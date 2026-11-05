<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/stock_batches.php';

$db = app_db();
$lock = $db->query("SELECT GET_LOCK('supermarket360:expire-stock-reservations', 0)")->fetchColumn();
if ((int)$lock !== 1) {
    fwrite(STDOUT, "Another reservation expiry worker is running.\n");
    exit(0);
}

$processed = 0;
try {
    $db->beginTransaction();
    $stmt = $db->query(
        "SELECT id,tenant_id,order_id,warehouse_id,product_id,quantity
         FROM stock_reservations
         WHERE status='active' AND expires_at<=UTC_TIMESTAMP()
         ORDER BY expires_at,id
         LIMIT 500 FOR UPDATE"
    );
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stock = $db->prepare(
        'UPDATE stock_levels SET reserved=reserved-?
         WHERE tenant_id=? AND warehouse_id=? AND product_id=? AND reserved>=?'
    );
    $mark = $db->prepare("UPDATE stock_reservations SET status='expired' WHERE tenant_id=? AND id=? AND status='active'");
    $movement = $db->prepare(
        "INSERT INTO stock_movements
         (tenant_id,warehouse_id,product_id,actor_user_id,movement_type,quantity,reference_type,reference_id,reason)
         VALUES (?,?,?,NULL,'release',?,'order',?,'انقضای خودکار مهلت پرداخت')"
    );
    foreach ($rows as $row) {
        $qty = (int)$row['quantity'];
        $stock->execute([$qty,$row['tenant_id'],$row['warehouse_id'],$row['product_id'],$qty]);
        if ($stock->rowCount() !== 1) {
            throw new RuntimeException('Could not release expired aggregate reservation '.$row['id']);
        }
        app_stock_batch_release_reservation($db,(int)$row['tenant_id'],(int)$row['id'],'expired');
        $mark->execute([$row['tenant_id'],$row['id']]);
        if ($mark->rowCount() !== 1) {
            throw new RuntimeException('Could not mark reservation expired '.$row['id']);
        }
        $movement->execute([$row['tenant_id'],$row['warehouse_id'],$row['product_id'],-$qty,$row['order_id']]);
        $processed++;
    }
    $db->commit();
    fwrite(STDOUT, "Expired reservations released: {$processed}\n");
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    fwrite(STDERR, $e->getMessage()."\n");
    exitCode:
    exit(1);
} finally {
    $db->query("SELECT RELEASE_LOCK('supermarket360:expire-stock-reservations')");
}
