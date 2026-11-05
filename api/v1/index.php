<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/auth.php';
require_once dirname(__DIR__, 2) . '/app/policies.php';
require_once dirname(__DIR__, 2) . '/app/admin_catalog.php';

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

    if ($path === '/admin/products') {
        if ($method === 'GET') {
            $session = app_auth_require($db, $tenant);
            app_auth_require_permission($db, $tenant, $session, 'catalog.products.read');
            app_admin_product_list($db, $tenant);
        }
        if ($method === 'POST') {
            $session = app_auth_require($db, $tenant);
            app_auth_require_csrf($session);
            app_auth_require_permission($db, $tenant, $session, 'catalog.products.manage');
            app_admin_product_create($db, $tenant, $session);
        }
        header('Allow: GET, POST');
        app_json(['error' => 'روش درخواست برای این مسیر مجاز نیست.'], 405);
    }

    if (preg_match('#^/admin/products/([1-9][0-9]*)$#', $path, $productMatch)) {
        $productId = (int)$productMatch[1];
        $session = app_auth_require($db, $tenant);
        if ($method === 'PATCH') {
            app_auth_require_csrf($session);
            app_auth_require_permission($db, $tenant, $session, 'catalog.products.manage');
            app_admin_product_update($db, $tenant, $session, $productId);
        }
        if ($method === 'DELETE') {
            app_auth_require_csrf($session);
            app_auth_require_permission($db, $tenant, $session, 'catalog.products.manage');
            app_admin_product_deactivate($db, $tenant, $session, $productId);
        }
        header('Allow: PATCH, DELETE');
        app_json(['error' => 'روش درخواست برای این مسیر مجاز نیست.'], 405);
    }

    if ($path === '/auth/request-otp' && $method === 'POST') {
        app_auth_request_otp($db, $tenant);
    }

    if ($path === '/auth/verify-otp' && $method === 'POST') {
        app_auth_verify_otp($db, $tenant);
    }

    if ($path === '/auth/logout' && $method === 'POST') {
        $session = app_auth_require($db, $tenant);
        app_auth_require_csrf($session);
        app_auth_logout($db, $session);
    }

    if ($path === '/me' && $method === 'GET') {
        $session = app_auth_require($db, $tenant);
        app_json(['data' => [
            'id' => (int)$session['id'],
            'full_name' => $session['full_name'],
            'mobile' => $session['mobile'],
            'referral_code' => $session['referral_code'],
        ]]);
    }

    if ($method !== 'GET') {
        header('Allow: GET, POST, PATCH, DELETE');
        app_json(['error' => 'روش درخواست برای این مسیر مجاز نیست.'], 405);
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
            $where[] = '(title LIKE :q_title OR sku LIKE :q_sku OR barcode LIKE :q_barcode)';
            $search = '%' . mb_substr($query, 0, 80) . '%';
            $params['q_title'] = $search;
            $params['q_sku'] = $search;
            $params['q_barcode'] = $search;
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
