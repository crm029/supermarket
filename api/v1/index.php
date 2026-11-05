<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/auth.php';
require_once dirname(__DIR__, 2) . '/app/policies.php';
require_once dirname(__DIR__, 2) . '/app/admin_catalog.php';
require_once dirname(__DIR__, 2) . '/app/media.php';

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

    if ($path === '/admin/categories') {
        if ($method === 'GET') {
            $session = app_auth_require($db, $tenant);
            app_auth_require_permission($db, $tenant, $session, 'catalog.categories.read');
            app_admin_category_list($db, $tenant);
        }
        if ($method === 'POST') {
            $session = app_auth_require($db, $tenant);
            app_auth_require_csrf($session);
            app_auth_require_permission($db, $tenant, $session, 'catalog.categories.manage');
            app_admin_category_create($db, $tenant, $session);
        }
        header('Allow: GET, POST');
        app_json(['error' => 'روش درخواست برای این مسیر مجاز نیست.'], 405);
    }

    if (preg_match('#^/admin/categories/([1-9][0-9]*)$#', $path, $categoryMatch)) {
        $categoryId = (int)$categoryMatch[1];
        $session = app_auth_require($db, $tenant);
        if ($method === 'PATCH') {
            app_auth_require_csrf($session);
            app_auth_require_permission($db, $tenant, $session, 'catalog.categories.manage');
            app_admin_category_update($db, $tenant, $session, $categoryId);
        }
        if ($method === 'DELETE') {
            app_auth_require_csrf($session);
            app_auth_require_permission($db, $tenant, $session, 'catalog.categories.manage');
            app_admin_category_deactivate($db, $tenant, $session, $categoryId);
        }
        header('Allow: PATCH, DELETE');
        app_json(['error' => 'روش درخواست برای این مسیر مجاز نیست.'], 405);
    }

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

    if (preg_match('#^/products/([1-9][0-9]*)$#', $path, $productMatch)) {
        $productId = (int)$productMatch[1];
        $stmt = $db->prepare(
            "SELECT p.id, p.sku, p.barcode, p.title, p.brand, p.description,
                    p.unit_label, p.price_rial, p.compare_at_rial, p.image_media_id,
                    p.is_weighted, c.id AS category_id, c.title AS category_title, c.slug AS category_slug,
                    (SELECT COALESCE(SUM(sl.on_hand - sl.reserved), 0)
                     FROM stock_levels sl
                     JOIN warehouses w ON w.id = sl.warehouse_id AND w.tenant_id = sl.tenant_id
                     WHERE sl.tenant_id = p.tenant_id AND sl.product_id = p.id AND w.is_active = 1) AS available_quantity
             FROM products p
             LEFT JOIN categories c
               ON c.tenant_id = p.tenant_id AND c.id = p.category_id AND c.is_active = 1
             WHERE p.tenant_id = ? AND p.id = ? AND p.is_active = 1
             LIMIT 1"
        );
        $stmt->execute([$tenant['id'], $productId]);
        $product = $stmt->fetch();
        if (!$product) app_json(['error' => 'کالا پیدا نشد.'], 404);

        $product['image'] = app_media_public_data($db, (int)$tenant['id'],
            $product['image_media_id'] === null ? null : (int)$product['image_media_id']);
        $product['category'] = $product['category_id'] === null ? null : [
            'id' => (int)$product['category_id'],
            'title' => $product['category_title'],
            'slug' => $product['category_slug'],
        ];
        $product['in_stock'] = (int)$product['available_quantity'] > 0;
        unset($product['image_media_id'], $product['category_id'], $product['category_title'],
            $product['category_slug'], $product['available_quantity']);
        $product['is_weighted'] = (bool)$product['is_weighted'];
        app_json(['data' => $product]);
    }

    if ($path === '/scenes') {
        $stmt = $db->prepare(
            "SELECT id, title, scene_type, media_id, fallback_media_id, sort_order, metadata
             FROM store_scenes
             WHERE tenant_id = ? AND is_published = 1
             ORDER BY sort_order, id"
        );
        $stmt->execute([$tenant['id']]);
        $scenes = $stmt->fetchAll();
        if ($scenes === []) app_json(['data' => []]);

        $hotspotsQuery = $db->prepare(
            "SELECT h.id, h.scene_id, h.target_scene_id, h.product_id, h.label,
                    h.x, h.y, h.action, h.payload,
                    p.title AS product_title, p.price_rial AS product_price_rial, p.image_media_id AS product_image_media_id,
                    target.title AS target_scene_title
             FROM scene_hotspots h
             LEFT JOIN products p
               ON p.tenant_id = h.tenant_id AND p.id = h.product_id AND p.is_active = 1
             LEFT JOIN store_scenes target
               ON target.tenant_id = h.tenant_id AND target.id = h.target_scene_id AND target.is_published = 1
             WHERE h.tenant_id = ?
               AND h.scene_id IN (SELECT id FROM store_scenes WHERE tenant_id = ? AND is_published = 1)
               AND (h.action <> 'product' OR p.id IS NOT NULL)
               AND (h.action <> 'navigate' OR target.id IS NOT NULL)
             ORDER BY h.scene_id, h.id"
        );
        $hotspotsQuery->execute([$tenant['id'], $tenant['id']]);
        $hotspotsByScene = [];
        foreach ($hotspotsQuery->fetchAll() as $hotspot) {
            $productData = null;
            if ($hotspot['product_id'] !== null) {
                $productData = [
                    'id' => (int)$hotspot['product_id'],
                    'title' => $hotspot['product_title'],
                    'price_rial' => $hotspot['product_price_rial'],
                    'image' => app_media_public_data($db, (int)$tenant['id'],
                        $hotspot['product_image_media_id'] === null ? null : (int)$hotspot['product_image_media_id']),
                ];
            }
            $targetData = $hotspot['target_scene_id'] === null ? null : [
                'id' => (int)$hotspot['target_scene_id'],
                'title' => $hotspot['target_scene_title'],
            ];
            $hotspotsByScene[(int)$hotspot['scene_id']][] = [
                'id' => (int)$hotspot['id'],
                'label' => $hotspot['label'],
                'x' => (float)$hotspot['x'],
                'y' => (float)$hotspot['y'],
                'action' => $hotspot['action'],
                'product' => $productData,
                'target_scene' => $targetData,
                'payload' => $hotspot['payload'] === null ? null : json_decode($hotspot['payload'], true),
            ];
        }

        $data = [];
        foreach ($scenes as $scene) {
            $sceneId = (int)$scene['id'];
            $data[] = [
                'id' => $sceneId,
                'title' => $scene['title'],
                'scene_type' => $scene['scene_type'],
                'sort_order' => (int)$scene['sort_order'],
                'metadata' => $scene['metadata'] === null ? null : json_decode($scene['metadata'], true),
                'media' => app_media_public_data($db, (int)$tenant['id'],
                    $scene['media_id'] === null ? null : (int)$scene['media_id']),
                'fallback_media' => app_media_public_data($db, (int)$tenant['id'],
                    $scene['fallback_media_id'] === null ? null : (int)$scene['fallback_media_id']),
                'hotspots' => $hotspotsByScene[$sceneId] ?? [],
            ];
        }
        app_json(['data' => $data]);
    }

    app_json(['error' => 'مسیر موردنظر پیدا نشد.'], 404);
} catch (Throwable $e) {
    error_log('[supermarket360-api] ' . $e->getMessage());
    app_json(['error' => 'خطای داخلی سرور.'], 500);
}
