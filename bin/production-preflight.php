<?php
declare(strict_types=1);

/**
 * Read-only production deployment preflight.
 * Run from the repository root with APP_ENV=production and the real server environment.
 * This command never prints secret values and never changes the database.
 */
require_once dirname(__DIR__) . '/app/bootstrap.php';

$checks = [];
$fail = static function (string $label, string $detail) use (&$checks): void {
    $checks[] = ['FAIL', $label, $detail];
};
$pass = static function (string $label, string $detail) use (&$checks): void {
    $checks[] = ['PASS', $label, $detail];
};
$warn = static function (string $label, string $detail) use (&$checks): void {
    $checks[] = ['WARN', $label, $detail];
};

$environment = strtolower((string)app_env('APP_ENV', ''));
if ($environment !== 'production') {
    $fail('APP_ENV', 'Set APP_ENV=production before running the production preflight.');
} else {
    $pass('APP_ENV', 'Production mode is selected.');
}

if (PHP_VERSION_ID < 80200) {
    $fail('PHP', 'PHP 8.2 or newer is required; detected ' . PHP_VERSION . '.');
} else {
    $pass('PHP', PHP_VERSION);
}

foreach (['pdo_mysql', 'mbstring', 'curl', 'fileinfo'] as $extension) {
    extension_loaded($extension)
        ? $pass('PHP extension: ' . $extension, 'Loaded.')
        : $fail('PHP extension: ' . $extension, 'Install and enable this extension.');
}

if (function_exists('dns_get_record')) {
    $pass('DNS TXT lookup', 'PHP DNS record lookup is available for tenant-domain verification.');
} else {
    $fail('DNS TXT lookup', 'Enable the PHP DNS functions required to verify tenant TXT records.');
}

$appKey = (string)(app_env('APP_KEY') ?? '');
if (strlen($appKey) < 32) {
    $fail('APP_KEY', 'Set a unique secret containing at least 32 characters.');
} else {
    $pass('APP_KEY', 'A sufficiently long key is configured; its value is not displayed.');
}

if (filter_var(app_env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN)) {
    $fail('APP_DEBUG', 'Debug mode must be disabled in production.');
} else {
    $pass('APP_DEBUG', 'Debug mode is disabled.');
}

$baseUrl = rtrim(trim((string)(app_env('APP_BASE_URL') ?? '')), '/');
$urlParts = $baseUrl !== '' ? parse_url($baseUrl) : false;
if (!is_array($urlParts) || strtolower((string)($urlParts['scheme'] ?? '')) !== 'https' ||
    empty($urlParts['host']) || isset($urlParts['user']) || isset($urlParts['pass']) ||
    isset($urlParts['query']) || isset($urlParts['fragment'])) {
    $fail('APP_BASE_URL', 'Set the exact public HTTPS origin without credentials, query or fragment.');
} else {
    $pass('APP_BASE_URL', 'A valid HTTPS callback origin is configured.');
}

$db = null;
try {
    $db = app_db();
    $version = (string)$db->query('SELECT VERSION()')->fetchColumn();
    if (stripos($version, 'mariadb') !== false) {
        $fail('Database version', 'MariaDB compatibility has not been validated for production (' . $version . ').');
    } elseif (version_compare(preg_replace('/[^0-9.].*$/', '', $version) ?: '0', '8.0.16', '<')) {
        $fail('Database version', 'MySQL 8.0.16 or newer is required; detected ' . $version . '.');
    } else {
        $pass('Database version', $version);
    }

    $requiredTables = [
        'tenants', 'tenant_domains', 'users', 'permissions', 'roles',
        'role_permissions', 'auth_sessions', 'auth_challenges',
        'products', 'categories', 'stock_levels', 'stock_reservations',
        'orders', 'order_assignments', 'order_refunds', 'notifications',
        'payment_attempts', 'media_assets', 'wallet_accounts', 'wallet_entries', 'referral_events',
    ];
    $tableQuery = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
    );
    $missing = [];
    foreach ($requiredTables as $table) {
        $tableQuery->execute([$table]);
        if ((int)$tableQuery->fetchColumn() !== 1) $missing[] = $table;
    }
    if ($missing !== []) {
        $fail('Database schema', 'Missing required tables: ' . implode(', ', $missing) . '. Apply the documented schema and migrations.');
    } else {
        $pass('Database schema', count($requiredTables) . ' required core tables are present.');
    }

    $requiredDomainColumns = ['verification_token_hash', 'verification_expires_at', 'verification_attempts'];
    $columnQuery = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
    );
    $missingDomainColumns = [];
    foreach ($requiredDomainColumns as $column) {
        $columnQuery->execute(['tenant_domains', $column]);
        if ((int)$columnQuery->fetchColumn() !== 1) $missingDomainColumns[] = $column;
    }
    if ($missingDomainColumns !== []) {
        $fail('Domain verification schema', 'Missing tenant_domains columns: ' . implode(', ', $missingDomainColumns) . '. Apply the DNS challenge migration.');
    } else {
        $pass('Domain verification schema', 'DNS TXT challenge fields are present.');
    }

    $activeDomainCount = (int)$db->query(
        "SELECT COUNT(*) FROM tenant_domains d JOIN tenants t ON t.id=d.tenant_id
         WHERE d.verified_at IS NOT NULL AND t.status='active'"
    )->fetchColumn();
    if ($activeDomainCount < 1) {
        $fail('Tenant domain', 'No verified domain belongs to an active tenant.');
    } else {
        $pass('Tenant domain', $activeDomainCount . ' verified active domain mapping(s) found.');
    }
} catch (Throwable $error) {
    $fail('Database connection', 'Connection or schema inspection failed: ' . $error->getMessage());
}

