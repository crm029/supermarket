<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/stock_batches.php';
require_once __DIR__ . '/notifications.php';

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


function app_wallet_pay_order(PDO $db, array $tenant, array $session, int $orderId): never {
    $db->beginTransaction();
    try{
        $orderQuery=$db->prepare(
            'SELECT id,user_id,order_number,status,total_rial
             FROM orders WHERE tenant_id=? AND user_id=? AND id=? FOR UPDATE'
        );
        $orderQuery->execute([$tenant['id'],$session['id'],$orderId]);
        $order=$orderQuery->fetch(PDO::FETCH_ASSOC);
        if(!$order){
            $db->rollBack();
            app_json(['error'=>'سفارش پیدا نشد.'],404);
        }
        if($order['status']==='paid'){
            $existing=$db->prepare(
                "SELECT id FROM payment_attempts
                 WHERE tenant_id=? AND order_id=? AND provider='wallet' AND status='verified' LIMIT 1"
            );
            $existing->execute([$tenant['id'],$orderId]);
            if($existing->fetchColumn()){
                $walletId=app_wallet_ensure_account($db,(int)$tenant['id'],(int)$session['id']);
                $balance=app_wallet_balance($db,(int)$tenant['id'],$walletId);
                $db->commit();
                app_json(['data'=>[
                    'order_id'=>$orderId,'status'=>'paid','amount_rial'=>(int)$order['total_rial'],
                    'wallet_balance_rial'=>$balance,'payment_method'=>'wallet','already_paid'=>true
                ],'message'=>'این سفارش قبلاً با کیف پول پرداخت شده است.']);
            }
            $db->rollBack();
            app_json(['error'=>'این سفارش قبلاً با روش پرداخت دیگری تسویه شده است.'],409);
        }
        if($order['status']!=='pending_payment'){
            $db->rollBack();
            app_json(['error'=>'وضعیت سفارش برای پرداخت با کیف پول معتبر نیست.'],409);
        }
        $amount=(int)$order['total_rial'];
        if($amount<1){
            $db->rollBack();
            app_json(['error'=>'مبلغ سفارش برای پرداخت معتبر نیست.'],422);
        }

        $walletId=app_wallet_ensure_account($db,(int)$tenant['id'],(int)$session['id']);
        $walletLock=$db->prepare('SELECT id FROM wallet_accounts WHERE tenant_id=? AND id=? FOR UPDATE');
        $walletLock->execute([$tenant['id'],$walletId]);
        if(!$walletLock->fetchColumn())throw new RuntimeException('Customer wallet could not be locked.');
        $balance=app_wallet_balance($db,(int)$tenant['id'],$walletId);
        if($balance<$amount){
            $db->rollBack();
            app_json(['error'=>'موجودی کیف پول برای پرداخت کامل این سفارش کافی نیست.'],409);
        }

        $reservationResult=app_stock_commit_order_reservations(
            $db,(int)$tenant['id'],$orderId,(int)$session['id'],
            'پرداخت سفارش از کیف پول','آزادسازی رزرو ناقص پرداخت کیف پول'
        );
        if(!$reservationResult['committed']){
            $db->rollBack();
            app_json(['error'=>'مهلت رزرو موجودی پایان یافته است؛ سفارش را دوباره ثبت کنید.'],409);
        }

        $debit=$db->prepare(
            "INSERT INTO wallet_entries
             (tenant_id,wallet_id,entry_type,amount_rial,source_type,source_id,idempotency_key,note)
             VALUES (?,?,'debit',?,'order_wallet_payment',?,?,'پرداخت کامل سفارش از کیف پول')"
        );
        $debit->execute([$tenant['id'],$walletId,$amount,$orderId,'wallet-order-'.$orderId]);
        if($debit->rowCount()!==1)throw new RuntimeException('Wallet payment ledger entry could not be recorded.');

        $attempt=$db->prepare(
            "INSERT INTO payment_attempts
             (tenant_id,order_id,provider,authority,idempotency_key,amount_rial,status,verified_at)
             VALUES (?,?, 'wallet', ?, ?, ?, 'verified', UTC_TIMESTAMP())"
        );
        $attempt->execute([$tenant['id'],$orderId,'WALLET-'.$orderId,'wallet-payment-order-'.$orderId,$amount]);

        $update=$db->prepare("UPDATE orders SET status='paid' WHERE tenant_id=? AND id=? AND status='pending_payment'");
        $update->execute([$tenant['id'],$orderId]);
        if($update->rowCount()!==1)throw new RuntimeException('Order status could not be updated after wallet payment.');

        app_wallet_process_referral_reward($db,(int)$tenant['id'],$orderId,(int)$session['id'],$amount);
        app_audit_log($db,(int)$tenant['id'],(int)$session['id'],'order.paid.wallet','order',$orderId,[
            'amount_rial'=>$amount,'wallet_id'=>$walletId
        ]);
        app_notification_create($db,(int)$tenant['id'],(int)$session['id'],'payment-confirmed-'.$orderId,'order',
            'پرداخت سفارش تأیید شد','سفارش '.$order['order_number'].' با اعتبار کیف پول پرداخت شد.','order',$orderId);
        app_notification_notify_permission($db,(int)$tenant['id'],'orders.manage','payment-confirmed-'.$orderId,'order',
            'پرداخت سفارش دریافت شد','سفارش '.$order['order_number'].' به‌طور کامل از کیف پول پرداخت شد.','order',$orderId);

        $newBalance=app_wallet_balance($db,(int)$tenant['id'],$walletId);
        $db->commit();
        app_json(['data'=>[
            'order_id'=>$orderId,'status'=>'paid','amount_rial'=>$amount,
            'wallet_balance_rial'=>$newBalance,'payment_method'=>'wallet','already_paid'=>false
        ],'message'=>'پرداخت کامل سفارش از کیف پول با موفقیت انجام شد.']);
    }catch(Throwable $e){
        if($db->inTransaction())$db->rollBack();
        throw $e;
    }
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
