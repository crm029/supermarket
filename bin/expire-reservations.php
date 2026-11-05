<?php
declare(strict_types=1);

/**
 * Release stock held by unpaid orders after their reservation window.
 * Run from cron/Task Scheduler every minute:
 *   php /path/to/supermarket/bin/expire-reservations.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/app/bootstrap.php';

$db = app_db();
$batchSize = 100;
$db->beginTransaction();
try {
    // Lock orders before reservations, matching customer cancellation and payment callback lock order.
    $pending = $db->prepare(
        "SELECT DISTINCT tenant_id, order_id
         FROM stock_reservations
         WHERE status = 'active' AND expires_at <= UTC_TIMESTAMP()
         ORDER BY tenant_id, order_id
         LIMIT {$batchSize}"
    );
    $pending->execute();
    $orders = $pending->fetchAll();

    $lockOrder = $db->prepare('SELECT id, status FROM orders WHERE tenant_id = ? AND id = ? FOR UPDATE');
    $lockRows = $db->prepare(
        "SELECT id, warehouse_id, product_id, quantity
         FROM stock_reservations
         WHERE tenant_id = ? AND order_id = ? AND status = 'active' AND expires_at <= UTC_TIMESTAMP()
         ORDER BY id FOR UPDATE"
    );
    $release = $db->prepare(
        'UPDATE stock_levels
         SET reserved = reserved - ?
         WHERE tenant_id = ? AND warehouse_id = ? AND product_id = ? AND reserved >= ?'
    );
    $expire = $db->prepare(
        "UPDATE stock_reservations SET status = 'expired'
         WHERE id = ? AND status = 'active' AND expires_at <= UTC_TIMESTAMP()"
    );
    $cancelOrder = $db->prepare(
        "UPDATE orders o
         SET o.status = 'cancelled'
         WHERE o.tenant_id = ? AND o.id = ? AND o.status = 'pending_payment'
           AND NOT EXISTS (
             SELECT 1 FROM stock_reservations r
             WHERE r.tenant_id = o.tenant_id AND r.order_id = o.id AND r.status = 'active'
           )"
    );

    $released = 0;
    foreach ($orders as $candidate) {
        $lockOrder->execute([$candidate['tenant_id'], $candidate['order_id']]);
        if (!$lockOrder->fetch()) continue;

        $lockRows->execute([$candidate['tenant_id'], $candidate['order_id']]);
        foreach ($lockRows->fetchAll() as $row) {
            $quantity = (int)$row['quantity'];
            $release->execute([
                $quantity, $candidate['tenant_id'], $row['warehouse_id'], $row['product_id'], $quantity,
            ]);
            if ($release->rowCount() !== 1) {
                throw new RuntimeException('Reservation stock counter is inconsistent; refusing partial release.');
            }
            $expire->execute([$row['id']]);
            if ($expire->rowCount() !== 1) {
                throw new RuntimeException('Reservation state changed while processing expiry.');
            }
            $released++;
        }
        $cancelOrder->execute([$candidate['tenant_id'], $candidate['order_id']]);
    }

    $db->commit();
    fwrite(STDOUT, 'Expired reservations released: ' . $released . PHP_EOL);
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    fwrite(STDERR, 'Reservation expiry failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
