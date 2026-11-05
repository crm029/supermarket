<?php
declare(strict_types=1);

/**
 * Read-only production deployment preflight.
 *
 * Run from the project root or any working directory:
 *   php bin/production-preflight.php
 *
 * This command never prints secret values and never changes application data.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/app/bootstrap.php';

$errors = [];
$warnings = [];
$passed = [];

$check = static function (bool $ok, string $label, string $failure, bool $warning = false) use (&$errors, &$warnings, &$passed): void {
    if ($ok) {
        $passed[] = $label;
        fwrite(STDOUT, "[OK]   {$label}" . PHP_EOL);
        return;
    }
    if ($warning) {
        $warnings[] = $failure;
        fwrite(STDOUT, "[WARN] {$failure}" . PHP_EOL);
        return;
    }
    $errors[] = $failure;
    fwrite(STDOUT, "[FAIL] {$failure}" . PHP_EOL);
};

$env = static fn(string $key, ?string $default = null): ?string => app_env($key, $default);
$production = strtolower((string)$env('APP_ENV', '')) === 'production';
$check($production, 'APP_ENV is production', 'APP_ENV must be set explicitly to production.');

$debugValue = strtolower(trim((string)$env('APP_DEBUG', 'true')));
$debugDisabled = in_array($debugValue, ['false', '0', 'off', 'no'], true);
$check($debugDisabled, 'Application debug mode is disabled', 'Set APP_DEBUG=false.');

$key = (string)$env('APP_KEY', '');
$check(strlen($key) >= 32, 'Application key is configured', 'APP_KEY must be a unique secret with at least 32 characters.');

$baseUrl = trim((string)$env('APP_BASE_URL', ''));
$parts = $baseUrl !== '' ? parse_url($baseUrl) : false;
$validBaseUrl = is_array($parts)
    && strtolower((string)($parts['scheme'] ?? '')) === 'https'
    && !empty($parts['host'])
    && !isset($parts['user']) && !isset($parts['pass']) && !isset($parts['query']) && !isset($parts['fragment']);
$check($validBaseUrl, 'Public application URL uses HTTPS', 'APP_BASE_URL must be the exact public HTTPS origin, without credentials, query or fragment.');

foreach (['pdo_mysql', 'mbstring', 'curl', 'fileinfo'] as $extension) {
    $check(extension_loaded($extension), "PHP extension {$extension} is loaded", "Enable the PHP {$extension} extension.");
}

$smsProvider = strtolower((string)$env('SMS_PROVIDER', ''));
$smsReady = $smsProvider === 'kavenegar'
    && trim((string)$env('KAVENEGAR_API_KEY', '')) !== ''
    && trim((string)$env('KAVENEGAR_VERIFY_TEMPLATE', '')) !== '';
$check($smsReady, 'Production SMS provider is configured', 'Set SMS_PROVIDER=kavenegar and configure KAVENEGAR_API_KEY plus KAVENEGAR_VERIFY_TEMPLATE.');

$merchant = trim((string)$env('ZARINPAL_MERCHANT_ID', ''));
$sandbox = filter_var($env('ZARINPAL_SANDBOX', 'true'), FILTER_VALIDATE_BOOLEAN);
$check($merchant !== '', 'ZarinPal merchant ID is configured', 'Set ZARINPAL_MERCHANT_ID for this store.');
$check(!$sandbox, 'ZarinPal live mode is selected', 'Set ZARINPAL_SANDBOX=false after sandbox acceptance tests.');

$mediaRoot = trim((string)$env('MEDIA_STORAGE_PATH', ''));
$isWindowsDrive = strlen($mediaRoot) >= 3 && ctype_alpha($mediaRoot[0]) && $mediaRoot[1] === ':' && ($mediaRoot[2] === '/' || $mediaRoot[2] === '\\\\');
$isAbsolute = $mediaRoot !== '' && (str_starts_with($mediaRoot, DIRECTORY_SEPARATOR) || $isWindowsDrive);
$mediaReal = $isAbsolute ? realpath($mediaRoot) : false;
$docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath((string)$_SERVER['DOCUMENT_ROOT']) : false;
$outsideDocRoot = $mediaReal !== false && is_dir($mediaReal)
    && ($docRoot === false || !str_starts_with($mediaReal . DIRECTORY_SEPARATOR, rtrim($docRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR));
$check($outsideDocRoot, 'Media storage exists outside the public document root', 'Set MEDIA_STORAGE_PATH to an existing writable directory outside DOCUMENT_ROOT.');
$check($mediaReal !== false && is_writable($mediaReal), 'Media storage is writable', 'The PHP process must be able to write to MEDIA_STORAGE_PATH.');

$mediaUrl = trim((string)$env('MEDIA_BASE_URL', ''));
$mediaParts = $mediaUrl !== '' ? parse_url($mediaUrl) : false;
$mediaUrlValid = is_array($mediaParts)
    && strtolower((string)($mediaParts['scheme'] ?? '')) === 'https'
    && !empty($mediaParts['host'])
    && !isset($mediaParts['user'], $mediaParts['pass'], $mediaParts['query'], $mediaParts['fragment']);
$check($mediaUrlValid, 'Public media base URL uses HTTPS', 'Set MEDIA_BASE_URL to the HTTPS static-media/CDN origin.');

$worker = dirname(__DIR__) . '/bin/expire-reservations.php';
$check(is_file($worker), 'Reservation expiry worker is present', 'The bin/expire-reservations.php worker is missing.');

$db = null;
try {
    $db = app_db();
    $check(true, 'Database connection succeeds', '');
} catch (Throwable $e) {
    $check(false, 'Database connection succeeds', 'Database connection failed; verify DB_HOST, DB_DATABASE, DB_USERNAME and DB_PASSWORD.');
}

if ($db instanceof PDO) {
    $requiredTables = [
        'tenants', 'tenant_domains', 'users', 'roles', 'permissions', 'role_permissions',
        'auth_challenges', 'auth_sessions', 'products', 'categories', 'warehouses',
        'stock_levels', 'stock_reservations', 'orders', 'order_items', 'payment_attempts',
        'wallet_accounts', 'wallet_entries', 'media_assets', 'audit_logs',
    ];
    try {
        $placeholders = implode(',', array_fill(0, count($requiredTables), '?'));
        $stmt = $db->prepare(
            "SELECT TABLE_NAME FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ({$placeholders})"
        );
        $stmt->execute($requiredTables);
        $found = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $missing = array_values(array_diff($requiredTables, $found));
        $check($missing === [], 'Core database tables are installed', $missing === []
            ? ''
            : 'Missing database tables: ' . implode(', ', $missing) . '. Apply the documented schema and migrations.');

        if ($validBaseUrl) {
            $host = strtolower(rtrim((string)$parts['host'], '.'));
            $domain = $db->prepare(
                "SELECT t.id
                 FROM tenant_domains d
                 JOIN tenants t ON t.id = d.tenant_id
                 WHERE d.host = ? AND d.verified_at IS NOT NULL AND t.status = 'active'
                 LIMIT 1"
            );
            $domain->execute([$host]);
            $check((bool)$domain->fetchColumn(), 'Public domain maps to an active verified store', 'The APP_BASE_URL host has no active tenant with a verified domain mapping.');
        } else {
            $check(false, 'Public domain maps to an active verified store', 'Fix APP_BASE_URL before checking the verified tenant domain.');
        }
    } catch (Throwable $e) {
        $check(false, 'Database schema and tenant-domain checks complete', 'Unable to inspect database schema or verified domain mapping.');
    }
}

fwrite(STDOUT, PHP_EOL . 'Preflight summary: ' . count($passed) . ' passed, ' . count($warnings) . ' warnings, ' . count($errors) . ' failed.' . PHP_EOL);
if ($warnings !== []) {
    fwrite(STDOUT, 'Warnings require review before launch.' . PHP_EOL);
}
if ($errors !== []) {
    fwrite(STDERR, 'Production preflight failed. Do not enable live checkout until every failed check is resolved.' . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, 'Configuration preflight passed. This does not replace payment/SMS acceptance, backup-restore, security, load, accessibility or real-device testing.' . PHP_EOL);
exit(0);
