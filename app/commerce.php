<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';

function app_commerce_body(): array {
    return app_auth_request_body();
}

function app_commerce_lines(array $body): array {
    $items = $body['items'] ?? null;
    if (!is_array($items) || !array_is_list($items) || count($items) < 1 || count($items) > 50) {
        app_json(['error' => 'سبد خرید باید بین ۱ تا ۵۰ قلم کالا داشته باشد.'], 422);
    }
    $lines = [];
    foreach ($items as $item) {
        if (!is_array($item)) app_json(['error' => 'ساختار اقلام سبد خرید معتبر نیست.'], 422);
        $id = filter_var($item['product_id'] ?? null, FILTER_VALIDATE_INT);
        $qty = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1 || $qty === false || $qty < 1 || $qty > 99) {
            app_json(['error' => 'شناسه یا تعداد یکی از کالاها معتبر نیست.'], 422);
        }
        if (isset($lines[$id])) {
            $lines[$id] += $qty;
        } else {
            $lines[$id] = $qty;
        }
        if ($lines[$id] > 99) app_json(['error' => 'تعداد هر کالا حداکثر ۹۹ عدد است.'], 422);
    }
    return $lines;
}

/**
 * Recalculate every line against the current tenant catalog and available stock.
 * Client-submitted prices are deliberately ignored.
 */
function app_commerce_quote(PDO $db, int $tenantId, array $lines, bool $requireStock = true): array {
    $ids = array_keys($lines);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare(
        "SELECT p.id, p.title, p.sku, p.unit_label, p.price_rial, p.is_weighted,
                COALESCE(SUM(CASE WHEN w.is_active = 1 THEN sl.on_hand - sl.reserved ELSE 0 END), 0) AS available_quantity
         FROM products p
         LEFT JOIN stock_levels sl ON sl.tenant_id = p.tenant_id AND sl.product_id = p.id
         LEFT JOIN warehouses w ON w.tenant_id = sl.tenant_id AND w.id = sl.warehouse_id AND w.is_active = 1
         WHERE p.tenant_id = ? AND p.is_active = 1 AND p.id IN ({$placeholders})
         GROUP BY p.id, p.title, p.sku, p.unit_label, p.price_rial, p.is_weighted"
    );
    $stmt->execute(array_merge([$tenantId], $ids));
    $found = [];
    foreach ($stmt->fetchAll() as $row) $found[(int)$row['id']] = $row;

    $result = [];
    $subtotal = 0;
    foreach ($lines as $id => $quantity) {
        if (!isset($found[$id])) app_json(['error' => 'یکی از کالاها دیگر در این فروشگاه موجود نیست.'], 409);
        $row = $found[$id];
        $available = (int)$row['available_quantity'];
        if ($requireStock && $available < $quantity) {
            app_json(['error' => 'موجودی «' . $row['title'] . '» برای تعداد انتخاب‌شده کافی نیست.'], 409);
        }
        $price = (int)$row['price_rial'];
        if ($price > 0 && $quantity > intdiv(PHP_INT_MAX, $price)) {
            app_json(['error' => 'مبلغ سبد خرید بیش از حد مجاز است.'], 422);
        }
        $lineTotal = $price * $quantity;
        if ($subtotal > PHP_INT_MAX - $lineTotal) app_json(['error' => 'مبلغ سبد خرید بیش از حد مجاز است.'], 422);
        $subtotal += $lineTotal;
        $result[] = [
            'product_id' => (int)$id,
            'sku' => $row['sku'],
            'title' => $row['title'],
            'unit_label' => $row['unit_label'],
            'unit_price_rial' => $price,
            'quantity' => $quantity,
            'line_total_rial' => $lineTotal,
            'available_quantity' => $available,
            'is_weighted' => (bool)$row['is_weighted'],
        ];
    }
    return ['items' => $result, 'subtotal_rial' => $subtotal, 'delivery_fee_rial' => 0, 'discount_rial' => 0,
        'total_rial' => $subtotal, 'currency' => 'IRR'];
}

function app_commerce_public_quote(PDO $db, array $tenant): never {
    $body = app_commerce_body();
    $lines = app_commerce_lines($body);
    app_json(['data' => app_commerce_quote($db, (int)$tenant['id'], $lines)]);
}

function app_commerce_list_addresses(PDO $db, array $tenant, array $session): never {
    $stmt = $db->prepare(
        'SELECT id, title, recipient, mobile, province, city, address, postal_code, latitude, longitude
         FROM customer_addresses WHERE tenant_id = ? AND user_id = ? ORDER BY id DESC'
    );
    $stmt->execute([$tenant['id'], $session['id']]);
    app_json(['data' => $stmt->fetchAll()]);
}

