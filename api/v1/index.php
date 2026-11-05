<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/auth.php';
require_once dirname(__DIR__, 2) . '/app/policies.php';
require_once dirname(__DIR__, 2) . '/app/admin_catalog.php';
require_once dirname(__DIR__, 2) . '/app/media.php';
require_once dirname(__DIR__, 2) . '/app/commerce.php';
require_once dirname(__DIR__, 2) . '/app/payments.php';
require_once dirname(__DIR__, 2) . '/app/admin_orders.php';
require_once dirname(__DIR__, 2) . '/app/inventory.php';
require_once dirname(__DIR__, 2) . '/app/admin_scenes.php';
require_once dirname(__DIR__, 2) . '/app/reports.php';
require_once dirname(__DIR__, 2) . '/app/wallet.php';
require_once dirname(__DIR__, 2) . '/app/customer_cart.php';
require_once dirname(__DIR__, 2) . '/app/store_settings.php';
require_once dirname(__DIR__, 2) . '/app/staff.php';
require_once dirname(__DIR__, 2) . '/app/delivery_zones.php';
require_once dirname(__DIR__, 2) . '/app/pickup_slots.php';
require_once dirname(__DIR__, 2) . '/app/coupons.php';

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
    $apiPrefix = '/api/v1';
    $apiPosition = strpos($path, $apiPrefix);
    if ($apiPosition !== false &&
        (!isset($path[$apiPosition + strlen($apiPrefix)]) || $path[$apiPosition + strlen($apiPrefix)] === '/')) {
        $path = substr($path, $apiPosition + strlen($apiPrefix));
        if ($path === '') $path = '/';
    }
    $method = app_method();
    $db = app_db();

    if (preg_match('#^/admin/media/([1-9][0-9]*)$#', $path, $mediaMatch) && $method === 'DELETE') {
        $session = app_auth_require($db, $tenant);
        app_auth_require_csrf($session);
        app_auth_require_permission($db, $tenant, $session, 'media.manage');
        app_admin_media_delete($db, $tenant, $session, (int)$mediaMatch[1]);
    }

    if ($path === '/admin/media') {
        $session = app_auth_require($db, $tenant);
        app_auth_require_permission($db, $tenant, $session, 'media.manage');
        if ($method === 'GET') app_admin_media_list($db, $tenant);
        if ($method === 'POST') {
            app_auth_require_csrf($session);
            app_media_upload($db, $tenant, $session);
        }
        header('Allow: GET, POST');
        app_json(['error' => 'روش درخواست برای این مسیر مجاز نیست.'], 405);
    }

    if ($path === '/admin/scenes') {
        $session = app_auth_require($db, $tenant);
        app_auth_require_permission($db, $tenant, $session, 'media.manage');
        if ($method === 'GET') app_admin_scene_list($db, $tenant);
        if ($method === 'POST') {
            app_auth_require_csrf($session);
            app_admin_scene_create($db, $tenant, $session);
        }
        header('Allow: GET, POST');
        app_json(['error' => 'روش درخواست برای این مسیر مجاز نیست.'], 405);
    }

    if (preg_match('#^/admin/scenes/([1-9][0-9]*)$#', $path, $sceneMatch)) {
        $session = app_auth_require($db, $tenant);
        app_auth_require_permission($db, $tenant, $session, 'media.manage');
        if ($method === 'PATCH') {
            app_auth_require_csrf($session);
            app_admin_scene_update($db, $tenant, $session, (int)$sceneMatch[1]);
        }
        if ($method === 'DELETE') {
            app_auth_require_csrf($session);
            app_admin_scene_unpublish($db, $tenant, $session, (int)$sceneMatch[1]);
        }
        header('Allow: PATCH, DELETE');
        app_json(['error' => 'روش درخواست برای این مسیر مجاز نیست.'], 405);
    }

    if (preg_match('#^/admin/scenes/([1-9][0-9]*)/hotspots$#', $path, $hotspotListMatch) && $method === 'GET') {
        $session = app_auth_require($db, $tenant);
        app_auth_require_permission($db, $tenant, $session, 'media.manage');
        app_admin_hotspot_list($db, $tenant, (int)$hotspotListMatch[1]);
    }

    if (preg_match('#^/admin/scenes/([1-9][0-9]*)/hotspots$#', $path, $hotspotSceneMatch) && $method === 'POST') {
        $session = app_auth_require($db, $tenant);
        app_auth_require_csrf($session);
        app_auth_require_permission($db, $tenant, $session, 'media.manage');
        app_admin_hotspot_create($db, $tenant, $session, (int)$hotspotSceneMatch[1]);
    }

    if (preg_match('#^/admin/hotspots/([1-9][0-9]*)$#', $path, $hotspotMatch) && $method === 'DELETE') {
        $session = app_auth_require($db, $tenant);
        app_auth_require_csrf($session);
        app_auth_require_permission($db, $tenant, $session, 'media.manage');
        app_admin_hotspot_delete($db, $tenant, $session, (int)$hotspotMatch[1]);
    }

    if ($path === '/admin/warehouses') {
        $session = app_auth_require($db, $tenant);
        if ($method === 'GET') {
            app_auth_require_permission($db, $tenant, $session, 'inventory.read');
            app_inventory_warehouse_list($db, $tenant);
        }
        if ($method === 'POST') {
            app_auth_require_csrf($session);
            app_auth_require_permission($db, $tenant, $session, 'inventory.manage');
            app_inventory_warehouse_create($db, $tenant, $session);
        }
        header('Allow: GET, POST');
        app_json(['error' => 'روش درخواست برای این مسیر مجاز نیست.'], 405);
    }

    if ($path === '/admin/stock' && $method === 'GET') {
        $session = app_auth_require($db, $tenant);
        app_auth_require_permission($db, $tenant, $session, 'inventory.read');
        app_inventory_stock_list($db, $tenant);
    }

    if ($path === '/admin/stock/receipts' && $method === 'POST') {
        $session = app_auth_require($db, $tenant);
        app_auth_require_csrf($session);
        app_auth_require_permission($db, $tenant, $session, 'inventory.manage');
        app_inventory_receipt($db, $tenant, $session);
    }

    if ($path === '/admin/stock/adjustments' && $method === 'POST') {
        $session = app_auth_require($db, $tenant);
        app_auth_require_csrf($session);
        app_auth_require_permission($db, $tenant, $session, 'inventory.manage');
        app_inventory_adjustment($db, $tenant, $session);
    }

    if ($path === '/admin/stock/transfers' && $method === 'POST') {
        $session = app_auth_require($db, $tenant);
        app_auth_require_csrf($session);
        app_auth_require_permission($db, $tenant, $session, 'inventory.manage');
        app_inventory_transfer($db, $tenant, $session);
    }

    if ($path === '/admin/reports/overview' && $method === 'GET') {
        $session = app_auth_require($db, $tenant);
        app_auth_require_permission($db, $tenant, $session, 'reports.read');
        app_admin_report_overview($db, $tenant);
    }

    if ($path === '/admin/orders' && $method === 'GET') {
        $session = app_auth_require($db, $tenant);
        app_auth_require_permission($db, $tenant, $session, 'orders.read');
        app_admin_orders_list($db, $tenant);
    }

    if (preg_match('#^/admin/orders/([1-9][0-9]*)/transition$#', $path, $orderTransitionMatch) && $method === 'POST') {
        $session = app_auth_require($db, $tenant);
        app_auth_require_csrf($session);
        app_auth_require_permission($db, $tenant, $session, 'orders.manage');
        app_admin_order_transition($db, $tenant, $session, (int)$orderTransitionMatch[1]);
    }

    if (preg_match('#^/me/orders/([1-9][0-9]*)$#', $path, $customerOrderMatch) && $method === 'GET') {
        $session = app_auth_require($db, $tenant);
        app_customer_order_detail($db, $tenant, $session, (int)$customerOrderMatch[1]);
    }

    if (preg_match('#^/me/orders/([1-9][0-9]*)/cancel$#', $path, $customerCancelMatch) && $method === 'POST') {
        $session = app_auth_require($db, $tenant);
        app_auth_require_csrf($session);
        app_customer_cancel_order($db, $tenant, $session, (int)$customerCancelMatch[1]);
    }

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

    if (preg_match('#^/admin/products/([1-9][0-9]*)/variants$#', $path, $variantParentMatch)) {
        $session = app_auth_require($db, $tenant);
        app_auth_require_permission($db, $tenant, $session, 'catalog.products.manage');
        $parentId = (int)$variantParentMatch[1];
        if ($method === 'GET') app_admin_product_variant_list($db, $tenant, $parentId);
        if ($method === 'POST') {
            app_auth_require_csrf($session);
            app_admin_product_variant_create($db, $tenant, $session, $parentId);
        }
        header('Allow: GET, POST');
        app_json(['error' => 'روش درخواست برای این مسیر مجاز نیست.'], 405);
    }

    if (preg_match('#^/admin/products/([1-9][0-9]*)/variants/([1-9][0-9]*)$#', $path, $variantMatch)) {
        $session = app_auth_require($db, $tenant);
        app_auth_require_permission($db, $tenant, $session, 'catalog.products.manage');
        $parentId = (int)$variantMatch[1];
        $variantId = (int)$variantMatch[2];
        if ($method === 'PATCH') {
            app_auth_require_csrf($session);
            app_admin_product_variant_update($db, $tenant, $session, $parentId, $variantId);
        }
        if ($method === 'DELETE') {
            app_auth_require_csrf($session);
            app_admin_product_variant_deactivate($db, $tenant, $session, $parentId, $variantId);
        }
        header('Allow: PATCH, DELETE');
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

    if ($path === '/cart/quote' && $method === 'POST') {
        app_commerce_public_quote($db, $tenant);
    }

    if ($path === '/me/addresses') {
        $session = app_auth_require($db, $tenant);
        if ($method === 'GET') app_commerce_list_addresses($db, $tenant, $session);
        if ($method === 'POST') {
            app_auth_require_csrf($session);
            app_commerce_create_address($db, $tenant, $session);
        }
        header('Allow: GET, POST');
        app_json(['error' => 'روش درخواست برای این مسیر مجاز نیست.'], 405);
    }

    if (preg_match('#^/me/addresses/([1-9][0-9]*)$#', $path, $addressMatch)) {
        $session = app_auth_require($db, $tenant);
        if ($method === 'PATCH') {
            app_auth_require_csrf($session);
            app_commerce_update_address($db, $tenant, $session, (int)$addressMatch[1]);
        }
        if ($method === 'DELETE') {
            app_auth_require_csrf($session);
            app_commerce_delete_address($db, $tenant, $session, (int)$addressMatch[1]);
        }
        header('Allow: PATCH, DELETE');
        app_json(['error' => 'روش درخواست برای این مسیر مجاز نیست.'], 405);
    }

    if ($path === '/payments/zarinpal/request' && $method === 'POST') {
        $session = app_auth_require($db, $tenant);
        app_auth_require_csrf($session);
        app_payment_start($db, $tenant, $session);
    }

    if ($path === '/payments/zarinpal/callback' && $method === 'GET') {
        app_payment_callback($db, $tenant);
    }

    if ($path === '/checkout' && $method === 'POST') {
        $session = app_auth_require($db, $tenant);
        app_auth_require_csrf($session);
        app_commerce_create_order($db, $tenant, $session);
    }

    if ($path === '/me/cart') {
        $session = app_auth_require($db, $tenant);
        if ($method === 'GET') app_customer_cart_read($db, $tenant, $session);
        if ($method === 'PUT') {
            app_auth_require_csrf($session);
            app_customer_cart_replace($db, $tenant, $session);
        }
        header('Allow: GET, PUT');
        app_json(['error' => 'روش درخواست برای این مسیر مجاز نیست.'], 405);
    }

    if ($path === '/me/wallet' && $method === 'GET') {
        $session = app_auth_require($db, $tenant);
        app_wallet_customer_summary($db, $tenant, $session);
    }

    if ($path === '/me/orders' && $method === 'GET') {
        $session = app_auth_require($db, $tenant);
        app_commerce_order_history($db, $tenant, $session);
    }

    if ($path === '/auth/register' && $method === 'POST') {
        app_auth_register($db, $tenant);
    }

    if ($path === '/auth/csrf' && $method === 'GET') {
        $session = app_auth_require($db, $tenant);
        app_auth_refresh_csrf($db, $session);
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

    if ($path === '/admin/staff') {
        $session = app_auth_require($db, $tenant);
        app_auth_require_permission($db, $tenant, $session, 'staff.manage');
        if ($method === 'GET') app_admin_staff_list($db, $tenant);
        header('Allow: GET');
        app_json(['error' => 'روش درخواست برای این مسیر مجاز نیست.'], 405);
    }

    if ($path === '/admin/roles' && $method === 'POST') {
        $session = app_auth_require($db, $tenant);
        app_auth_require_csrf($session);
        app_auth_require_permission($db, $tenant, $session, 'staff.manage');
        app_admin_staff_create_role($db, $tenant, $session);
    }

    if ($path === '/admin/staff/assign' && $method === 'POST') {
        $session = app_auth_require($db, $tenant);
        app_auth_require_csrf($session);
        app_auth_require_permission($db, $tenant, $session, 'staff.manage');
        app_admin_staff_assign_role($db, $tenant, $session);
    }

    if (preg_match('#^/admin/staff/([1-9][0-9]*)/roles/([1-9][0-9]*)$#', $path, $staffRoleMatch) && $method === 'DELETE') {
        $session = app_auth_require($db, $tenant);
        app_auth_require_csrf($session);
        app_auth_require_permission($db, $tenant, $session, 'staff.manage');
        app_admin_staff_remove_role($db, $tenant, $session, (int)$staffRoleMatch[1], (int)$staffRoleMatch[2]);
    }

    if ($path === '/admin/coupons') {
        $session = app_auth_require($db, $tenant);
        app_auth_require_permission($db, $tenant, $session, 'store.settings.manage');
        if ($method === 'GET') app_admin_coupon_list($db, $tenant);
        if ($method === 'POST') {
            app_auth_require_csrf($session);
            app_admin_coupon_create($db, $tenant, $session);
        }
        header('Allow: GET, POST');
        app_json(['error' => 'روش درخواست برای این مسیر مجاز نیست.'], 405);
    }

    if (preg_match('#^/admin/coupons/([1-9][0-9]*)$#', $path, $couponMatch)) {
        $session = app_auth_require($db, $tenant);
        app_auth_require_permission($db, $tenant, $session, 'store.settings.manage');
        if ($method === 'PATCH') {
            app_auth_require_csrf($session);
            app_admin_coupon_update($db, $tenant, $session, (int)$couponMatch[1]);
        }
        if ($method === 'DELETE') {
            app_auth_require_csrf($session);
            app_admin_coupon_disable($db, $tenant, $session, (int)$couponMatch[1]);
        }
        header('Allow: PATCH, DELETE');
        app_json(['error' => 'روش درخواست برای این مسیر مجاز نیست.'], 405);
    }

    if ($path === '/admin/pickup-slots') {
        $session = app_auth_require($db, $tenant);
        app_auth_require_permission($db, $tenant, $session, 'store.settings.manage');
        if ($method === 'GET') app_admin_pickup_slot_list($db, $tenant);
        if ($method === 'POST') {
            app_auth_require_csrf($session);
            app_admin_pickup_slot_create($db, $tenant, $session);
        }
        header('Allow: GET, POST');
        app_json(['error' => 'روش درخواست برای این مسیر مجاز نیست.'], 405);
    }

    if (preg_match('#^/admin/pickup-slots/([1-9][0-9]*)$#', $path, $pickupMatch) && $method === 'DELETE') {
        $session = app_auth_require($db, $tenant);
        app_auth_require_csrf($session);
        app_auth_require_permission($db, $tenant, $session, 'store.settings.manage');
        app_admin_pickup_slot_disable($db, $tenant, $session, (int)$pickupMatch[1]);
    }

    if ($path === '/admin/delivery-zones') {
        $session = app_auth_require($db, $tenant);
        app_auth_require_permission($db, $tenant, $session, 'store.settings.manage');
        if ($method === 'GET') app_admin_delivery_zone_list($db, $tenant);
        if ($method === 'POST') {
            app_auth_require_csrf($session);
            app_admin_delivery_zone_create($db, $tenant, $session);
        }
        header('Allow: GET, POST');
        app_json(['error' => 'روش درخواست برای این مسیر مجاز نیست.'], 405);
    }

    if (preg_match('#^/admin/delivery-zones/([1-9][0-9]*)$#', $path, $zoneMatch)) {
        $session = app_auth_require($db, $tenant);
        app_auth_require_permission($db, $tenant, $session, 'store.settings.manage');
        if ($method === 'PATCH') {
            app_auth_require_csrf($session);
            app_admin_delivery_zone_update($db, $tenant, $session, (int)$zoneMatch[1]);
        }
        if ($method === 'DELETE') {
            app_auth_require_csrf($session);
            app_admin_delivery_zone_disable($db, $tenant, $session, (int)$zoneMatch[1]);
        }
        header('Allow: PATCH, DELETE');
        app_json(['error' => 'روش درخواست برای این مسیر مجاز نیست.'], 405);
    }

    if ($path === '/admin/settings') {
        $session = app_auth_require($db, $tenant);
        app_auth_require_permission($db, $tenant, $session, 'store.settings.manage');
        if ($method === 'GET') app_admin_store_settings_read($db, $tenant);
        if ($method === 'PUT') {
            app_auth_require_csrf($session);
            app_admin_store_settings_update($db, $tenant, $session);
        }
        header('Allow: GET, PUT');
        app_json(['error' => 'روش درخواست برای این مسیر مجاز نیست.'], 405);
    }

    if ($method !== 'GET') {
        header('Allow: GET, POST, PATCH, DELETE');
        app_json(['error' => 'روش درخواست برای این مسیر مجاز نیست.'], 405);
    }

    if ($path === '/pickup-slots' && $method === 'GET') {
        app_public_pickup_slots($db, $tenant);
    }

    if ($path === '/store' || $path === '/') {
        $settings = app_store_settings_read($db, $tenant);
        app_json(['data' => [
            'name' => $settings['profile']['name'],
            'slug' => $tenant['slug'],
            'timezone' => $tenant['timezone'],
            'currency' => $tenant['currency'],
            'settings' => $settings,
            'fulfillment' => [
                'delivery' => $settings['checkout']['delivery_enabled'],
                'pickup' => $settings['checkout']['pickup_enabled'],
            ],
        ]]);
    }

    if ($path === '/categories') {
        $stmt = $db->prepare(
            "SELECT c.id, c.parent_id, c.title, c.slug, c.image_media_id,
                    m.storage_key AS media_storage_key, m.alt_text AS media_alt_text,
                    m.width AS media_width, m.height AS media_height
             FROM categories c
             LEFT JOIN media_assets m ON m.tenant_id = c.tenant_id AND m.id = c.image_media_id
             WHERE c.tenant_id = ? AND c.is_active = 1
             ORDER BY c.sort_order, c.title"
        );
        $stmt->execute([$tenant['id']]);
        $categories = $stmt->fetchAll();
        foreach ($categories as &$categoryRow) {
            $categoryRow['image'] = $categoryRow['media_storage_key'] === null ? null : [
                'url' => app_media_public_url((string)$categoryRow['media_storage_key']),
                'alt_text' => $categoryRow['media_alt_text'],
                'width' => $categoryRow['media_width'] === null ? null : (int)$categoryRow['media_width'],
                'height' => $categoryRow['media_height'] === null ? null : (int)$categoryRow['media_height'],
            ];
            unset($categoryRow['image_media_id'], $categoryRow['media_storage_key'],
                $categoryRow['media_alt_text'], $categoryRow['media_width'], $categoryRow['media_height']);
        }
        unset($categoryRow);
        app_json(['data' => $categories]);
    }

    if ($path === '/products') {
        $query = trim((string)($_GET['q'] ?? ''));
        $brand = trim((string)($_GET['brand'] ?? ''));
        $category = filter_input(INPUT_GET, 'category', FILTER_VALIDATE_INT);
        $minimum = filter_input(INPUT_GET, 'min_price', FILTER_VALIDATE_INT);
        $maximum = filter_input(INPUT_GET, 'max_price', FILTER_VALIDATE_INT);
        $page = max(1, min(1000, (int)($_GET['page'] ?? 1)));
        $limit = max(1, min(48, (int)($_GET['per_page'] ?? 24)));
        $sort = (string)($_GET['sort'] ?? 'title_asc');
        $inStock = isset($_GET['in_stock']) ? (string)$_GET['in_stock'] : '';
        if ($category === false || $minimum === false || $maximum === false ||
            ($category !== null && $category < 1) ||
            ($minimum !== null && $minimum < 0) ||
            ($maximum !== null && $maximum < 0) ||
            ($minimum !== null && $minimum !== false && $maximum !== null && $maximum !== false && $minimum > $maximum) ||
            mb_strlen($query) > 80 || mb_strlen($brand) > 120 ||
            !in_array($sort, ['title_asc','price_asc','price_desc','newest'], true) ||
            ($inStock !== '' && !in_array($inStock, ['0','1'], true))) {
            app_json(['error' => 'فیلترهای جست‌وجوی محصولات معتبر نیستند.'], 422);
        }

        $where = ['p.tenant_id = :tenant', 'p.is_active = 1'];
        $params = ['tenant' => $tenant['id']];
        if ($query !== '') {
            $where[] = '(p.title LIKE :q_title OR p.sku LIKE :q_sku OR p.barcode LIKE :q_barcode)';
            $search = '%' . $query . '%';
            $params['q_title'] = $search;
            $params['q_sku'] = $search;
            $params['q_barcode'] = $search;
        }
        if ($brand !== '') {
            $where[] = 'p.brand LIKE :brand';
            $params['brand'] = '%' . $brand . '%';
        }
        if ($category !== null && $category !== false) {
            $where[] = 'p.category_id = :category';
            $params['category'] = $category;
        }
        if ($minimum !== null && $minimum !== false) {
            $where[] = 'p.price_rial >= :minimum_price';
            $params['minimum_price'] = $minimum;
        }
        if ($maximum !== null && $maximum !== false) {
            $where[] = 'p.price_rial <= :maximum_price';
            $params['maximum_price'] = $maximum;
        }
        if ($inStock !== '') {
            $stockExists = "EXISTS (
                SELECT 1 FROM stock_levels filter_stock
                JOIN warehouses filter_warehouse
                  ON filter_warehouse.tenant_id=filter_stock.tenant_id
                 AND filter_warehouse.id=filter_stock.warehouse_id
                WHERE filter_stock.tenant_id=p.tenant_id
                  AND filter_stock.product_id=p.id
                  AND filter_warehouse.is_active=1
                  AND filter_stock.on_hand > filter_stock.reserved
            )";
            $where[] = $inStock === '1' ? $stockExists : 'NOT ' . $stockExists;
        }

        $sqlWhere = implode(' AND ', $where);
        $sortSql = [
            'title_asc' => 'p.title ASC,p.id DESC',
            'price_asc' => 'p.price_rial ASC,p.id DESC',
            'price_desc' => 'p.price_rial DESC,p.id DESC',
            'newest' => 'p.id DESC',
        ][$sort];
        $count = $db->prepare("SELECT COUNT(*) FROM products p WHERE {$sqlWhere}");
        $count->execute($params);
        $stmt = $db->prepare(
            "SELECT p.id, p.sku, p.barcode, p.title, p.brand, p.description, p.unit_label, p.price_rial,
                    p.compare_at_rial, p.image_media_id, p.is_weighted, c.slug AS category_slug,
                    COALESCE((SELECT SUM(sl.on_hand-sl.reserved)
                              FROM stock_levels sl
                              JOIN warehouses w ON w.tenant_id=sl.tenant_id AND w.id=sl.warehouse_id
                              WHERE sl.tenant_id=p.tenant_id AND sl.product_id=p.id AND w.is_active=1),0) AS available_quantity,
                    m.storage_key AS media_storage_key, m.alt_text AS media_alt_text,
                    m.width AS media_width, m.height AS media_height
             FROM products p
             LEFT JOIN media_assets m ON m.tenant_id = p.tenant_id AND m.id = p.image_media_id
             LEFT JOIN categories c ON c.tenant_id = p.tenant_id AND c.id = p.category_id AND c.is_active = 1
             WHERE {$sqlWhere}
             ORDER BY {$sortSql} LIMIT {$limit} OFFSET " . (($page - 1) * $limit)
        );
        $stmt->execute($params);
        $products = $stmt->fetchAll();
        foreach ($products as &$productRow) {
            $productRow['image'] = $productRow['media_storage_key'] === null ? null : [
                'url' => app_media_public_url((string)$productRow['media_storage_key']),
                'alt_text' => $productRow['media_alt_text'],
                'width' => $productRow['media_width'] === null ? null : (int)$productRow['media_width'],
                'height' => $productRow['media_height'] === null ? null : (int)$productRow['media_height'],
            ];
            $productRow['available_quantity'] = (int)$productRow['available_quantity'];
            $productRow['in_stock'] = $productRow['available_quantity'] > 0;
            $productRow['is_weighted'] = (bool)$productRow['is_weighted'];
            unset($productRow['image_media_id'], $productRow['media_storage_key'],
                $productRow['media_alt_text'], $productRow['media_width'], $productRow['media_height']);
        }
        unset($productRow);
        app_json(['data' => $products, 'meta' => [
            'page' => $page, 'per_page' => $limit, 'total' => (int)$count->fetchColumn(),
            'sort' => $sort,
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
                    target.title AS target_scene_title,
                    category.id AS target_category_id, category.title AS target_category_title, category.slug AS target_category_slug
             FROM scene_hotspots h
             LEFT JOIN products p
               ON p.tenant_id = h.tenant_id AND p.id = h.product_id AND p.is_active = 1
             LEFT JOIN store_scenes target
               ON target.tenant_id = h.tenant_id AND target.id = h.target_scene_id AND target.is_published = 1
             LEFT JOIN categories category
               ON category.tenant_id = h.tenant_id
              AND category.id = CAST(JSON_UNQUOTE(JSON_EXTRACT(h.payload, '$.category_id')) AS UNSIGNED)
              AND category.is_active = 1
             WHERE h.tenant_id = ?
               AND h.scene_id IN (SELECT id FROM store_scenes WHERE tenant_id = ? AND is_published = 1)
               AND (h.action <> 'product' OR p.id IS NOT NULL)
               AND (h.action <> 'navigate' OR target.id IS NOT NULL)
               AND (h.action <> 'category' OR category.id IS NOT NULL)
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
            $categoryData = $hotspot['target_category_id'] === null ? null : [
                'id' => (int)$hotspot['target_category_id'],
                'title' => $hotspot['target_category_title'],
                'slug' => $hotspot['target_category_slug'],
            ];
            $hotspotsByScene[(int)$hotspot['scene_id']][] = [
                'id' => (int)$hotspot['id'],
                'label' => $hotspot['label'],
                'x' => (float)$hotspot['x'],
                'y' => (float)$hotspot['y'],
                'action' => $hotspot['action'],
                'product' => $productData,
                'target_scene' => $targetData,
                'category' => $categoryData,
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
