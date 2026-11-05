<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/wallet.php';

$tenantId = 1;
$mobile = '09120000004';
$db = app_db();
$userQuery = $db->prepare(
    "SELECT id, referred_by_user_id FROM users WHERE tenant_id = ? AND mobile = ? LIMIT 1"
);
$userQuery->execute([$tenantId, $mobile]);
$user = $userQuery->fetch();
if (!$user || empty($user['referred_by_user_id'])) {
    throw new RuntimeException('Referral integration fixture is missing.');
}

putenv('REFERRAL_REWARD_BASIS_POINTS=500');
$orderNumber = 'WALLETTEST' . strtoupper(bin2hex(random_bytes(4)));
$insertOrder = $db->prepare(
    "INSERT INTO orders
     (tenant_id,user_id,order_number,status,fulfillment,subtotal_rial,discount_rial,delivery_fee_rial,total_rial)
     VALUES (?, ?, ?, 'paid', 'pickup', 100000, 0, 0, 100000)"
);
$insertOrder->execute([$tenantId, (int)$user['id'], $orderNumber]);
$orderId = (int)$db->lastInsertId();

$db->beginTransaction();
try {
    app_wallet_process_referral_reward($db, $tenantId, $orderId, (int)$user['id'], 100000);
    $db->commit();
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    throw $e;
}

$event = $db->prepare(
    "SELECT status, reward_rial, qualifying_order_id
     FROM referral_events WHERE tenant_id = ? AND referred_user_id = ? LIMIT 1"
);
$event->execute([$tenantId, (int)$user['id']]);
$eventRow = $event->fetch();
if (!$eventRow || $eventRow['status'] !== 'rewarded' ||
    (int)$eventRow['reward_rial'] !== 5000 || (int)$eventRow['qualifying_order_id'] !== $orderId) {
    throw new RuntimeException('First paid-order referral reward was not finalized.');
}

$wallet = $db->prepare('SELECT id FROM wallet_accounts WHERE tenant_id = ? AND user_id = ?');
$wallet->execute([$tenantId, (int)$user['referred_by_user_id']]);
$walletId = (int)$wallet->fetchColumn();
$entries = $db->prepare(
    "SELECT COUNT(*), COALESCE(SUM(amount_rial),0)
     FROM wallet_entries
     WHERE tenant_id = ? AND wallet_id = ? AND source_type = 'referral_reward' AND source_id = ?"
);
$entries->execute([$tenantId, $walletId, $orderId]);
[$count, $amount] = $entries->fetch(PDO::FETCH_NUM);
if ((int)$count !== 1 || (int)$amount !== 5000) {
    throw new RuntimeException('Referral wallet credit is missing or duplicated.');
}

// A repeated processing attempt must not create a second credit.
$db->beginTransaction();
try {
    app_wallet_process_referral_reward($db, $tenantId, $orderId, (int)$user['id'], 100000);
    $db->commit();
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    throw $e;
}
$entries->execute([$tenantId, $walletId, $orderId]);
[$countAfterRetry, $amountAfterRetry] = $entries->fetch(PDO::FETCH_NUM);
if ((int)$countAfterRetry !== 1 || (int)$amountAfterRetry !== 5000) {
    throw new RuntimeException('Repeated referral processing duplicated a wallet credit.');
}

echo "Wallet referral integration tests passed.\n";
