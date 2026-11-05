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
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function app_db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $host = app_env('DB_HOST', '127.0.0.1');
    $name = app_env('DB_DATABASE', 'supermarket360');
    $user = app_env('DB_USERNAME', 'root');
    $pass = app_env('DB_PASSWORD', '');
    $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function app_host(): string {
    $host = strtolower(trim((string)($_SERVER['HTTP_HOST'] ?? '')));
    // Reject malformed Host headers and ports; configured domains are stored without ports.
    if ($host === '' || !preg_match('/^(?=.{1,253}$)[a-z0-9.-]+$/', $host)) {
        app_json(['error' => 'دامنه درخواست معتبر نیست.'], 400);
    }
    return rtrim($host, '.');
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