function app_commerce_create_address(PDO $db, array $tenant, array $session): never {
    $body = app_commerce_body();
    $title = trim((string)($body['title'] ?? ''));
    $recipient = trim((string)($body['recipient'] ?? $session['full_name']));
    $mobile = app_auth_mobile((string)($body['mobile'] ?? $session['mobile']));
    $address = trim((string)($body['address'] ?? ''));
    $province = trim((string)($body['province'] ?? ''));
    $city = trim((string)($body['city'] ?? ''));
    $postal = preg_replace('/\s+/', '', (string)($body['postal_code'] ?? ''));
    if ($title === '' || mb_strlen($title) > 80 || $recipient === '' || mb_strlen($recipient) > 160 ||
        $mobile === null || $address === '' || mb_strlen($address) > 2000 ||
        mb_strlen($province) > 100 || mb_strlen($city) > 100 ||
        ($postal !== '' && !preg_match('/^[0-9]{10}$/', $postal))) {
        app_json(['error' => 'اطلاعات نشانی کامل یا معتبر نیست.'], 422);
    }
    $lat = $body['latitude'] ?? null;
    $lng = $body['longitude'] ?? null;
    if (($lat === null) !== ($lng === null) ||
        ($lat !== null && (!is_numeric($lat) || (float)$lat < -90 || (float)$lat > 90)) ||
        ($lng !== null && (!is_numeric($lng) || (float)$lng < -180 || (float)$lng > 180))) {
        app_json(['error' => 'مختصات نشانی معتبر نیست.'], 422);
    }
    $stmt = $db->prepare(
        'INSERT INTO customer_addresses
         (tenant_id,user_id,title,recipient,mobile,province,city,address,postal_code,latitude,longitude)
         VALUES (?,?,?,?,?,?,?,?,?,?,?)'
    );
    $stmt->execute([
        $tenant['id'], $session['id'], $title, $recipient, $mobile,
        $province === '' ? null : $province, $city === '' ? null : $city,
        $address, $postal === '' ? null : $postal,
        $lat === null ? null : (float)$lat, $lng === null ? null : (float)$lng,
    ]);
    app_json(['data' => ['id' => (int)$db->lastInsertId()], 'message' => 'نشانی ذخیره شد.'], 201);
}

