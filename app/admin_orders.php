<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/delivery_tracking.php';
require_once __DIR__ . '/policies.php';
require_once __DIR__ . '/stock_batches.php';

function app_admin_orders_list(PDO $db, array $tenant): never {
    $status = trim((string)($_GET['status'] ?? ''));
    $allowed = ['pending_payment','paid','accepted','picking','packed','ready_pickup','dispatched','out_for_delivery','delivered','cancelled','refunded'];
    $where = 'o.tenant_id = ?';
    $params = [$tenant['id']];
    if ($status !== '') {
        if (!in_array($status, $allowed, true)) app_json(['error' => 'وضعیت سفارش معتبر نیست.'], 422);
        $where .= ' AND o.status = ?';
        $params[] = $status;
    }
    $stmt = $db->prepare(
        "SELECT o.id, o.order_number, o.status, o.fulfillment, o.subtotal_rial, o.discount_rial, o.delivery_fee_rial,
                o.total_rial, o.created_at, o.customer_note, cp.code AS coupon_code, u.full_name AS customer_name, u.mobile AS customer_mobile,
                ca.recipient AS delivery_recipient, ca.mobile AS delivery_mobile, ca.province, ca.city, ca.address AS delivery_address, ca.postal_code,
                ps.title AS pickup_slot_title, ps.starts_at AS pickup_slot_starts_at, ps.ends_at AS pickup_slot_ends_at,
                COUNT(oi.id) AS item_count
         FROM orders o
         JOIN users u ON u.tenant_id = o.tenant_id AND u.id = o.user_id
         LEFT JOIN customer_addresses ca ON ca.tenant_id = o.tenant_id AND ca.id = o.address_id
         LEFT JOIN coupons cp ON cp.tenant_id = o.tenant_id AND cp.id = o.coupon_id
         LEFT JOIN pickup_slots ps ON ps.tenant_id = o.tenant_id AND ps.id = o.pickup_slot_id
         LEFT JOIN order_items oi ON oi.tenant_id = o.tenant_id AND oi.order_id = o.id
         WHERE {$where}
         GROUP BY o.id, o.order_number, o.status, o.fulfillment, o.subtotal_rial, o.discount_rial,
                  o.delivery_fee_rial, o.total_rial, o.created_at, o.customer_note, cp.code, u.full_name, u.mobile,
                  ca.recipient, ca.mobile, ca.province, ca.city, ca.address, ca.postal_code,
                  ps.title, ps.starts_at, ps.ends_at
         ORDER BY o.id DESC LIMIT 200"
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $timezone = app_pickup_timezone($tenant);
    $utc = new DateTimeZone('UTC');
    foreach ($rows as &$row) {
        if (!empty($row['pickup_slot_starts_at'])) {
            $row['pickup_slot_starts_at_label'] = (new DateTimeImmutable((string)$row['pickup_slot_starts_at'], $utc))->setTimezone($timezone)->format('Y/m/d H:i');
            $row['pickup_slot_ends_at_label'] = (new DateTimeImmutable((string)$row['pickup_slot_ends_at'], $utc))->setTimezone($timezone)->format('Y/m/d H:i');
        }
    }
    unset($row);
    app_json(['data' => $rows]);
}

function app_admin_order_transition(PDO $db, array $tenant, array $session, int $orderId): never {
    $body = app_auth_request_body();
    $next = trim((string)($body['status'] ?? ''));
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('SELECT id, user_id, order_number, status, fulfillment FROM orders WHERE tenant_id = ? AND id = ? FOR UPDATE');
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
            'dispatched' => ['out_for_delivery'],
            'out_for_delivery' => ['delivered'],
        ];
        if (!in_array($next, $transitions[$order['status']] ?? [], true)) {
            $db->rollBack();
            app_json(['error' => 'این تغییر وضعیت در گردش کار سفارش مجاز نیست.'], 409);
        }
        if ($order['status'] === 'picking' && $next === 'packed') {
            $activePicker=$db->prepare(
                "SELECT COUNT(*) FROM order_assignments
                 WHERE tenant_id=? AND order_id=? AND assignment_type='picker' AND status='active'"
            );
            $activePicker->execute([$tenant['id'],$orderId]);
            if ((int)$activePicker->fetchColumn() > 0) {
                $db->rollBack();
                app_json(['error'=>'ابتدا نیروی جمع‌آوری باید پایان کار خود را ثبت کند.'],409);
            }
        }
        if ($order['fulfillment'] === 'pickup' && $next === 'delivered') {
            $db->rollBack();
            app_json(['error'=>'تحویل حضوری باید توسط مسئول تحویل و از مرکز وظایف کارکنان ثبت شود.'],409);
        }
        if ($order['fulfillment'] === 'delivery' && $next === 'out_for_delivery') {
            $activeCourier=$db->prepare(
                "SELECT COUNT(*) FROM order_assignments
                 WHERE tenant_id=? AND order_id=? AND assignment_type='courier' AND status='active'"
            );
            $activeCourier->execute([$tenant['id'],$orderId]);
            if ((int)$activeCourier->fetchColumn() !== 1) {
                $db->rollBack();
                app_json(['error'=>'پیش از شروع ارسال، باید دقیقاً یک پیک فعال به سفارش تخصیص داده شود.'],409);
            }
        }
        $update = $db->prepare('UPDATE orders SET status = ? WHERE tenant_id = ? AND id = ?');
        $update->execute([$next, $tenant['id'], $orderId]);
        if ($order['fulfillment'] === 'delivery' && in_array($next,['out_for_delivery','delivered'],true)) {
            $assignmentQuery=$db->prepare(
                "SELECT id FROM order_assignments
                 WHERE tenant_id=? AND order_id=? AND assignment_type='courier' AND status='active'
                 ORDER BY id DESC LIMIT 1 FOR UPDATE"
            );
            $assignmentQuery->execute([$tenant['id'],$orderId]);
            $assignmentId=$assignmentQuery->fetchColumn();
            if ($next === 'delivered' && $assignmentId) {
                $completeAssignment=$db->prepare(
                    "UPDATE order_assignments SET status='completed',completed_at=UTC_TIMESTAMP()
                     WHERE tenant_id=? AND order_id=? AND id=? AND status='active'"
                );
                $completeAssignment->execute([$tenant['id'],$orderId,$assignmentId]);
            }
            app_delivery_event_create(
                $db,(int)$tenant['id'],$orderId,$assignmentId?(int)$assignmentId:null,(int)$session['id'],
                $next,$next==='delivered'?'تحویل توسط مدیر فروشگاه ثبت شد.':'شروع ارسال توسط مدیر فروشگاه ثبت شد.'
            );
        }
        app_audit_log($db, (int)$tenant['id'], (int)$session['id'], 'order.status.' . $next, 'order', $orderId, [
            'previous_status' => $order['status'],
            'next_status' => $next,
        ]);
        $statusLabels=[
            'accepted'=>'پذیرفته‌شده',
            'picking'=>'در حال جمع‌آوری',
            'packed'=>'بسته‌بندی‌شده',
            'ready_pickup'=>'آماده تحویل حضوری',
            'dispatched'=>'آماده ارسال',
            'out_for_delivery'=>'در مسیر ارسال',
            'delivered'=>'تحویل‌شده',
        ];
        $statusLabel=$statusLabels[$next]??$next;
        app_notification_create($db,(int)$tenant['id'],(int)$order['user_id'],'order-status-'.$orderId.'-'.$next,'order',
            'وضعیت سفارش به‌روزرسانی شد','سفارش '.$order['order_number'].' اکنون '.$statusLabel.' است.','order',$orderId);
        $db->commit();
        app_json(['data' => ['id' => $orderId, 'status' => $next], 'message' => 'وضعیت سفارش به‌روزرسانی شد.']);
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}

