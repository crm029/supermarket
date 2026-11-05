<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/**
 * Lists traceable inventory lots. Legacy stock is represented by synthetic
 * lots without an expiry date until it is physically verified by staff.
 */

/** Create an additional lot for stock entering a warehouse. */
function app_stock_batch_add(PDO $db, int $tenantId, int $warehouseId, int $productId, int $quantity, string $batchNumber, ?string $expiryDate, int $unitCostRial = 0, ?int $receiptItemId = null): void {
    if ($quantity < 1) throw new InvalidArgumentException('Batch quantity must be positive.');
    $stmt = $db->prepare(
        'INSERT INTO stock_batches (tenant_id,warehouse_id,product_id,receipt_item_id,batch_number,expiry_date,received_quantity,on_hand,reserved,unit_cost_rial)
         VALUES (?,?,?,?,?,?,?, ?,0,?)'
    );
    $stmt->execute([$tenantId,$warehouseId,$productId,$receiptItemId,$batchNumber,$expiryDate,$quantity,$quantity,$unitCostRial]);
}

/** Consume only unreserved stock, oldest expiry first; NULL expiry is last. */
function app_stock_batch_consume(PDO $db, int $tenantId, int $warehouseId, int $productId, int $quantity, ?int $receiptItemId = null): array {
    if ($quantity < 1) throw new InvalidArgumentException('Batch consumption must be positive.');
    $sql = 'SELECT id,batch_number,expiry_date,on_hand,reserved,unit_cost_rial,receipt_item_id
            FROM stock_batches
            WHERE tenant_id=? AND warehouse_id=? AND product_id=? AND on_hand>reserved';
    $params = [$tenantId,$warehouseId,$productId];
    if ($receiptItemId !== null) { $sql .= ' AND receipt_item_id=?'; $params[] = $receiptItemId; }
    $sql .= ' ORDER BY (expiry_date IS NULL),expiry_date,received_at,id FOR UPDATE';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $remaining = $quantity;
    $taken = [];
    $update = $db->prepare('UPDATE stock_batches SET on_hand=on_hand-? WHERE tenant_id=? AND id=? AND on_hand-reserved>=?');
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $batch) {
        if ($remaining === 0) break;
        $available = (int)$batch['on_hand'] - (int)$batch['reserved'];
        $take = min($remaining,$available);
        if ($take < 1) continue;
        $update->execute([$take,$tenantId,$batch['id'],$take]);
        if ($update->rowCount() !== 1) throw new RuntimeException('Batch stock changed during consumption.');
        $taken[] = ['batch_number'=>$batch['batch_number'],'expiry_date'=>$batch['expiry_date'],'quantity'=>$take,'unit_cost_rial'=>(int)$batch['unit_cost_rial'],'receipt_item_id'=>$batch['receipt_item_id']];
        $remaining -= $take;
    }
    if ($remaining > 0) throw new RuntimeException('Insufficient unreserved batch stock for this operation.');
    return $taken;
}

/** Transfer lot identity and expiry metadata without losing traceability. */
function app_stock_batch_transfer(PDO $db, int $tenantId, int $fromWarehouseId, int $toWarehouseId, int $productId, int $quantity): void {
    $lots = app_stock_batch_consume($db,$tenantId,$fromWarehouseId,$productId,$quantity);
    foreach ($lots as $lot) {
        app_stock_batch_add($db,$tenantId,$toWarehouseId,$productId,$lot['quantity'],$lot['batch_number'],$lot['expiry_date'],$lot['unit_cost_rial']);
    }
}


/** Reserve exact FEFO lots for an order reservation; expired lots are never eligible. */
function app_stock_batch_reserve(PDO $db, int $tenantId, int $reservationId, int $warehouseId, int $productId, int $quantity): void {
    $stmt=$db->prepare(
        'SELECT id,on_hand,reserved FROM stock_batches
         WHERE tenant_id=? AND warehouse_id=? AND product_id=?
           AND (expiry_date IS NULL OR expiry_date>=UTC_DATE()) AND on_hand>reserved
         ORDER BY (expiry_date IS NULL),expiry_date,received_at,id FOR UPDATE'
    );
    $stmt->execute([$tenantId,$warehouseId,$productId]);
    $remaining=$quantity;
    $update=$db->prepare('UPDATE stock_batches SET reserved=reserved+? WHERE tenant_id=? AND id=? AND on_hand-reserved>=?');
    $insert=$db->prepare("INSERT INTO stock_reservation_batches (tenant_id,reservation_id,batch_id,quantity,status) VALUES (?,?,?,?,'active')");
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $batch){
        if($remaining===0)break;
        $available=(int)$batch['on_hand']-(int)$batch['reserved'];
        $take=min($remaining,$available);
        if($take<1)continue;
        $update->execute([$take,$tenantId,$batch['id'],$take]);
        if($update->rowCount()!==1)throw new RuntimeException('Batch reservation changed concurrently.');
        $insert->execute([$tenantId,$reservationId,$batch['id'],$take]);
        $remaining-=$take;
    }
    if($remaining>0)throw new RuntimeException('Insufficient unexpired batch stock.');
}

/** Commit all lot allocations for a paid reservation. */
function app_stock_batch_commit_reservation(PDO $db, int $tenantId, int $reservationId): void {
    $stmt=$db->prepare("SELECT id,batch_id,quantity FROM stock_reservation_batches WHERE tenant_id=? AND reservation_id=? AND status='active' ORDER BY batch_id FOR UPDATE");
    $stmt->execute([$tenantId,$reservationId]);
    $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
    $updateBatch=$db->prepare('UPDATE stock_batches SET on_hand=on_hand-?,reserved=reserved-? WHERE tenant_id=? AND id=? AND on_hand>=? AND reserved>=?');
    $updateAllocation=$db->prepare("UPDATE stock_reservation_batches SET status='committed' WHERE tenant_id=? AND id=? AND status='active'");
    foreach($rows as $row){
        $qty=(int)$row['quantity'];
        $updateBatch->execute([$qty,$qty,$tenantId,$row['batch_id'],$qty,$qty]);
        if($updateBatch->rowCount()!==1)throw new RuntimeException('Reserved batch could not be committed.');
        $updateAllocation->execute([$tenantId,$row['id']]);
    }
    if($rows===[])throw new RuntimeException('No batch allocations exist for this active reservation.');
}

/** Release exact lot allocations when an order is cancelled or its reservation expires. */
function app_stock_batch_release_reservation(PDO $db, int $tenantId, int $reservationId, string $status='released'): void {
    if(!in_array($status,['released','expired'],true))throw new InvalidArgumentException('Invalid batch release status.');
    $stmt=$db->prepare("SELECT id,batch_id,quantity FROM stock_reservation_batches WHERE tenant_id=? AND reservation_id=? AND status='active' ORDER BY batch_id FOR UPDATE");
    $stmt->execute([$tenantId,$reservationId]);
    $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
    $updateBatch=$db->prepare('UPDATE stock_batches SET reserved=reserved-? WHERE tenant_id=? AND id=? AND reserved>=?');
    $updateAllocation=$db->prepare("UPDATE stock_reservation_batches SET status=? WHERE tenant_id=? AND id=? AND status='active'");
    foreach($rows as $row){
        $qty=(int)$row['quantity'];
        $updateBatch->execute([$qty,$tenantId,$row['batch_id'],$qty]);
        if($updateBatch->rowCount()!==1)throw new RuntimeException('Reserved batch could not be released.');
        $updateAllocation->execute([$status,$tenantId,$row['id']]);
    }
}

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