function app_commerce_create_order(PDO $db, array $tenant, array $session): never {
    $body = app_commerce_body();
    $lines = app_commerce_lines($body);
    $fulfillment = (string)($body['fulfillment'] ?? 'delivery');
    if (!in_array($fulfillment, ['delivery', 'pickup'], true)) {
        app_json(['error' => 'روش دریافت سفارش معتبر نیست.'], 422);
    }
    $addressId = null;
    if ($fulfillment === 'delivery') {
        $addressId = filter_var($body['address_id'] ?? null, FILTER_VALIDATE_INT);
        if ($addressId === false || $addressId < 1) app_json(['error' => 'برای ارسال، یک نشانی انتخاب کنید.'], 422);
    }
    $note = trim((string)($body['customer_note'] ?? ''));
    if (mb_strlen($note) > 1000) app_json(['error' => 'یادداشت سفارش بیش از حد طولانی است.'], 422);

    $db->beginTransaction();
    try {
        if ($addressId !== null) {
            $address = $db->prepare('SELECT id FROM customer_addresses WHERE tenant_id = ? AND user_id = ? AND id = ? FOR UPDATE');
            $address->execute([$tenant['id'], $session['id'], $addressId]);
            if (!$address->fetchColumn()) {
                $db->rollBack();
                app_json(['error' => 'نشانی انتخاب‌شده پیدا نشد.'], 404);
            }
        }

        // Lock product rows first, then recalculate prices and stock inside the transaction.
        $ids = array_keys($lines);
        sort($ids, SORT_NUMERIC);
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $locks = $db->prepare("SELECT id FROM products WHERE tenant_id = ? AND is_active = 1 AND id IN ({$marks}) ORDER BY id FOR UPDATE");
        $locks->execute(array_merge([$tenant['id']], $ids));
        if (count($locks->fetchAll()) !== count($ids)) {
            $db->rollBack();
            app_json(['error' => 'یکی از کالاها دیگر قابل سفارش نیست.'], 409);
        }
        $quote = app_commerce_quote($db, (int)$tenant['id'], $lines);
        $orderNumber = 'SM' . gmdate('ymdHis') . strtoupper(bin2hex(random_bytes(3)));
        $insertOrder = $db->prepare(
            "INSERT INTO orders
             (tenant_id,user_id,address_id,order_number,status,fulfillment,subtotal_rial,discount_rial,delivery_fee_rial,total_rial,customer_note)
             VALUES (?,?,?,?,'pending_payment',?,?,0,0,?,?)"
        );
        $insertOrder->execute([
            $tenant['id'], $session['id'], $addressId, $orderNumber, $fulfillment,
            $quote['subtotal_rial'], $quote['total_rial'], $note === '' ? null : $note,
        ]);
        $orderId = (int)$db->lastInsertId();

        $insertLine = $db->prepare(
            'INSERT INTO order_items (tenant_id,order_id,product_id,product_title_snapshot,unit_price_rial,quantity,line_total_rial)
             VALUES (?,?,?,?,?,?,?)'
        );
        $reserve = $db->prepare(
            "INSERT INTO stock_reservations (tenant_id,order_id,warehouse_id,product_id,quantity,status,expires_at)
             VALUES (?,?,?,?,?,'active',DATE_ADD(UTC_TIMESTAMP(), INTERVAL 20 MINUTE))"
        );
        $reservationMovement = $db->prepare(
            "INSERT INTO stock_movements (tenant_id,warehouse_id,product_id,actor_user_id,movement_type,quantity,reference_type,reference_id,reason) VALUES (?,?,?,?,'reservation',?,'order',?,'رزرو برای پرداخت')"
        );
        $updateStock = $db->prepare(
            'UPDATE stock_levels SET reserved = reserved + ? WHERE tenant_id = ? AND warehouse_id = ? AND product_id = ? AND on_hand - reserved >= ?'
        );
        foreach ($quote['items'] as $item) {
            $insertLine->execute([
                $tenant['id'], $orderId, $item['product_id'], $item['title'],
                $item['unit_price_rial'], $item['quantity'], $item['line_total_rial'],
            ]);
            $remaining = (int)$item['quantity'];
            $stockRows = $db->prepare(
                'SELECT sl.warehouse_id, sl.on_hand, sl.reserved
                 FROM stock_levels sl JOIN warehouses w ON w.id = sl.warehouse_id AND w.tenant_id = sl.tenant_id
                 WHERE sl.tenant_id = ? AND sl.product_id = ? AND w.is_active = 1 AND sl.on_hand > sl.reserved
                 ORDER BY sl.on_hand - sl.reserved DESC, sl.warehouse_id FOR UPDATE'
            );
            $stockRows->execute([$tenant['id'], $item['product_id']]);
            foreach ($stockRows->fetchAll() as $stock) {
                if ($remaining === 0) break;
                $available = (int)$stock['on_hand'] - (int)$stock['reserved'];
                $take = min($remaining, $available);
                $updateStock->execute([$take, $tenant['id'], $stock['warehouse_id'], $item['product_id'], $take]);
                if ($updateStock->rowCount() !== 1) throw new RuntimeException('Stock changed during reservation.');
                $reserve->execute([$tenant['id'], $orderId, $stock['warehouse_id'], $item['product_id'], $take]);
                $reservationMovement->execute([$tenant['id'],$stock['warehouse_id'],$item['product_id'],$session['id'],$take,$orderId]);
                $remaining -= $take;
            }
            if ($remaining > 0) {
                $db->rollBack();
                app_json(['error' => 'موجودی یکی از کالاها هنگام ثبت سفارش تغییر کرد. دوباره تلاش کنید.'], 409);
            }
        }
        $db->commit();
        app_json(['data' => [
            'id' => $orderId, 'order_number' => $orderNumber, 'status' => 'pending_payment',
            'fulfillment' => $fulfillment, 'items' => $quote['items'],
            'subtotal_rial' => $quote['subtotal_rial'], 'delivery_fee_rial' => 0,
            'total_rial' => $quote['total_rial'], 'reservation_expires_in_seconds' => 1200,
            'payment_required' => true,
        ], 'message' => 'سفارش ثبت اولیه شد؛ برای نهایی‌شدن باید پرداخت تأیید شود.'], 201);
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}

function app_commerce_order_history(PDO $db, array $tenant, array $session): never {
    $stmt = $db->prepare(
        'SELECT id, order_number, status, fulfillment, subtotal_rial, discount_rial,
                delivery_fee_rial, total_rial, created_at
         FROM orders WHERE tenant_id = ? AND user_id = ? ORDER BY id DESC LIMIT 100'
    );
    $stmt->execute([$tenant['id'], $session['id']]);
    app_json(['data' => $stmt->fetchAll()]);
}