function app_customer_order_detail(PDO $db, array $tenant, array $session, int $orderId): never {
    $stmt = $db->prepare(
        'SELECT o.id, o.order_number, o.status, o.fulfillment, o.subtotal_rial, o.discount_rial,
                o.delivery_fee_rial, o.total_rial, o.customer_note, o.created_at, cp.code AS coupon_code,
                ps.title AS pickup_slot_title, ps.starts_at AS pickup_slot_starts_at, ps.ends_at AS pickup_slot_ends_at
         FROM orders o
         LEFT JOIN coupons cp ON cp.tenant_id=o.tenant_id AND cp.id=o.coupon_id
         LEFT JOIN pickup_slots ps ON ps.tenant_id=o.tenant_id AND ps.id=o.pickup_slot_id
         WHERE o.tenant_id = ? AND o.user_id = ? AND o.id = ? LIMIT 1'
    );
    $stmt->execute([$tenant['id'], $session['id'], $orderId]);
    $order = $stmt->fetch();
    if (!$order) app_json(['error' => 'سفارش پیدا نشد.'], 404);
    if (!empty($order['pickup_slot_starts_at'])) {
        $timezone = app_pickup_timezone($tenant);
        $utc = new DateTimeZone('UTC');
        $order['pickup_slot_starts_at_label'] = (new DateTimeImmutable((string)$order['pickup_slot_starts_at'], $utc))->setTimezone($timezone)->format('Y/m/d H:i');
        $order['pickup_slot_ends_at_label'] = (new DateTimeImmutable((string)$order['pickup_slot_ends_at'], $utc))->setTimezone($timezone)->format('Y/m/d H:i');
    }
    $items = $db->prepare(
        'SELECT product_title_snapshot AS title, unit_price_rial, quantity, line_total_rial
         FROM order_items WHERE tenant_id = ? AND order_id = ? ORDER BY id'
    );
    $items->execute([$tenant['id'], $orderId]);
    $order['items'] = $items->fetchAll();
    $deliveryEvents = $order['fulfillment'] === 'delivery'
        ? app_delivery_event_list($db,(int)$tenant['id'],$orderId)
        : [];
    $order['delivery_events'] = array_map(
        static fn(array $event): array => ['event_type'=>$event['event_type'],'created_at'=>$event['created_at']],
        $deliveryEvents
    );
    app_json(['data' => $order]);
}

function app_customer_cancel_order(PDO $db, array $tenant, array $session, int $orderId): never {
    $db->beginTransaction();
    try {
        $stmt = $db->prepare(
            'SELECT id, order_number, status FROM orders WHERE tenant_id = ? AND user_id = ? AND id = ? FOR UPDATE'
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
            app_stock_batch_release_reservation($db,(int)$tenant['id'],(int)$row['id'],'released');
            $release->execute([$row['id']]);
            $movement->execute([$tenant['id'],$row['warehouse_id'],$row['product_id'],$session['id'],-$qty,$orderId]);
        }
        $update = $db->prepare("UPDATE orders SET status = 'cancelled' WHERE tenant_id = ? AND id = ? AND status = 'pending_payment'");
        $update->execute([$tenant['id'], $orderId]);
        app_notification_notify_permission($db,(int)$tenant['id'],'orders.manage','order-cancelled-'.$orderId,'order',
            'سفارش توسط مشتری لغو شد','سفارش '.$order['order_number'].' توسط مشتری لغو شد و موجودی رزروشده آزاد شد.',
            'order',$orderId);
        $db->commit();
        app_json(['message' => 'سفارش لغو شد و موجودی رزروشده آزاد شد.']);
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}
