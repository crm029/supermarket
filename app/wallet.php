<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function app_wallet_reward_basis_points(): int {
    $raw = app_env('REFERRAL_REWARD_BASIS_POINTS', '0') ?? '0';
    if (!preg_match('/^\d{1,5}$/', (string)$raw)) {
        throw new RuntimeException('REFERRAL_REWARD_BASIS_POINTS must be an integer from 0 to 10000.');
    }
    $basisPoints = (int)$raw;
    if ($basisPoints < 0 || $basisPoints > 10000) {
        throw new RuntimeException('REFERRAL_REWARD_BASIS_POINTS must be an integer from 0 to 10000.');
    }
    return $basisPoints;
}

function app_wallet_reward_amount(int $orderTotalRial, int $basisPoints): int {
    if ($orderTotalRial < 0 || $basisPoints < 0 || $basisPoints > 10000) {
        throw new InvalidArgumentException('Invalid referral reward calculation input.');
    }
    // Split the multiplication to avoid integer overflow for large order totals.
    return intdiv($orderTotalRial, 10000) * $basisPoints
        + intdiv(($orderTotalRial % 10000) * $basisPoints, 10000);
}

function app_wallet_ensure_account(PDO $db, int $tenantId, int $userId): int {
    $insert = $db->prepare('INSERT IGNORE INTO wallet_accounts (tenant_id,user_id) VALUES (?,?)');
    $insert->execute([$tenantId, $userId]);
    $find = $db->prepare('SELECT id FROM wallet_accounts WHERE tenant_id = ? AND user_id = ? LIMIT 1');
    $find->execute([$tenantId, $userId]);
    $id = $find->fetchColumn();
    if (!$id) throw new RuntimeException('Unable to initialize customer wallet.');
    return (int)$id;
}

function app_wallet_balance(PDO $db, int $tenantId, int $walletId): int {
    $stmt = $db->prepare(
        "SELECT COALESCE(SUM(CASE WHEN entry_type IN ('credit','reversal') THEN CAST(amount_rial AS SIGNED) ELSE -CAST(amount_rial AS SIGNED) END),0)
         FROM wallet_entries WHERE tenant_id = ? AND wallet_id = ?"
    );
    $stmt->execute([$tenantId, $walletId]);
    return (int)$stmt->fetchColumn();
}

/**
 * Credit the referrer only after the referred customer's first verified paid order.
 * The reward is disabled by default until the merchant explicitly configures its rate.
 * This function must run inside the payment callback's database transaction.
 */
function app_wallet_process_referral_reward(PDO $db, int $tenantId, int $orderId, int $customerId, int $orderTotalRial): void {
    $basisPoints = app_wallet_reward_basis_points();
    if ($basisPoints === 0) return;

    $eventQuery = $db->prepare(
        "SELECT id, referrer_user_id
         FROM referral_events
         WHERE tenant_id = ? AND referred_user_id = ? AND status = 'pending'
         LIMIT 1 FOR UPDATE"
    );
    $eventQuery->execute([$tenantId, $customerId]);
    $event = $eventQuery->fetch();
    if (!$event) return;

    $referrerId = (int)$event['referrer_user_id'];
    if ($referrerId < 1 || $referrerId === $customerId) {
        $reject = $db->prepare("UPDATE referral_events SET status = 'rejected' WHERE id = ? AND tenant_id = ?");
        $reject->execute([$event['id'], $tenantId]);
        return;
    }

    $priorPaid = $db->prepare(
        "SELECT COUNT(*) FROM orders
         WHERE tenant_id = ? AND user_id = ? AND id <> ?
           AND status IN ('paid','accepted','picking','packed','ready_pickup','dispatched','delivered')"
    );
    $priorPaid->execute([$tenantId, $customerId, $orderId]);
    if ((int)$priorPaid->fetchColumn() > 0) {
        $reject = $db->prepare(
            "UPDATE referral_events SET status = 'rejected', qualifying_order_id = ?
             WHERE id = ? AND tenant_id = ?"
        );
        $reject->execute([$orderId, $event['id'], $tenantId]);
        return;
    }

    $reward = app_wallet_reward_amount($orderTotalRial, $basisPoints);
    if ($reward < 1) {
        $qualify = $db->prepare(
            "UPDATE referral_events SET status = 'qualified', qualifying_order_id = ?, reward_rial = 0
             WHERE id = ? AND tenant_id = ?"
        );
        $qualify->execute([$orderId, $event['id'], $tenantId]);
        return;
    }

    $walletId = app_wallet_ensure_account($db, $tenantId, $referrerId);
    $entry = $db->prepare(
        "INSERT IGNORE INTO wallet_entries
         (tenant_id,wallet_id,entry_type,amount_rial,source_type,source_id,idempotency_key,note)
         VALUES (?,?,'credit',?,'referral_reward',? ,?,'پاداش دعوت پس از اولین خرید تأییدشده')"
    );
    $entry->execute([$tenantId, $walletId, $reward, $orderId, 'referral-order-' . $orderId]);
    if ($entry->rowCount() !== 1) return;

    $update = $db->prepare(
        "UPDATE referral_events
         SET status = 'rewarded', qualifying_order_id = ?, reward_rial = ?
         WHERE id = ? AND tenant_id = ? AND status = 'pending'"
    );
    $update->execute([$orderId, $reward, $event['id'], $tenantId]);
    if ($update->rowCount() !== 1) {
        throw new RuntimeException('Referral reward state could not be finalized.');
    }

    $audit = $db->prepare(
        "INSERT INTO audit_logs (tenant_id,actor_user_id,action,entity_type,entity_id,details)
         VALUES (?,?,'wallet.referral_rewarded','wallet',?,JSON_OBJECT('order_id',?,'amount_rial',?))"
    );
    $audit->execute([$tenantId, $customerId, $walletId, $orderId, $reward]);
}

function app_wallet_customer_summary(PDO $db, array $tenant, array $session): never {
    $tenantId = (int)$tenant['id'];
    $userId = (int)$session['id'];
    $walletId = app_wallet_ensure_account($db, $tenantId, $userId);
    $balance = app_wallet_balance($db, $tenantId, $walletId);
    $history = $db->prepare(
        "SELECT id, entry_type, amount_rial, source_type, source_id, note, created_at
         FROM wallet_entries
         WHERE tenant_id = ? AND wallet_id = ?
         ORDER BY id DESC LIMIT 50"
    );
    $history->execute([$tenantId, $walletId]);
    $referrals = $db->prepare(
        "SELECT status, reward_rial, qualifying_order_id, created_at
         FROM referral_events
         WHERE tenant_id = ? AND referrer_user_id = ?
         ORDER BY id DESC LIMIT 50"
    );
    $referrals->execute([$tenantId, $userId]);
    app_json(['data' => [
        'balance_rial' => $balance,
        'currency' => 'IRR',
        'entries' => $history->fetchAll(),
        'referrals' => $referrals->fetchAll(),
    ]]);
}
