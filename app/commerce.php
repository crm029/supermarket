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
         FROM customer_addresses WHERE tenant_id = ? AND user_id = ? AND is_active = 1 ORDER BY id DESC'
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

function app_commerce_update_address(PDO $db, array $tenant, array $session, int $addressId): never {
    $body = app_commerce_body();
    $find = $db->prepare(
        'SELECT title,recipient,mobile,province,city,address,postal_code,latitude,longitude
         FROM customer_addresses WHERE tenant_id = ? AND user_id = ? AND id = ? AND is_active = 1 LIMIT 1'
    );
    $find->execute([$tenant['id'], $session['id'], $addressId]);
    $current = $find->fetch(PDO::FETCH_ASSOC);
    if (!$current) app_json(['error' => 'نشانی پیدا نشد.'], 404);
    $values = array_merge($current, $body);
    $title = trim((string)$values['title']);
    $recipient = trim((string)$values['recipient']);
    $mobile = app_auth_mobile((string)$values['mobile']);
    $address = trim((string)$values['address']);
    $province = trim((string)($values['province'] ?? ''));
    $city = trim((string)($values['city'] ?? ''));
    $postal = preg_replace('/\\s+/', '', (string)($values['postal_code'] ?? ''));
    if ($title === '' || mb_strlen($title) > 80 || $recipient === '' || mb_strlen($recipient) > 160 ||
        $mobile === null || $address === '' || mb_strlen($address) > 2000 ||
        mb_strlen($province) > 100 || mb_strlen($city) > 100 ||
        ($postal !== '' && !preg_match('/^[0-9]{10}$/', $postal))) {
        app_json(['error' => 'اطلاعات نشانی کامل یا معتبر نیست.'], 422);
    }
    $lat = $values['latitude'] ?? null;
    $lng = $values['longitude'] ?? null;
    if (($lat === null) !== ($lng === null) ||
        ($lat !== null && (!is_numeric($lat) || (float)$lat < -90 || (float)$lat > 90)) ||
        ($lng !== null && (!is_numeric($lng) || (float)$lng < -180 || (float)$lng > 180))) {
        app_json(['error' => 'مختصات نشانی معتبر نیست.'], 422);
    }
    $update = $db->prepare(
        'UPDATE customer_addresses SET title=?,recipient=?,mobile=?,province=?,city=?,address=?,postal_code=?,latitude=?,longitude=?
         WHERE tenant_id=? AND user_id=? AND id=? AND is_active=1'
    );
    $update->execute([
        $title,$recipient,$mobile,$province === '' ? null : $province,$city === '' ? null : $city,
        $address,$postal === '' ? null : $postal,$lat === null ? null : (float)$lat,
        $lng === null ? null : (float)$lng,$tenant['id'],$session['id'],$addressId
    ]);
    app_json(['data' => ['id' => $addressId], 'message' => 'نشانی ویرایش شد.']);
}

function app_commerce_delete_address(PDO $db, array $tenant, array $session, int $addressId): never {
    $stmt = $db->prepare(
        'UPDATE customer_addresses SET is_active = 0
         WHERE tenant_id = ? AND user_id = ? AND id = ? AND is_active = 1'
    );
    $stmt->execute([$tenant['id'], $session['id'], $addressId]);
    if ($stmt->rowCount() !== 1) app_json(['error' => 'نشانی پیدا نشد.'], 404);
    app_json(['data' => ['id' => $addressId], 'message' => 'نشانی از فهرست فعال حذف شد.']);
}

