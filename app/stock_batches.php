<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/**
 * Lists traceable inventory lots. Legacy stock is represented by synthetic
 * lots without an expiry date until it is physically verified by staff.
 */
function app_admin_stock_batch_list(PDO $db, array $tenant): never {
    $warehouseId = filter_input(INPUT_GET, 'warehouse_id', FILTER_VALIDATE_INT);
    $productId = filter_input(INPUT_GET, 'product_id', FILTER_VALIDATE_INT);
    $state = (string)($_GET['state'] ?? 'all');
    if (!in_array($state, ['all', 'expired', 'expiring', 'valid', 'unknown'], true)) {
        app_json(['error' => 'فیلتر وضعیت بچ معتبر نیست.'], 422);
    }
    $days = filter_input(INPUT_GET, 'days', FILTER_VALIDATE_INT);
    $days = $days === false || $days === null ? 30 : max(1, min(180, (int)$days));
    $where = ['b.tenant_id = :tenant'];
    $params = ['tenant' => $tenant['id'], 'days' => $days];
    if ($warehouseId !== false && $warehouseId !== null) {
        if ($warehouseId < 1) app_json(['error' => 'شناسه انبار معتبر نیست.'], 422);
        $where[] = 'b.warehouse_id = :warehouse';
        $params['warehouse'] = $warehouseId;
    }
    if ($productId !== false && $productId !== null) {
        if ($productId < 1) app_json(['error' => 'شناسه کالا معتبر نیست.'], 422);
        $where[] = 'b.product_id = :product';
        $params['product'] = $productId;
    }
    if ($state === 'expired') $where[] = 'b.expiry_date < UTC_DATE()';
    if ($state === 'expiring') $where[] = 'b.expiry_date >= UTC_DATE() AND b.expiry_date <= DATE_ADD(UTC_DATE(), INTERVAL :days DAY)';
    if ($state === 'valid') $where[] = '(b.expiry_date IS NULL OR b.expiry_date > DATE_ADD(UTC_DATE(), INTERVAL :days DAY))';
    if ($state === 'unknown') $where[] = 'b.expiry_date IS NULL';
    $sql = 'SELECT b.id,b.warehouse_id,w.title AS warehouse_title,b.product_id,p.title AS product_title,
                   p.sku,b.receipt_item_id,b.batch_number,b.expiry_date,b.received_quantity,b.on_hand,b.reserved,
                   (b.on_hand-b.reserved) AS available_quantity,b.unit_cost_rial,b.received_at,
                   CASE WHEN b.expiry_date IS NULL THEN "unknown"
                        WHEN b.expiry_date < UTC_DATE() THEN "expired"
                        WHEN b.expiry_date <= DATE_ADD(UTC_DATE(), INTERVAL :label_days DAY) THEN "expiring"
                        ELSE "valid" END AS expiry_state
            FROM stock_batches b
            JOIN warehouses w ON w.tenant_id=b.tenant_id AND w.id=b.warehouse_id
            JOIN products p ON p.tenant_id=b.tenant_id AND p.id=b.product_id
            WHERE '.implode(' AND ',$where).'
            ORDER BY (b.expiry_date IS NULL),b.expiry_date,b.received_at,b.id
            LIMIT 500';
    $params['label_days'] = $days;
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    app_json(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'meta' => ['days' => $days]]);
}

function app_admin_stock_expiry_summary(PDO $db, array $tenant): never {
    $days = filter_input(INPUT_GET, 'days', FILTER_VALIDATE_INT);
    $days = $days === false || $days === null ? 30 : max(1, min(180, (int)$days));
    $stmt = $db->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN expiry_date < UTC_DATE() THEN on_hand-reserved ELSE 0 END),0) AS expired_available,
            COALESCE(SUM(CASE WHEN expiry_date < UTC_DATE() THEN on_hand ELSE 0 END),0) AS expired_on_hand,
            COALESCE(SUM(CASE WHEN expiry_date >= UTC_DATE() AND expiry_date <= DATE_ADD(UTC_DATE(), INTERVAL ? DAY) THEN on_hand-reserved ELSE 0 END),0) AS expiring_available,
            COUNT(CASE WHEN expiry_date < UTC_DATE() AND on_hand > 0 THEN 1 END) AS expired_batches,
            COUNT(CASE WHEN expiry_date >= UTC_DATE() AND expiry_date <= DATE_ADD(UTC_DATE(), INTERVAL ? DAY) AND on_hand > 0 THEN 1 END) AS expiring_batches,
            COUNT(CASE WHEN expiry_date IS NULL AND on_hand > 0 THEN 1 END) AS unknown_expiry_batches
         FROM stock_batches WHERE tenant_id=?"
    );
    $stmt->execute([$days, $days, $tenant['id']]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $summary['days'] = $days;
    foreach (['expired_available','expired_on_hand','expiring_available','expired_batches','expiring_batches','unknown_expiry_batches'] as $key) {
        $summary[$key] = (int)($summary[$key] ?? 0);
    }
    app_json(['data' => $summary]);
}
