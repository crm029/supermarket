<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/wallet.php';
require_once __DIR__ . '/notifications.php';

function app_admin_order_refund_wallet(PDO $db, array $tenant, array $session, int $orderId): never {
    $body=app_auth_request_body();
    $reason=trim((string)($body['reason']??''));
    if(mb_strlen($reason)<12||mb_strlen($reason)>500){
        app_json(['error'=>'دلیل بازپرداخت باید حداقل ۱۲ و حداکثر ۵۰۰ نویسه باشد.'],422);
    }
    $db->beginTransaction();
    try{
        $lockOrder=$db->prepare(
            'SELECT id,user_id,order_number,status,total_rial
             FROM orders WHERE tenant_id=? AND id=? FOR UPDATE'
        );
        $lockOrder->execute([$tenant['id'],$orderId]);
        $order=$lockOrder->fetch(PDO::FETCH_ASSOC);
        if(!$order){
            $db->rollBack();
            app_json(['error'=>'سفارش پیدا نشد.'],404);
        }
        $eligible=['paid','accepted','picking','packed','ready_pickup','dispatched','out_for_delivery','delivered'];
        if(!in_array($order['status'],$eligible,true)){
            $db->rollBack();
            app_json(['error'=>'این سفارش در وضعیت قابل بازپرداخت نیست یا قبلاً بازپرداخت شده است.'],409);
        }

        $paymentQuery=$db->prepare(
            "SELECT id,amount_rial FROM payment_attempts
             WHERE tenant_id=? AND order_id=? AND status='verified'
             ORDER BY id DESC FOR UPDATE"
        );
        $paymentQuery->execute([$tenant['id'],$orderId]);
        $verifiedPayments=$paymentQuery->fetchAll(PDO::FETCH_ASSOC);
        if(count($verifiedPayments)!==1||(int)$verifiedPayments[0]['amount_rial']<(int)$order['total_rial']){
            $db->rollBack();
            app_json(['error'=>'پرداخت تأییدشده یکتا با مبلغ کامل سفارش پیدا نشد؛ بررسی دستی لازم است.'],409);
        }

        $existing=$db->prepare('SELECT id FROM order_refunds WHERE tenant_id=? AND order_id=? LIMIT 1');
        $existing->execute([$tenant['id'],$orderId]);
        if($existing->fetchColumn()){
            $db->rollBack();
            app_json(['error'=>'برای این سفارش قبلاً بازپرداخت ثبت شده است.'],409);
        }

        $customerId=(int)$order['user_id'];
        $amount=(int)$order['total_rial'];
        $walletId=app_wallet_ensure_account($db,(int)$tenant['id'],$customerId);
        $walletLock=$db->prepare('SELECT id FROM wallet_accounts WHERE tenant_id=? AND id=? FOR UPDATE');
        $walletLock->execute([$tenant['id'],$walletId]);
        if(!$walletLock->fetchColumn())throw new RuntimeException('Customer wallet could not be locked.');

        $credit=$db->prepare(
            "INSERT INTO wallet_entries
             (tenant_id,wallet_id,entry_type,amount_rial,source_type,source_id,idempotency_key,note)
             VALUES (?,?,'credit',?,'order_refund',?,?,'بازپرداخت کامل سفارش به اعتبار کیف پول')"
        );
        $credit->execute([$tenant['id'],$walletId,$amount,$orderId,'order-refund-'.$orderId]);
        if($credit->rowCount()!==1){
            $db->rollBack();
            app_json(['error'=>'تراکنش بازپرداخت تکراری است؛ وضعیت دفترکل را بررسی کنید.'],409);
        }

        $refund=$db->prepare(
            "INSERT INTO order_refunds
             (tenant_id,order_id,customer_user_id,refunded_by_user_id,wallet_id,refund_method,amount_rial,reason)
             VALUES (?,?,?,?,?,'wallet_credit',?,?)"
        );
        $refund->execute([$tenant['id'],$orderId,$customerId,$session['id'],$walletId,$amount,$reason]);
        $refundId=(int)$db->lastInsertId();

        $update=$db->prepare(
            "UPDATE orders SET status='refunded'
             WHERE tenant_id=? AND id=? AND status IN ('paid','accepted','picking','packed','ready_pickup','dispatched','out_for_delivery','delivered')"
        );
        $update->execute([$tenant['id'],$orderId]);
        if($update->rowCount()!==1)throw new RuntimeException('Order refund state could not be finalized.');

        $activeAssignments=$db->prepare(
            "SELECT id,assignee_user_id FROM order_assignments
             WHERE tenant_id=? AND order_id=? AND status='active' FOR UPDATE"
        );
        $activeAssignments->execute([$tenant['id'],$orderId]);
        $cancelledAssignments=$activeAssignments->fetchAll(PDO::FETCH_ASSOC);
        $cancelAssignments=$db->prepare(
            "UPDATE order_assignments SET status='cancelled',completed_at=UTC_TIMESTAMP()
             WHERE tenant_id=? AND order_id=? AND status='active'"
        );
        $cancelAssignments->execute([$tenant['id'],$orderId]);
        foreach($cancelledAssignments as $assignment){
            app_notification_create($db,(int)$tenant['id'],(int)$assignment['assignee_user_id'],
                'assignment-cancelled-refund-'.$assignment['id'],'assignment','وظیفه سفارش لغو شد',
                'سفارش '.$order['order_number'].' بازپرداخت شد و وظیفه عملیاتی شما لغو گردید.','order',$orderId);
        }

        $referralQuery=$db->prepare(
            "SELECT id,referrer_user_id,reward_rial FROM referral_events
             WHERE tenant_id=? AND qualifying_order_id=? AND status IN ('rewarded','qualified')
             LIMIT 1 FOR UPDATE"
        );
        $referralQuery->execute([$tenant['id'],$orderId]);
        $referral=$referralQuery->fetch(PDO::FETCH_ASSOC);
        $reversedReward=0;
        if($referral){
            $referrerId=(int)$referral['referrer_user_id'];
            $reversedReward=(int)$referral['reward_rial'];
            if($reversedReward>0){
                $referrerWallet=app_wallet_ensure_account($db,(int)$tenant['id'],$referrerId);
                $referrerLock=$db->prepare('SELECT id FROM wallet_accounts WHERE tenant_id=? AND id=? FOR UPDATE');
                $referrerLock->execute([$tenant['id'],$referrerWallet]);
                $debit=$db->prepare(
                    "INSERT INTO wallet_entries
                     (tenant_id,wallet_id,entry_type,amount_rial,source_type,source_id,idempotency_key,note)
                     VALUES (?,?,'debit',?,'referral_reward_reversal',?,?,'برگشت پاداش دعوت به علت بازپرداخت سفارش اولیه')"
                );
                $debit->execute([$tenant['id'],$referrerWallet,$reversedReward,$orderId,'referral-reversal-'.$orderId]);
                if($debit->rowCount()!==1)throw new RuntimeException('Referral reward reversal could not be recorded.');
                app_notification_create($db,(int)$tenant['id'],$referrerId,'referral-reversed-'.$orderId,'system',
                    'پاداش دعوت برگشت خورد','پاداش مربوط به سفارش '.$order['order_number'].' به علت بازپرداخت آن برگشت داده شد.',
                    'order',$orderId);
            }
            $eventUpdate=$db->prepare(
                "UPDATE referral_events SET status='reversed'
                 WHERE tenant_id=? AND id=? AND status IN ('rewarded','qualified')"
            );
            $eventUpdate->execute([$tenant['id'],$referral['id']]);
            if($eventUpdate->rowCount()!==1)throw new RuntimeException('Referral event could not be reversed.');
        }

        app_audit_log($db,(int)$tenant['id'],(int)$session['id'],'order.refunded.wallet_credit','order',$orderId,[
            'amount_rial'=>$amount,'wallet_id'=>$walletId,'reason'=>$reason,
            'referral_reward_reversed_rial'=>$reversedReward
        ]);
        app_notification_create($db,(int)$tenant['id'],$customerId,'order-refund-'.$orderId,'order',
            'اعتبار بازپرداخت به کیف پول اضافه شد',
            'مبلغ '.number_format($amount).' ریال بابت بازپرداخت سفارش '.$order['order_number'].' به کیف پول شما اضافه شد.',
            'order',$orderId);
        app_notification_notify_permission($db,(int)$tenant['id'],'orders.manage','order-refunded-'.$orderId,'order',
            'بازپرداخت سفارش ثبت شد','سفارش '.$order['order_number'].' به مبلغ '.number_format($amount).' ریال به کیف پول مشتری بازپرداخت شد.',
            'order',$orderId,(int)$session['id']);

        $balance=app_wallet_balance($db,(int)$tenant['id'],$walletId);
        $db->commit();
        app_json(['data'=>[
            'order_id'=>$orderId,'refund_id'=>$refundId,
            'amount_rial'=>$amount,'wallet_balance_rial'=>$balance,
            'refund_method'=>'wallet_credit','referral_reward_reversed_rial'=>$reversedReward
        ],'message'=>'مبلغ کامل سفارش به اعتبار کیف پول مشتری اضافه شد.'],201);
    }catch(Throwable $e){
        if($db->inTransaction())$db->rollBack();
        throw $e;
    }
}