function app_commerce_create_order(PDO $db, array $tenant, array $session): never {
    $body = app_commerce_body();
    $lines = app_commerce_lines($body);
    $fulfillment = (string)($body['fulfillment'] ?? 'delivery');
    if (!in_array($fulfillment, ['delivery', 'pickup'], true)) {
        app_json(['error' => 'روش دریافت سفارش معتبر نیست.'], 422);
    }
    $pickupSlotId = null;
    if (array_key_exists('pickup_slot_id',$body) && $body['pickup_slot_id'] !== null && $body['pickup_slot_id'] !== '') {
        $pickupSlotId = filter_var($body['pickup_slot_id'],FILTER_VALIDATE_INT);
        if ($pickupSlotId === false || $pickupSlotId < 1) app_json(['error'=>'بازه دریافت حضوری معتبر نیست.'],422);
    }
    if ($fulfillment === 'delivery' && $pickupSlotId !== null) {
        app_json(['error'=>'برای سفارش ارسالی نمی‌توان بازه دریافت حضوری انتخاب کرد.'],422);
    }
    $addressId = null;
    if ($fulfillment === 'delivery') {
        $addressId = filter_var($body['address_id'] ?? null, FILTER_VALIDATE_INT);
        if ($addressId === false || $addressId < 1) app_json(['error' => 'برای ارسال، یک نشانی انتخاب کنید.'], 422);
    }
    $note = trim((string)($body['customer_note'] ?? ''));
    if (mb_strlen($note) > 1000) app_json(['error' => 'یادداشت سفارش بیش از حد طولانی است.'], 422);

    $settingsQuery = $db->prepare("SELECT setting_value FROM store_settings WHERE tenant_id = ? AND setting_key = 'store.checkout' LIMIT 1");
    $settingsQuery->execute([$tenant['id']]);
    $checkoutSettings = json_decode((string)($settingsQuery->fetchColumn() ?: '{}'), true);
    if (!is_array($checkoutSettings)) $checkoutSettings = [];
    $deliveryEnabled = array_key_exists('delivery_enabled', $checkoutSettings) ? (bool)$checkoutSettings['delivery_enabled'] : true;
    $pickupEnabled = array_key_exists('pickup_enabled', $checkoutSettings) ? (bool)$checkoutSettings['pickup_enabled'] : true;
    if (($fulfillment === 'delivery' && !$deliveryEnabled) || ($fulfillment === 'pickup' && !$pickupEnabled)) {
        app_json(['error' => 'این روش دریافت در حال حاضر توسط فروشگاه ارائه نمی‌شود.'], 409);
    }
    $deliveryFeeRial = $fulfillment === 'delivery' ? max(0, (int)($checkoutSettings['delivery_fee_rial'] ?? 0)) : 0;
    $minimumOrderRial = max(0, (int)($checkoutSettings['minimum_order_rial'] ?? 0));
    $reservationMinutes = min(120, max(5, (int)($checkoutSettings['reservation_minutes'] ?? 20)));
    $deliveryZoneId = null;
    $pickupSlotsConfiguredQuery = $db->prepare('SELECT COUNT(*) FROM pickup_slots WHERE tenant_id=?');
    $pickupSlotsConfiguredQuery->execute([$tenant['id']]);
    $pickupSlotsConfigured = (int)$pickupSlotsConfiguredQuery->fetchColumn() > 0;
    if ($fulfillment === 'pickup' && $pickupSlotsConfigured && $pickupSlotId === null) {
        app_json(['error'=>'برای تحویل حضوری یک بازه زمانی انتخاب کنید.'],422);
    }
    if ($fulfillment === 'pickup' && !$pickupSlotsConfigured && $pickupSlotId !== null) {
        app_json(['error'=>'بازه دریافت انتخاب‌شده پیدا نشد.'],404);
    }

    $db->beginTransaction();
    try {
        if ($addressId !== null) {
            $address = $db->prepare('SELECT id,province,city,postal_code FROM customer_addresses WHERE tenant_id = ? AND user_id = ? AND id = ? AND is_active = 1 FOR UPDATE');
            $address->execute([$tenant['id'], $session['id'], $addressId]);
            $addressRow = $address->fetch(PDO::FETCH_ASSOC);
            if (!$addressRow) {
                $db->rollBack();
                app_json(['error' => 'نشانی انتخاب‌شده پیدا نشد.'], 404);
            }
            $zoneCount = $db->prepare('SELECT COUNT(*) FROM delivery_zones WHERE tenant_id=?');
            $zoneCount->execute([$tenant['id']]);
            if ((int)$zoneCount->fetchColumn() > 0) {
                $province = trim((string)($addressRow['province'] ?? ''));
                $city = trim((string)($addressRow['city'] ?? ''));
                $postalCode = preg_replace('/\\s+/', '', (string)($addressRow['postal_code'] ?? ''));
                $zone = $db->prepare(
                    "SELECT id,delivery_fee_rial,minimum_order_rial
                     FROM delivery_zones
                     WHERE tenant_id=? AND is_active=1 AND province=? AND city=?
                       AND (postal_prefix='' OR (?<>'' AND LEFT(?,CHAR_LENGTH(postal_prefix))=postal_prefix))
                     ORDER BY CHAR_LENGTH(postal_prefix) DESC,sort_order,id LIMIT 1"
                );
                $zone->execute([$tenant['id'],$province,$city,$postalCode,$postalCode]);
                $zoneRow = $zone->fetch(PDO::FETCH_ASSOC);
                if (!$zoneRow) {
                    $db->rollBack();
                    app_json(['error' => 'این نشانی خارج از محدوده ارسال فروشگاه است.'], 422);
                }
                $deliveryZoneId = (int)$zoneRow['id'];
                $deliveryFeeRial = (int)$zoneRow['delivery_fee_rial'];
                $minimumOrderRial = max($minimumOrderRial,(int)$zoneRow['minimum_order_rial']);
            }
        }

        if ($fulfillment === 'pickup' && $pickupSlotId !== null) {
            $slot = $db->prepare(
                'SELECT id,capacity FROM pickup_slots
                 WHERE tenant_id=? AND id=? AND is_active=1
                   AND starts_at>UTC_TIMESTAMP() AND ends_at>UTC_TIMESTAMP()
                 LIMIT 1 FOR UPDATE'
            );
            $slot->execute([$tenant['id'],$pickupSlotId]);
            $slotRow = $slot->fetch(PDO::FETCH_ASSOC);
            if (!$slotRow) {
                $db->rollBack();
                app_json(['error'=>'این بازه دریافت دیگر فعال یا قابل انتخاب نیست.'],409);
            }
            $reserved = $db->prepare(
                "SELECT COUNT(*) FROM orders o
                 WHERE o.tenant_id=? AND o.pickup_slot_id=?
                   AND (
                     o.status IN ('paid','accepted','picking','packed','ready_pickup','dispatched','delivered')
                     OR (o.status='pending_payment' AND EXISTS (
                       SELECT 1 FROM stock_reservations sr
                       WHERE sr.tenant_id=o.tenant_id AND sr.order_id=o.id
                         AND sr.status='active' AND sr.expires_at>UTC_TIMESTAMP()
                     ))
                   )"
            );
            $reserved->execute([$tenant['id'],$pickupSlotId]);
            if ((int)$reserved->fetchColumn() >= (int)$slotRow['capacity']) {
                $db->rollBack();
                app_json(['error'=>'ظرفیت این بازه دریافت تکمیل شده است؛ زمان دیگری انتخاب کنید.'],409);
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
        if ((int)$quote['total_rial'] < $minimumOrderRial) {
            $db->rollBack();
            app_json(['error' => 'حداقل مبلغ سفارش رعایت نشده است.'], 422);
        }
        $coupon = app_coupon_evaluate($db,$tenant,(int)$session['id'],(string)($body['coupon_code']??''),(int)$quote['subtotal_rial']);
        if (isset($coupon['error'])) {
            $db->rollBack();
            app_json(['error'=>$coupon['error']],(int)($coupon['status']??422));
        }
        $discountRial = (int)$coupon['discount_rial'];
        $orderTotalRial = (int)$quote['subtotal_rial'] - $discountRial + $deliveryFeeRial;
        $orderNumber = 'SM' . gmdate('ymdHis') . strtoupper(bin2hex(random_bytes(3)));
        $insertOrder = $db->prepare(
            "INSERT INTO orders
             (tenant_id,user_id,address_id,delivery_zone_id,pickup_slot_id,coupon_id,order_number,status,fulfillment,subtotal_rial,discount_rial,delivery_fee_rial,total_rial,customer_note)
             VALUES (?,?,?,?,?,?,?,'pending_payment',?,?,?,?,?,?)"
        );
        $insertOrder->execute([
            $tenant['id'], $session['id'], $addressId, $deliveryZoneId, $pickupSlotId, $coupon['coupon_id'], $orderNumber, $fulfillment,
            $quote['subtotal_rial'], $discountRial, $deliveryFeeRial, $orderTotalRial, $note === '' ? null : $note,
        ]);
        $orderId = (int)$db->lastInsertId();
        if ($coupon['coupon_id'] !== null) {
            $redemption=$db->prepare('INSERT INTO coupon_redemptions (tenant_id,coupon_id,order_id,user_id,discount_rial) VALUES (?,?,?,?,?)');
            $redemption->execute([$tenant['id'],$coupon['coupon_id'],$orderId,$session['id'],$discountRial]);
        }

        $insertLine = $db->prepare(
            'INSERT INTO order_items (tenant_id,order_id,product_id,product_title_snapshot,unit_price_rial,quantity,line_total_rial)
             VALUES (?,?,?,?,?,?,?)'
        );
        $reserve = $db->prepare(
            "INSERT INTO stock_reservations (tenant_id,order_id,warehouse_id,product_id,quantity,status,expires_at)
             VALUES (?,?,?,?,?,'active',DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? MINUTE))"
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
                $reserve->execute([$tenant['id'], $orderId, $stock['warehouse_id'], $item['product_id'], $take, $reservationMinutes]);
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
            'subtotal_rial' => $quote['subtotal_rial'], 'discount_rial' => $discountRial,
            'coupon_code' => $coupon['coupon_code'], 'delivery_fee_rial' => $deliveryFeeRial,
            'delivery_zone_id' => $deliveryZoneId, 'pickup_slot_id' => $pickupSlotId,
            'total_rial' => $orderTotalRial, 'reservation_expires_in_seconds' => $reservationMinutes * 60,
            'payment_required' => true,
        ], 'message' => 'سفارش ثبت اولیه شد؛ برای نهایی‌شدن باید پرداخت تأیید شود.'], 201);
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}

function app_commerce_order_history(PDO $db, array $tenant, array $session): never {
    $stmt = $db->prepare(
        'SELECT o.id, o.order_number, o.status, o.fulfillment, o.subtotal_rial, o.discount_rial,
                o.delivery_fee_rial, o.total_rial, o.created_at,
                ps.title AS pickup_slot_title, ps.starts_at AS pickup_slot_starts_at, ps.ends_at AS pickup_slot_ends_at
         FROM orders o
         LEFT JOIN pickup_slots ps ON ps.tenant_id=o.tenant_id AND ps.id=o.pickup_slot_id
         WHERE o.tenant_id = ? AND o.user_id = ? ORDER BY o.id DESC LIMIT 100'
    );
    $stmt->execute([$tenant['id'], $session['id']]);
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
