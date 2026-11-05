<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/commerce.php';

function app_customer_cart_id(PDO $db, int $tenantId, int $userId): int {
    $insert = $db->prepare('INSERT IGNORE INTO customer_carts (tenant_id,user_id) VALUES (?,?)');
    $insert->execute([$tenantId, $userId]);
    $find = $db->prepare('SELECT id FROM customer_carts WHERE tenant_id = ? AND user_id = ? LIMIT 1');
    $find->execute([$tenantId, $userId]);
    $id = $find->fetchColumn();
    if (!$id) throw new RuntimeException('Unable to initialize customer cart.');
    return (int)$id;
}

function app_customer_cart_read(PDO $db, array $tenant, array $session): never {
    $cartId = app_customer_cart_id($db, (int)$tenant['id'], (int)$session['id']);
    $stmt = $db->prepare(
        "SELECT ci.product_id, ci.quantity, p.title, p.sku, p.unit_label, p.price_rial,
                COALESCE((SELECT SUM(sl.on_hand - sl.reserved)
                          FROM stock_levels sl JOIN warehouses w
                            ON w.tenant_id = sl.tenant_id AND w.id = sl.warehouse_id
                          WHERE sl.tenant_id = ci.tenant_id AND sl.product_id = ci.product_id
                            AND w.is_active = 1),0) AS available_quantity
         FROM customer_cart_items ci
         JOIN products p ON p.tenant_id = ci.tenant_id AND p.id = ci.product_id
         WHERE ci.tenant_id = ? AND ci.cart_id = ? AND p.is_active = 1
         ORDER BY ci.id"
    );
    $stmt->execute([$tenant['id'], $cartId]);
    $items = $stmt->fetchAll();
    foreach ($items as &$item) {
        $item['product_id'] = (int)$item['product_id'];
        $item['quantity'] = (int)$item['quantity'];
        $item['price_rial'] = (int)$item['price_rial'];
        $item['available_quantity'] = (int)$item['available_quantity'];
    }
    unset($item);
    app_json(['data' => ['items' => $items, 'item_count' => array_sum(array_column($items, 'quantity'))]]);
}

function app_customer_cart_replace(PDO $db, array $tenant, array $session): never {
    $body = app_auth_request_body();
    $lines = app_commerce_lines($body);
    if ($lines !== []) {
        // Validate that every item is active and belongs to this tenant; prices and stock are not trusted here.
        app_commerce_quote($db, (int)$tenant['id'], $lines, false);
    }

    $db->beginTransaction();
    try {
        $cartId = app_customer_cart_id($db, (int)$tenant['id'], (int)$session['id']);
        $delete = $db->prepare('DELETE FROM customer_cart_items WHERE tenant_id = ? AND cart_id = ?');
        $delete->execute([$tenant['id'], $cartId]);
        if ($lines !== []) {
            $insert = $db->prepare(
                'INSERT INTO customer_cart_items (tenant_id,cart_id,product_id,quantity) VALUES (?,?,?,?)'
            );
            foreach ($lines as $productId => $quantity) {
                $insert->execute([$tenant['id'], $cartId, $productId, $quantity]);
            }
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }

    app_json(['data' => ['item_count' => array_sum($lines)], 'message' => 'سبد خرید ذخیره شد.']);
}
