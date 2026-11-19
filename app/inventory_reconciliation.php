<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/**
 * Compare the aggregate stock ledger with the sum of its traceable batches.
 * This is a read-only control: it never silently repairs inventory.
 */
function app_inventory_reconciliation(PDO $db, array $tenant): never {
    $tenantId = (int)$tenant['id'];
    $stockSummary = $db->prepare(
        'SELECT COUNT(*) AS stock_rows, COALESCE(SUM(on_hand),0) AS stock_on_hand,
                COALESCE(SUM(reserved),0) AS stock_reserved
         FROM stock_levels WHERE tenant_id=?'
    );
    $stockSummary->execute([$tenantId]);
    $stock = $stockSummary->fetch(PDO::FETCH_ASSOC) ?: [];

    $batchSummary = $db->prepare(
        'SELECT COUNT(*) AS batch_groups, COALESCE(SUM(on_hand),0) AS batch_on_hand,
                COALESCE(SUM(reserved),0) AS batch_reserved
         FROM (
             SELECT warehouse_id,product_id,SUM(on_hand) AS on_hand,SUM(reserved) AS reserved
             FROM stock_batches WHERE tenant_id=? GROUP BY warehouse_id,product_id
         ) totals'
    );
    $batchSummary->execute([$tenantId]);
    $batches = $batchSummary->fetch(PDO::FETCH_ASSOC) ?: [];

    $stockDiscrepancies = $db->prepare(
        'SELECT sl.warehouse_id,sl.product_id,sl.on_hand AS ledger_on_hand,
                sl.reserved AS ledger_reserved,COALESCE(bt.on_hand,0) AS batch_on_hand,
                COALESCE(bt.reserved,0) AS batch_reserved,
                CASE WHEN bt.product_id IS NULL THEN "missing_batches" ELSE "quantity_mismatch" END AS issue
         FROM stock_levels sl
         LEFT JOIN (
             SELECT tenant_id,warehouse_id,product_id,SUM(on_hand) AS on_hand,SUM(reserved) AS reserved
             FROM stock_batches WHERE tenant_id=? GROUP BY tenant_id,warehouse_id,product_id
         ) bt ON bt.tenant_id=sl.tenant_id AND bt.warehouse_id=sl.warehouse_id AND bt.product_id=sl.product_id
         WHERE sl.tenant_id=?
           AND (bt.product_id IS NULL OR sl.on_hand<>bt.on_hand OR sl.reserved<>bt.reserved)
         ORDER BY sl.warehouse_id,sl.product_id LIMIT 500'
    );
    $stockDiscrepancies->execute([$tenantId,$tenantId]);
    $rows = $stockDiscrepancies->fetchAll(PDO::FETCH_ASSOC);

    $orphanBatches = $db->prepare(
        'SELECT bt.warehouse_id,bt.product_id,0 AS ledger_on_hand,0 AS ledger_reserved,
                bt.on_hand AS batch_on_hand,bt.reserved AS batch_reserved,"missing_stock_level" AS issue
         FROM (
             SELECT tenant_id,warehouse_id,product_id,SUM(on_hand) AS on_hand,SUM(reserved) AS reserved
             FROM stock_batches WHERE tenant_id=? GROUP BY tenant_id,warehouse_id,product_id
         ) bt
         LEFT JOIN stock_levels sl
           ON sl.tenant_id=bt.tenant_id AND sl.warehouse_id=bt.warehouse_id AND sl.product_id=bt.product_id
         WHERE sl.tenant_id IS NULL
         ORDER BY bt.warehouse_id,bt.product_id LIMIT 500'
    );
    $orphanBatches->execute([$tenantId]);
    $rows = array_merge($rows,$orphanBatches->fetchAll(PDO::FETCH_ASSOC));

    foreach ($rows as &$row) {
        foreach (['warehouse_id','product_id','ledger_on_hand','ledger_reserved','batch_on_hand','batch_reserved'] as $field) {
            $row[$field] = (int)$row[$field];
        }
    }
    unset($row);

    app_json(['data' => [
        'consistent' => $rows === [],
        'stock_rows' => (int)($stock['stock_rows'] ?? 0),
        'batch_groups' => (int)($batches['batch_groups'] ?? 0),
        'stock_on_hand' => (int)($stock['stock_on_hand'] ?? 0),
        'batch_on_hand' => (int)($batches['batch_on_hand'] ?? 0),
        'stock_reserved' => (int)($stock['stock_reserved'] ?? 0),
        'batch_reserved' => (int)($batches['batch_reserved'] ?? 0),
        'discrepancy_count' => count($rows),
        'discrepancies' => $rows,
    ]]);
}