$smsProvider = strtolower(trim((string)app_env('SMS_PROVIDER', 'disabled')));
if ($smsProvider !== 'kavenegar' ||
    trim((string)(app_env('KAVENEGAR_API_KEY') ?? '')) === '' ||
    trim((string)(app_env('KAVENEGAR_VERIFY_TEMPLATE') ?? '')) === '') {
    $fail('SMS provider', 'Configure SMS_PROVIDER=kavenegar, KAVENEGAR_API_KEY and KAVENEGAR_VERIFY_TEMPLATE.');
} else {
    $pass('SMS provider', 'Kavenegar credentials and template are present; live delivery still needs a real-phone test.');
}

if (trim((string)(app_env('ZARINPAL_MERCHANT_ID') ?? '')) === '') {
    $fail('Payment gateway', 'Configure the merchant-owned ZARINPAL_MERCHANT_ID.');
} elseif (filter_var(app_env('ZARINPAL_SANDBOX', 'true'), FILTER_VALIDATE_BOOLEAN)) {
    $fail('Payment gateway', 'ZARINPAL_SANDBOX must be false for production.');
} else {
    $pass('Payment gateway', 'Merchant ID is present and sandbox mode is disabled; provider acceptance is not verified by this check.');
}

$mediaPath = trim((string)(app_env('MEDIA_STORAGE_PATH') ?? ''));
$mediaBase = trim((string)(app_env('MEDIA_BASE_URL') ?? ''));
$mediaParts = $mediaBase !== '' ? parse_url($mediaBase) : false;
if ($mediaPath === '' || !str_starts_with($mediaPath, DIRECTORY_SEPARATOR)) {
    $fail('Media storage', 'MEDIA_STORAGE_PATH must be an absolute path outside the public web root.');
} else {
    $realMediaPath = realpath($mediaPath);
    if ($realMediaPath === false || !is_dir($realMediaPath) || !is_writable($realMediaPath)) {
        $fail('Media storage', 'The configured storage directory must exist and be writable by PHP.');
    } else {
        $applicationRoot = realpath(APP_ROOT);
        $documentRoot = trim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
        $realDocumentRoot = $documentRoot !== '' ? realpath($documentRoot) : false;
        $insideApplication = $applicationRoot !== false &&
            ($realMediaPath === $applicationRoot || str_starts_with($realMediaPath, $applicationRoot . DIRECTORY_SEPARATOR));
        $insideDocumentRoot = $realDocumentRoot !== false &&
            ($realMediaPath === $realDocumentRoot || str_starts_with($realMediaPath, $realDocumentRoot . DIRECTORY_SEPARATOR));
        if ($insideApplication || $insideDocumentRoot) {
            $fail('Media storage', 'The media directory must be outside the application and public document roots.');
        } else {
            $pass('Media storage', 'An absolute writable directory outside the application root is configured.');
        }
    }
}
if (!is_array($mediaParts) || strtolower((string)($mediaParts['scheme'] ?? '')) !== 'https' || empty($mediaParts['host'])) {
    $fail('MEDIA_BASE_URL', 'Configure a public HTTPS media URL backed by a static-only alias or CDN.');
} else {
    $pass('MEDIA_BASE_URL', 'A public HTTPS media origin is configured.');
}

if (strtolower((string)(app_env('DB_USERNAME') ?? '')) === 'root') {
    $fail('Database account', 'Use a dedicated least-privilege database account, not root.');
} else {
    $pass('Database account', 'A non-root database username is configured.');
}

$failed = 0;
foreach ($checks as [$status, $label, $detail]) {
    if ($status === 'FAIL') $failed++;
    printf("[%s] %s — %s\n", $status, $label, $detail);
}
printf("\nResult: %d check(s), %d failure(s).\n", count($checks), $failed);
exit($failed === 0 ? 0 : 1);
