<?php
declare(strict_types=1);

/**
 * Issue renewal invoices shortly before subscription expiry and mark overdue
 * subscriptions past_due after a configurable grace period. This worker never
 * charges a merchant or suspends a store.
 *
 * Schedule daily:
 *   php /path/to/supermarket/bin/process-subscription-billing.php
 */
if(PHP_SAPI!=='cli'){
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__).'/app/bootstrap.php';
require_once dirname(__DIR__).'/app/platform.php';
require_once dirname(__DIR__).'/app/notifications.php';

function app_subscription_worker_int(string $key,int $default,int $min,int $max): int {
    $value=filter_var(app_env($key,(string)$default),FILTER_VALIDATE_INT);
    if($value===false||$value<$min||$value>$max)throw new RuntimeException('Invalid '.$key.' configuration.');
    return $value;
}


function app_subscription_worker_notify_owner(PDO $db,int $tenantId,string $eventKey,string $title,string $body,string $entityType,int $entityId): void {
    app_notification_notify_role($db,$tenantId,'store_owner',$eventKey,'system',$title,$body,$entityType,$entityId);
}

function app_subscription_worker_audit(PDO $db,string $action,int $subscriptionId,array $details): void {
    $stmt=$db->prepare(
        'INSERT INTO audit_logs (tenant_id,actor_user_id,action,entity_type,entity_id,details)
         VALUES (NULL,NULL,?,\'tenant_subscription\',?,?)'
    );
    $stmt->execute([$action,$subscriptionId,json_encode($details,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
}

$db=app_db();
try{
    $batchSize=app_subscription_worker_int('SUBSCRIPTION_BILLING_BATCH_SIZE',100,1,500);
    $leadDays=app_subscription_worker_int('SUBSCRIPTION_BILLING_LEAD_DAYS',7,1,30);
    $graceDays=app_subscription_worker_int('SUBSCRIPTION_GRACE_DAYS',7,0,30);
    $created=0;
    $pastDue=0;
    $cancelled=0;
    $errors=0;

    $cancellationCandidates=$db->query(
        "SELECT id,tenant_id FROM tenant_subscriptions
         WHERE status IN ('active','past_due') AND cancel_at_period_end=1
           AND current_period_end IS NOT NULL AND current_period_end<=UTC_TIMESTAMP()
         ORDER BY current_period_end,id LIMIT {$batchSize}"
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach($cancellationCandidates as $candidate){
        $db->beginTransaction();
        try{
            $tenantLock=$db->prepare('SELECT id FROM tenants WHERE id=? FOR UPDATE');
            $tenantLock->execute([(int)$candidate['tenant_id']]);
            if(!$tenantLock->fetchColumn()){$db->rollBack();continue;}
            $lock=$db->prepare("SELECT id,tenant_id,status,current_period_end,cancel_at_period_end FROM tenant_subscriptions WHERE tenant_id=? AND id=? FOR UPDATE");
            $lock->execute([(int)$candidate['tenant_id'],(int)$candidate['id']]);
            $sub=$lock->fetch(PDO::FETCH_ASSOC);
            if(!$sub||!in_array($sub['status'],['active','past_due'],true)||(int)$sub['cancel_at_period_end']!==1||
               empty($sub['current_period_end'])||strtotime($sub['current_period_end'].' UTC')>time()){
                $db->commit();continue;
            }
            $update=$db->prepare("UPDATE tenant_subscriptions SET status='cancelled',cancelled_at=UTC_TIMESTAMP(),cancel_at_period_end=0 WHERE tenant_id=? AND id=? AND status IN ('active','past_due') AND cancel_at_period_end=1");
            $update->execute([(int)$sub['tenant_id'],(int)$sub['id']]);
            if($update->rowCount()===1){
                app_subscription_worker_audit($db,'platform.subscription.cancelled_at_period_end',(int)$sub['id'],[
                    'tenant_id'=>(int)$sub['tenant_id'],'current_period_end'=>$sub['current_period_end']
                ]);
                app_subscription_worker_notify_owner($db,(int)$sub['tenant_id'],'platform-subscription-cancelled-'.(int)$sub['id'],
                    'اشتراک فروشگاه لغو شد','دوره اشتراک به پایان رسید و لغو زمان‌بندی‌شده اجرا شد. برای فعال‌سازی دوباره با مدیریت پلتفرم تماس بگیرید.',
                    'tenant_subscription',(int)$sub['id']);
                $cancelled++;
            }
            $db->commit();
        }catch(Throwable $e){
            if($db->inTransaction())$db->rollBack();
            $errors++;
            fwrite(STDERR,'Scheduled cancellation failed for subscription '.(int)$candidate['id'].': '.$e->getMessage().PHP_EOL);
        }
    }

    $due=$db->query(
        "SELECT id,tenant_id FROM tenant_subscriptions
         WHERE status='active' AND cancel_at_period_end=0 AND current_period_end IS NOT NULL
           AND current_period_end>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL {$graceDays} DAY)
           AND current_period_end<=DATE_ADD(UTC_TIMESTAMP(),INTERVAL {$leadDays} DAY)
         ORDER BY current_period_end,id LIMIT {$batchSize}"
    )->fetchAll(PDO::FETCH_ASSOC);

    foreach($due as $candidate){
        $db->beginTransaction();
        try{
            $tenantLock=$db->prepare('SELECT id FROM tenants WHERE id=? FOR UPDATE');
            $tenantLock->execute([(int)$candidate['tenant_id']]);
            if(!$tenantLock->fetchColumn()){$db->rollBack();continue;}

            $lock=$db->prepare(
                "SELECT id,tenant_id,status,billing_period_months,price_monthly_rial,current_period_end
                 FROM tenant_subscriptions WHERE tenant_id=? AND id=? FOR UPDATE"
            );
            $lock->execute([(int)$candidate['tenant_id'],(int)$candidate['id']]);
            $sub=$lock->fetch(PDO::FETCH_ASSOC);
            if(!$sub||$sub['status']!=='active'||empty($sub['current_period_end'])||
               strtotime($sub['current_period_end'].' UTC')<time()-($graceDays*86400)||
               strtotime($sub['current_period_end'].' UTC')>time()+($leadDays*86400)){
                $db->commit();continue;
            }

            $exists=$db->prepare('SELECT id FROM platform_invoices WHERE subscription_id=? AND period_start=? LIMIT 1');
            $exists->execute([(int)$sub['id'],$sub['current_period_end']]);
            if($exists->fetchColumn()){$db->commit();continue;}

            $months=(int)$sub['billing_period_months'];
            if(!in_array($months,[1,3,12],true))throw new RuntimeException('Unsupported subscription billing period.');
            $amount=(int)$sub['price_monthly_rial']*$months;
            if($amount<1||$amount>PHP_INT_MAX)throw new RuntimeException('Subscription invoice amount is outside the supported range.');
            $start=(string)$sub['current_period_end'];
            $dates=$db->query('SELECT DATE_ADD('.$db->quote($start).',INTERVAL '.$months.' MONTH) AS period_end')->fetch(PDO::FETCH_ASSOC);
            $insert=$db->prepare(
                "INSERT INTO platform_invoices
                 (tenant_id,subscription_id,invoice_number,period_start,period_end,amount_rial,status,due_at,created_by)
                 VALUES (?,?,?,?,?,?,'issued',?,NULL)"
            );
            $insert->execute([
                (int)$sub['tenant_id'],(int)$sub['id'],app_platform_invoice_number(),
                $start,$dates['period_end'],$amount,$start
            ]);
            $invoiceId=(int)$db->lastInsertId();
            app_subscription_worker_audit($db,'platform.subscription.renewal_invoice_created',(int)$sub['id'],[
                'tenant_id'=>(int)$sub['tenant_id'],'invoice_id'=>$invoiceId,
                'amount_rial'=>$amount,'period_start'=>$start,'period_end'=>$dates['period_end']
            ]);
            app_subscription_worker_notify_owner(
                $db,(int)$sub['tenant_id'],'platform-renewal-invoice-'.$invoiceId,
                'فاکتور تمدید اشتراک صادر شد',
                'فاکتور تمدید اشتراک به مبلغ '.number_format($amount).' ریال صادر شد. مهلت پرداخت تا '.(string)$start.' است.',
                'platform_invoice',$invoiceId
            );
            $db->commit();
            $created++;
        }catch(Throwable $e){
            if($db->inTransaction())$db->rollBack();
            $errors++;
            fwrite(STDERR,'Renewal invoice failed for subscription '.(int)$candidate['id'].': '.$e->getMessage().PHP_EOL);
        }
    }

    $overdue=$db->query(
        "SELECT id,tenant_id FROM tenant_subscriptions
         WHERE status='active' AND cancel_at_period_end=0 AND current_period_end IS NOT NULL
           AND current_period_end<DATE_SUB(UTC_TIMESTAMP(),INTERVAL {$graceDays} DAY)
         ORDER BY current_period_end,id LIMIT {$batchSize}"
    )->fetchAll(PDO::FETCH_ASSOC);

    foreach($overdue as $candidate){
        $db->beginTransaction();
        try{
            $tenantLock=$db->prepare('SELECT id FROM tenants WHERE id=? FOR UPDATE');
            $tenantLock->execute([(int)$candidate['tenant_id']]);
            if(!$tenantLock->fetchColumn()){$db->rollBack();continue;}
            $lock=$db->prepare(
                'SELECT id,tenant_id,status,current_period_end FROM tenant_subscriptions WHERE tenant_id=? AND id=? FOR UPDATE'
            );
            $lock->execute([(int)$candidate['tenant_id'],(int)$candidate['id']]);
            $sub=$lock->fetch(PDO::FETCH_ASSOC);
            if(!$sub||$sub['status']!=='active'||empty($sub['current_period_end'])||
               strtotime($sub['current_period_end'].' UTC')>=time()-($graceDays*86400)){
                $db->commit();continue;
            }
            $update=$db->prepare("UPDATE tenant_subscriptions SET status='past_due' WHERE tenant_id=? AND id=? AND status='active'");
            $update->execute([(int)$sub['tenant_id'],(int)$sub['id']]);
            if($update->rowCount()===1){
                app_subscription_worker_audit($db,'platform.subscription.marked_past_due',(int)$sub['id'],[
                    'tenant_id'=>(int)$sub['tenant_id'],'current_period_end'=>$sub['current_period_end'],
                    'grace_days'=>$graceDays
                ]);
                app_subscription_worker_notify_owner(
                    $db,(int)$sub['tenant_id'],'platform-subscription-past-due-'.(int)$sub['id'],
                    'اشتراک فروشگاه سررسید گذشته است',
                    'مهلت پرداخت اشتراک به پایان رسیده است. پس از ثبت پرداخت، اشتراک دوباره فعال می‌شود.',
                    'tenant_subscription',(int)$sub['id']
                );
                $pastDue++;
            }
            $db->commit();
        }catch(Throwable $e){
            if($db->inTransaction())$db->rollBack();
            $errors++;
            fwrite(STDERR,'Past-due update failed for subscription '.(int)$candidate['id'].': '.$e->getMessage().PHP_EOL);
        }
    }


    $reminderDays=app_subscription_worker_int('SUBSCRIPTION_REMINDER_LEAD_DAYS',3,1,14);
    $reminderCandidates=$db->query(
        "SELECT i.id,i.tenant_id,i.due_at FROM platform_invoices i
         WHERE i.status='issued' AND i.due_at<=DATE_ADD(UTC_TIMESTAMP(),INTERVAL {$reminderDays} DAY)
           AND (
             (i.due_at>=UTC_TIMESTAMP() AND NOT EXISTS(
               SELECT 1 FROM notifications n WHERE n.tenant_id=i.tenant_id
                 AND n.event_key=CONCAT('platform-invoice-due-',i.id)))
             OR
             (i.due_at<UTC_TIMESTAMP() AND NOT EXISTS(
               SELECT 1 FROM notifications n WHERE n.tenant_id=i.tenant_id
                 AND n.event_key=CONCAT('platform-invoice-overdue-',i.id,'-',DATE_FORMAT(UTC_DATE(),'%x-%v'))))
           )
         ORDER BY i.due_at,i.id LIMIT {$batchSize}"
    )->fetchAll(PDO::FETCH_ASSOC);
    $upcomingReminders=0;
    $overdueReminders=0;
    foreach($reminderCandidates as $candidate){
        $db->beginTransaction();
        try{
            $tenantLock=$db->prepare('SELECT id FROM tenants WHERE id=? FOR UPDATE');
            $tenantLock->execute([(int)$candidate['tenant_id']]);
            if(!$tenantLock->fetchColumn()){$db->rollBack();continue;}
            $invoiceLock=$db->prepare(
                "SELECT i.id,i.tenant_id,i.status,i.invoice_number,i.amount_rial,i.due_at,
                  COALESCE((SELECT SUM(p.amount_rial) FROM platform_invoice_payments p
                    WHERE p.tenant_id=i.tenant_id AND p.invoice_id=i.id),0) AS paid_rial
                 FROM platform_invoices i WHERE i.tenant_id=? AND i.id=? FOR UPDATE"
            );
            $invoiceLock->execute([(int)$candidate['tenant_id'],(int)$candidate['id']]);
            $invoice=$invoiceLock->fetch(PDO::FETCH_ASSOC);
            if(!$invoice||$invoice['status']!=='issued'||
               (int)$invoice['amount_rial']-(int)$invoice['paid_rial']<=0){
                $db->commit();continue;
            }
            $remaining=(int)$invoice['amount_rial']-(int)$invoice['paid_rial'];
            $isOverdue=strtotime((string)$invoice['due_at'].' UTC')<time();
            if($isOverdue){
                $week=gmdate('o-W');
                $eventKey='platform-invoice-overdue-'.$invoice['id'].'-'.$week;
                $title='یادآوری فاکتور معوق اشتراک';
                $body='فاکتور '.$invoice['invoice_number'].' هنوز تسویه نشده است. مانده قابل پرداخت: '.number_format($remaining).' ریال.';
                $overdueReminders++;
            }else{
                $eventKey='platform-invoice-due-'.$invoice['id'];
                $title='یادآوری مهلت پرداخت اشتراک';
                $body='مهلت پرداخت فاکتور '.$invoice['invoice_number'].' نزدیک است. مانده قابل پرداخت: '.number_format($remaining).' ریال.';
                $upcomingReminders++;
            }
            app_subscription_worker_notify_owner(
                $db,(int)$invoice['tenant_id'],$eventKey,$title,$body,'platform_invoice',(int)$invoice['id']
            );
            $db->commit();
        }catch(Throwable $e){
            if($db->inTransaction())$db->rollBack();
            $errors++;
            fwrite(STDERR,'Invoice reminder failed for invoice '.(int)$candidate['id'].': '.$e->getMessage().PHP_EOL);
        }
    }

    $moreRenewals=$db->query(
        "SELECT EXISTS(SELECT 1 FROM tenant_subscriptions s
          WHERE s.status='active' AND s.cancel_at_period_end=0 AND s.current_period_end>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL {$graceDays} DAY)
            AND s.current_period_end<=DATE_ADD(UTC_TIMESTAMP(),INTERVAL {$leadDays} DAY)
            AND NOT EXISTS(SELECT 1 FROM platform_invoices i WHERE i.subscription_id=s.id AND i.period_start=s.current_period_end)
          LIMIT 1)"
    )->fetchColumn();

    $moreCancellations=$db->query(
        "SELECT EXISTS(SELECT 1 FROM tenant_subscriptions WHERE status IN ('active','past_due')
          AND cancel_at_period_end=1 AND current_period_end<=UTC_TIMESTAMP() LIMIT 1)"
    )->fetchColumn();

    $moreReminders=$db->query(
        "SELECT EXISTS(SELECT 1 FROM platform_invoices i WHERE i.status='issued'
          AND i.due_at<=DATE_ADD(UTC_TIMESTAMP(),INTERVAL {$reminderDays} DAY)
          AND i.amount_rial>COALESCE((SELECT SUM(p.amount_rial) FROM platform_invoice_payments p
            WHERE p.tenant_id=i.tenant_id AND p.invoice_id=i.id),0)
          AND (
            (i.due_at>=UTC_TIMESTAMP() AND NOT EXISTS(SELECT 1 FROM notifications n WHERE n.tenant_id=i.tenant_id AND n.event_key=CONCAT('platform-invoice-due-',i.id)))
            OR
            (i.due_at<UTC_TIMESTAMP() AND NOT EXISTS(SELECT 1 FROM notifications n WHERE n.tenant_id=i.tenant_id AND n.event_key=CONCAT('platform-invoice-overdue-',i.id,'-',DATE_FORMAT(UTC_DATE(),'%x-%v'))))
          ) LIMIT 1)"
    )->fetchColumn();
    $moreOverdue=$db->query(
        "SELECT EXISTS(SELECT 1 FROM tenant_subscriptions
          WHERE status='active' AND cancel_at_period_end=0 AND current_period_end<DATE_SUB(UTC_TIMESTAMP(),INTERVAL {$graceDays} DAY) LIMIT 1)"
    )->fetchColumn();

    $report=[
        'status'=>$errors===0?'ok':'attention_required',
        'renewal_invoices_created'=>$created,
        'subscriptions_marked_past_due'=>$pastDue,
        'subscriptions_cancelled_at_period_end'=>$cancelled,
        'upcoming_invoice_reminders'=>$upcomingReminders,
        'overdue_invoice_reminders'=>$overdueReminders,
        'errors'=>$errors,
        'batch_size'=>$batchSize,
        'lead_days'=>$leadDays,
        'grace_days'=>$graceDays,
        'reminder_lead_days'=>$reminderDays,
        'more_may_remain'=>(bool)$moreCancellations||(bool)$moreRenewals||(bool)$moreOverdue||(bool)$moreReminders,
        'checked_at'=>gmdate('c')
    ];
    fwrite(STDOUT,json_encode($report,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL);
    if($errors>0)exit(1);
}catch(Throwable $e){
    fwrite(STDERR,'Subscription billing worker failed: '.$e->getMessage().PHP_EOL);
    exit(1);
}
