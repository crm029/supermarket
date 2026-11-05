<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/policies.php';

function app_admin_orders_list(PDO $db, array $tenant): never {
    $status = trim((string)($_GET['status'] ?? ''));
    $allowed = ['pending_payment','paid','accepted','picking','packed','ready_pickup','dispatched','delivered','cancelled','refunded'];
    $where = 'o.tenant_id = ?';
    $params = [$tenant['id']];
    if ($status !== '') {
        if (!in_array($status, $allowed, true)) app_json(['error' => 'وضعیت سفارش معتبر نیست.'], 422);
        $where .= ' AND o.status = ?';
        $params[] = $status;
    }
    $stmt = $db->prepare(
        "SELECT o.id, o.order_number, o.status, o.fulfillment, o.subtotal_rial, o.delivery_fee_rial,
                o.total_rial, o.created_at, o.customer_note, u.full_name AS customer_name, u.mobile AS customer_mobile,
                ca.recipient AS delivery_recipient, ca.mobile AS delivery_mobile, ca.province, ca.city, ca.address AS delivery_address, ca.postal_code,
                COUNT(oi.id) AS item_count
         FROM orders o
         JOIN users u ON u.tenant_id = o.tenant_id AND u.id = o.user_id
         LEFT JOIN customer_addresses ca ON ca.tenant_id = o.tenant_id AND ca.id = o.address_id
         LEFT JOIN order_items oi ON oi.tenant_id = o.tenant_id AND oi.order_id = o.id
         WHERE {$where}
         GROUP BY o.id, o.order_number, o.status, o.fulfillment, o.subtotal_rial,
                  o.delivery_fee_rial, o.total_rial, o.created_at, o.customer_note, u.full_name, u.mobile,
                  ca.recipient, ca.mobile, ca.province, ca.city, ca.address, ca.postal_code
         ORDER BY o.id DESC LIMIT 200"
    );
    $stmt->execute($params);
    app_json(['data' => $stmt->fetchAll()]);
}

function app_admin_order_transition(PDO $db, array $tenant, array $session, int $orderId): never {
    $body = app_auth_request_body();
    $next = trim((string)($body['status'] ?? ''));
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('SELECT id, status, fulfillment FROM orders WHERE tenant_id = ? AND id = ? FOR UPDATE');
        $stmt->execute([$tenant['id'], $orderId]);
        $order = $stmt->fetch();
        if (!$order) {
            $db->rollBack();
            app_json(['error' => 'سفارش پیدا نشد.'], 404);
        }
        $transitions = [
            'paid' => ['accepted'],
            'accepted' => ['picking'],
            'picking' => ['packed'],
            'packed' => [$order['fulfillment'] === 'pickup' ? 'ready_pickup' : 'dispatched'],
            'ready_pickup' => ['delivered'],
            'dispatched' => ['delivered'],
        ];
        if (!in_array($next, $transitions[$order['status']] ?? [], true)) {
            $db->rollBack();
            app_json(['error' => 'این تغییر وضعیت در گردش کار سفارش مجاز نیست.'], 409);
        }
        $update = $db->prepare('UPDATE orders SET status = ? WHERE tenant_id = ? AND id = ?');
        $update->execute([$next, $tenant['id'], $orderId]);
        app_audit_log($db, (int)$tenant['id'], (int)$session['id'], 'order.status.' . $next, 'order', $orderId, [
            'previous_status' => $order['status'],
            'next_status' => $next,
        ]);
        $db->commit();
        app_json(['data' => ['id' => $orderId, 'status' => $next], 'message' => 'وضعیت سفارش به‌روزرسانی شد.']);
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}

function app_customer_order_detail(PDO $db, array $tenant, array $session, int $orderId): never {
    $stmt = $db->prepare(
        'SELECT id, order_number, status, fulfillment, subtotal_rial, discount_rial,
                delivery_fee_rial, total_rial, customer_note, created_at
         FROM orders WHERE tenant_id = ? AND user_id = ? AND id = ? LIMIT 1'
    );
    $stmt->execute([$tenant['id'], $session['id'], $orderId]);
    $order = $stmt->fetch();
    if (!$order) app_json(['error' => 'سفارش پیدا نشد.'], 404);
    $items = $db->prepare(
        'SELECT product_title_snapshot AS title, unit_price_rial, quantity, line_total_rial
         FROM order_items WHERE tenant_id = ? AND order_id = ? ORDER BY id'
    );
    $items->execute([$tenant['id'], $orderId]);
    $order['items'] = $items->fetchAll();
    app_json(['data' => $order]);
}

function app_customer_cancel_order(PDO $db, array $tenant, array $session, int $orderId): never {
    $db->beginTransaction();
    try {
        $stmt = $db->prepare(
            'SELECT id, status FROM orders WHERE tenant_id = ? AND user_id = ? AND id = ? FOR UPDATE'
        );
        $stmt->execute([$tenant['id'], $session['id'], $orderId]);
        $order = $stmt->fetch();
        if (!$order) {
            $db->rollBack();
            app_json(['error' => 'سفارش پیدا نشد.'], 404);
        }
        if ($order['status'] !== 'pending_payment') {
            $db->rollBack();
            app_json(['error' => 'پس از شروع پردازش یا پرداخت، لغو آنلاین این سفارش ممکن نیست.'], 409);
        }
        $reservations = $db->prepare(
            "SELECT id, warehouse_id, product_id, quantity
             FROM stock_reservations WHERE tenant_id = ? AND order_id = ? AND status = 'active'
             ORDER BY id FOR UPDATE"
        );
        $reservations->execute([$tenant['id'], $orderId]);
        $rows = $reservations->fetchAll();
        $releaseStock = $db->prepare(
            'UPDATE stock_levels SET reserved = reserved - ? WHERE tenant_id = ? AND warehouse_id = ? AND product_id = ? AND reserved >= ?'
        );
        $release = $db->prepare("UPDATE stock_reservations SET status = 'released' WHERE id = ? AND status = 'active'");
        $movement = $db->prepare("INSERT INTO stock_movements (tenant_id,warehouse_id,product_id,actor_user_id,movement_type,quantity,reference_type,reference_id,reason) VALUES (?,?,?,?,'release',?,'order',?,'لغو سفارش توسط مشتری')");
        foreach ($rows as $row) {
            $qty = (int)$row['quantity'];
            $releaseStock->execute([$qty,$tenant['id'],$row['warehouse_id'],$row['product_id'],$qty]);
            if ($releaseStock->rowCount() !== 1) throw new RuntimeException('Unable to release order stock reservation.');
            $release->execute([$row['id']]);
            $movement->execute([$tenant['id'],$row['warehouse_id'],$row['product_id'],$session['id'],-$qty,$orderId]);
        }
        $update = $db->prepare("UPDATE orders SET status = 'cancelled' WHERE tenant_id = ? AND id = ? AND status = 'pending_payment'");
        $update->execute([$tenant['id'], $orderId]);
        $db->commit();
        app_json(['message' => 'سفارش لغو شد و موجودی رزروشده آزاد شد.']);
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}
