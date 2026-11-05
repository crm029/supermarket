<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: DENY');
header('X-Permitted-Cross-Domain-Policies: none');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header('Cross-Origin-Resource-Policy: same-origin');
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none'");

try {
    $tenant = app_tenant();
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $path = preg_replace('#^/api/v1#', '', $path);
    $method = app_method();
    $db = app_db();

    if ($method !== 'GET') {
        header('Allow: GET');
        app_json(['error' => 'این مسیر فعلاً فقط درخواست GET می‌پذیرد.'], 405);
    }

    if ($path === '/store' || $path === '/') {
        $stmt = $db->prepare("SELECT setting_key, setting_value FROM store_settings WHERE tenant_id = ?");
        $stmt->execute([$tenant['id']]);
        $settings = [];
        foreach ($stmt->fetchAll() as $row) {
            $settings[$row['setting_key']] = json_decode($row['setting_value'], true);
        }
        app_json(['data' => [
            'name' => $tenant['name'],
            'slug' => $tenant['slug'],
            'timezone' => $tenant['timezone'],
            'currency' => $tenant['currency'],
            'settings' => $settings,
            'fulfillment' => ['delivery' => true, 'pickup' => true],
        ]]);
    }

    if ($path === '/categories') {
        $stmt = $db->prepare(
            "SELECT id, parent_id, title, slug, image_media_id
             FROM categories WHERE tenant_id = ? AND is_active = 1
             ORDER BY sort_order, title"
        );
        $stmt->execute([$tenant['id']]);
        app_json(['data' => $stmt->fetchAll()]);
    }

    if ($path === '/products') {
        $query = trim((string)($_GET['q'] ?? ''));
        $category = filter_input(INPUT_GET, 'category', FILTER_VALIDATE_INT);
        $page = max(1, min(1000, (int)($_GET['page'] ?? 1)));
        $limit = 24;
        $where = ['tenant_id = :tenant', 'is_active = 1'];
        $params = ['tenant' => $tenant['id']];
        if ($query !== '') {
            $where[] = '(title LIKE :q OR sku LIKE :q OR barcode LIKE :q)';
            $params['q'] = '%' . mb_substr($query, 0, 80) . '%';
        }
        if ($category) {
            $where[] = 'category_id = :category';
            $params['category'] = $category;
        }
        $sqlWhere = implode(' AND ', $where);
        $count = $db->prepare("SELECT COUNT(*) FROM products WHERE {$sqlWhere}");
        $count->execute($params);
        $stmt = $db->prepare(
            "SELECT id, sku, title, brand, description, unit_label, price_rial,
                    compare_at_rial, image_media_id, is_weighted
             FROM products WHERE {$sqlWhere}
             ORDER BY title LIMIT {$limit} OFFSET " . (($page - 1) * $limit)
        );
        $stmt->execute($params);
        app_json(['data' => $stmt->fetchAll(), 'meta' => [
            'page' => $page, 'per_page' => $limit, 'total' => (int)$count->fetchColumn()
        ]]);
    }

    app_json(['error' => 'مسیر موردنظر پیدا نشد.'], 404);
} catch (Throwable $e) {
    error_log('[supermarket360-api] ' . $e->getMessage());
    app_json(['error' => 'خطای داخلی سرور.'], 500);
}
