<?php
declare(strict_types=1);

/**
 * Minimal PHP 8.2 bootstrap for the first server-side API slice.
 * Configure DB_* and APP_ENV in the server environment; never commit .env.
 */
define('APP_ROOT', dirname(__DIR__));
date_default_timezone_set('Asia/Tehran');

function app_env(string $key, ?string $default = null): ?string {
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    return ($value === false || $value === null || $value === '') ? $default : (string)$value;
}

function app_json(array $payload, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, max-age=0');
    header('Pragma: no-cache');
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        http_response_code(500);
        echo '{"error":"خطای داخلی سرور."}';
        exit;
    }
    echo $json;
    exit;
}

function app_db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $environment = strtolower(app_env('APP_ENV', 'development') ?? 'development');
    $production = $environment === 'production';

    // Local XAMPP defaults are development-only. Production must fail closed.
    $host = app_env('DB_HOST', $production ? null : '127.0.0.1');
    $name = app_env('DB_DATABASE', $production ? null : 'supermarket360');
    $user = app_env('DB_USERNAME', $production ? null : 'root');
    $pass = app_env('DB_PASSWORD', $production ? null : '');

    if (!$host || !$name || !$user || ($production && !$pass)) {
        throw new RuntimeException('Database environment is not configured.');
    }

    $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
        PDO::ATTR_TIMEOUT => 5,
    ]);
    return $pdo;
}

function app_host(): string {
    $raw = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($raw === '' || strlen($raw) > 260 || preg_match('/[\x00-\x20,\\\\\/]/', $raw)) {
        app_json(['error' => 'دامنه درخواست معتبر نیست.'], 400);
    }

    // Permit an optional port for local development, but never include it in tenant lookup.
    $host = strtolower((string)preg_replace('/:\\d{1,5}$/', '', $raw));
    $host = rtrim($host, '.');

    if ($host === 'localhost') return $host;
    if (filter_var($host, FILTER_VALIDATE_IP)) return $host;

    if (strlen($host) > 253 || str_contains($host, '..')) {
        app_json(['error' => 'دامنه درخواست معتبر نیست.'], 400);
    }
    $labels = explode('.', $host);
    foreach ($labels as $label) {
        if ($label === '' || strlen($label) > 63 ||
            !preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $label)) {
            app_json(['error' => 'دامنه درخواست معتبر نیست.'], 400);
        }
    }
    return $host;
}

function app_tenant(): array {
    static $tenant = null;
    if ($tenant !== null) return $tenant;

    $stmt = app_db()->prepare(
        "SELECT t.id, t.name, t.slug, t.timezone, t.currency
         FROM tenant_domains d
         JOIN tenants t ON t.id = d.tenant_id
         WHERE d.host = :host AND d.verified_at IS NOT NULL AND t.status = 'active'
         LIMIT 1"
    );
    $stmt->execute(['host' => app_host()]);
    $tenant = $stmt->fetch();
    if (!$tenant) app_json(['error' => 'فروشگاهی برای این دامنه فعال نیست.'], 404);
    return $tenant;
}

function app_method(): string {
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}
