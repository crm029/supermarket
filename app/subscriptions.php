<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/policies.php';
require_once __DIR__ . '/platform.php';
require_once __DIR__ . '/notifications.php';

function app_subscription_require_store_owner(PDO $db,array $tenant,array $session): void {
    $stmt=$db->prepare(
        "SELECT 1 FROM user_roles ur
         JOIN roles r ON r.role_scope_id=ur.role_scope_id AND r.id=ur.role_id
         WHERE ur.tenant_id=? AND ur.user_id=? AND r.role_key='store_owner' LIMIT 1"
    );
    $stmt->execute([(int)$tenant['id'],(int)$session['id']]);
    if(!$stmt->fetchColumn())app_json(['error'=>'فقط مالک فروشگاه می‌تواند اشتراک را مدیریت کند.'],403);
}

function app_tenant_subscription_overview(PDO $db,array $tenant,array $session): never {
    app_subscription_require_store_owner($db,$tenant,$session);
    $stmt=$db->prepare(
        "SELECT s.id,s.status,s.plan_id,s.billing_period_months,s.price_monthly_rial,
          s.current_period_start,s.current_period_end,p.name AS plan_name,p.limits_json
         FROM tenant_subscriptions s JOIN platform_plans p ON p.id=s.plan_id
         WHERE s.tenant_id=? AND s.status IN ('active','past_due','pending_payment')
         ORDER BY s.id DESC LIMIT 1"
    );
    $stmt->execute([(int)$tenant['id']]);
    $subscription=$stmt->fetch(PDO::FETCH_ASSOC)?:null;
    if($subscription){
        foreach(['id','plan_id','billing_period_months','price_monthly_rial'] as $key)$subscription[$key]=(int)$subscription[$key];
        $subscription['limits']=json_decode((string)$subscription['limits_json'],true)?:new stdClass();
        unset($subscription['limits_json']);
    }
    $plans=$db->query(
        "SELECT id,code,name,description,monthly_price_rial,limits_json
         FROM platform_plans WHERE is_active=1 ORDER BY sort_order,id"
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach($plans as &$plan){
        $plan['id']=(int)$plan['id'];$plan['monthly_price_rial']=(int)$plan['monthly_price_rial'];
        $plan['limits']=json_decode((string)$plan['limits_json'],true)?:new stdClass();
        unset($plan['limits_json']);
    }
    unset($plan);
    $requests=$db->prepare(
        "SELECT r.id,r.requested_plan_id,r.requested_billing_period_months,r.status,r.created_at,
          p.name AS requested_plan_name,i.id AS invoice_id,i.invoice_number,i.amount_rial,i.due_at
         FROM platform_subscription_change_requests r
         JOIN platform_plans p ON p.id=r.requested_plan_id
         LEFT JOIN platform_invoices i ON i.subscription_change_request_id=r.id
         WHERE r.tenant_id=? ORDER BY r.id DESC LIMIT 20"
    );
    $requests->execute([(int)$tenant['id']]);
    $history=$requests->fetchAll(PDO::FETCH_ASSOC);
    foreach($history as &$row){
        foreach(['id','requested_plan_id','requested_billing_period_months','invoice_id','amount_rial'] as $key)$row[$key]=$row[$key]===null?null:(int)$row[$key];
    }
    unset($row);
    app_json(['data'=>['subscription'=>$subscription,'plans'=>$plans,'change_requests'=>$history]]);
}

function app_tenant_subscription_change_request(PDO $db,array $tenant,array $session): never {
    app_subscription_require_store_owner($db,$tenant,$session);
    $body=app_auth_request_body();
    $planId=filter_var($body['plan_id']??null,FILTER_VALIDATE_INT);
    $months=filter_var($body['billing_period_months']??1,FILTER_VALIDATE_INT);
    if($planId===false||$planId<1||$months===false||!in_array($months,[1,3,12],true)){
        app_json(['error'=>'پلن یا دوره پرداخت معتبر نیست.'],422);
    }
    $db->beginTransaction();
    try{
        $tenantLock=$db->prepare('SELECT id FROM tenants WHERE id=? FOR UPDATE');
        $tenantLock->execute([(int)$tenant['id']]);
        if(!$tenantLock->fetchColumn()){$db->rollBack();app_json(['error'=>'فروشگاه پیدا نشد.'],404);}
        $subLock=$db->prepare(
            "SELECT id,plan_id,billing_period_months,status,current_period_end
             FROM tenant_subscriptions WHERE tenant_id=? AND status='active'
             ORDER BY id DESC LIMIT 1 FOR UPDATE"
        );
        $subLock->execute([(int)$tenant['id']]);$sub=$subLock->fetch(PDO::FETCH_ASSOC);
        if(!$sub||empty($sub['current_period_end'])){
            $db->rollBack();app_json(['error'=>'برای ثبت تغییر پلن، اشتراک فعال با تاریخ پایان دوره لازم است.'],409);
        }
        $plan=$db->prepare('SELECT id FROM platform_plans WHERE id=? AND is_active=1 FOR UPDATE');
        $plan->execute([$planId]);
        if(!$plan->fetchColumn()){$db->rollBack();app_json(['error'=>'پلن انتخاب‌شده فعال نیست.'],404);}
        if((int)$sub['plan_id']===$planId&&(int)$sub['billing_period_months']===$months){
            $db->rollBack();app_json(['error'=>'این پلن و دوره هم‌اکنون روی اشتراک شما فعال است.'],409);
        }
        $pending=$db->prepare("SELECT id FROM platform_subscription_change_requests WHERE tenant_id=? AND subscription_id=? AND status IN ('pending','invoiced') LIMIT 1 FOR UPDATE");
        $pending->execute([(int)$tenant['id'],(int)$sub['id']]);
        if($pending->fetchColumn()){$db->rollBack();app_json(['error'=>'برای این اشتراک یک درخواست تغییر پلن در حال بررسی یا پرداخت است.'],409);}
        $insert=$db->prepare(
            "INSERT INTO platform_subscription_change_requests
             (tenant_id,subscription_id,requested_plan_id,requested_billing_period_months,requested_by)
             VALUES (?,?,?,?,?)"
        );
        $insert->execute([(int)$tenant['id'],(int)$sub['id'],$planId,$months,(int)$session['id']]);
        $requestId=(int)$db->lastInsertId();
        app_audit_log($db,(int)$tenant['id'],(int)$session['id'],'subscription.plan_change.requested','subscription_change_request',$requestId,[
            'subscription_id'=>(int)$sub['id'],'requested_plan_id'=>$planId,'billing_period_months'=>$months
        ]);
        $db->commit();
        app_json(['data'=>['request_id'=>$requestId,'status'=>'pending'],'message'=>'درخواست تغییر پلن برای بررسی ارسال شد.'],201);
    }catch(PDOException $e){if($db->inTransaction())$db->rollBack();if((string)$e->getCode()==='23000')app_json(['error'=>'درخواست تکراری است.'],409);throw $e;}
    catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

function app_platform_subscription_change_requests(PDO $db): never {
    $rows=$db->query(
        "SELECT r.id,r.tenant_id,r.subscription_id,r.requested_plan_id,
          r.requested_billing_period_months,r.approved_price_monthly_rial,r.status,r.created_at,
          t.name AS tenant_name,t.slug,p.name AS plan_name,p.monthly_price_rial,
          i.id AS invoice_id,i.invoice_number,i.amount_rial,i.status AS invoice_status,i.due_at
         FROM platform_subscription_change_requests r
         JOIN tenants t ON t.id=r.tenant_id
         JOIN platform_plans p ON p.id=r.requested_plan_id
         LEFT JOIN platform_invoices i ON i.subscription_change_request_id=r.id
         WHERE r.status IN ('pending','invoiced')
         ORDER BY r.created_at,r.id LIMIT 200"
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach($rows as &$row){
        foreach(['id','tenant_id','subscription_id','requested_plan_id','requested_billing_period_months','approved_price_monthly_rial','monthly_price_rial','invoice_id','amount_rial'] as $key)$row[$key]=$row[$key]===null?null:(int)$row[$key];
    }
    unset($row);
    app_json(['data'=>$rows]);
}

function app_platform_subscription_change_approve(PDO $db,array $actorTenant,array $session,int $requestId): never {
    $scope=$db->prepare('SELECT tenant_id,subscription_id FROM platform_subscription_change_requests WHERE id=?');
    $scope->execute([$requestId]);$ref=$scope->fetch(PDO::FETCH_ASSOC);
    if(!$ref)app_json(['error'=>'درخواست تغییر پلن پیدا نشد.'],404);
    $db->beginTransaction();
    try{
        $tenantLock=$db->prepare('SELECT id FROM tenants WHERE id=? FOR UPDATE');
        $tenantLock->execute([(int)$ref['tenant_id']]);
        $subLock=$db->prepare('SELECT id,status,current_period_end FROM tenant_subscriptions WHERE tenant_id=? AND id=? FOR UPDATE');
        $subLock->execute([(int)$ref['tenant_id'],(int)$ref['subscription_id']]);$sub=$subLock->fetch(PDO::FETCH_ASSOC);
        $reqLock=$db->prepare(
            "SELECT id,tenant_id,subscription_id,requested_plan_id,requested_billing_period_months,status
             FROM platform_subscription_change_requests WHERE tenant_id=? AND id=? FOR UPDATE"
        );
        $reqLock->execute([(int)$ref['tenant_id'],$requestId]);$request=$reqLock->fetch(PDO::FETCH_ASSOC);
        if(!$request||$request['status']!=='pending'||!$sub||$sub['status']!=='active'||empty($sub['current_period_end'])||
           strtotime($sub['current_period_end'].' UTC')<=time()){
            $db->rollBack();app_json(['error'=>'درخواست دیگر قابل تأیید نیست یا دوره اشتراک فعال نیست.'],409);
        }
        $plan=$db->prepare('SELECT id,monthly_price_rial FROM platform_plans WHERE id=? AND is_active=1 FOR UPDATE');
        $plan->execute([(int)$request['requested_plan_id']]);$target=$plan->fetch(PDO::FETCH_ASSOC);
        if(!$target){$db->rollBack();app_json(['error'=>'پلن انتخاب‌شده دیگر فعال نیست.'],409);}
        $months=(int)$request['requested_billing_period_months'];$monthly=(int)$target['monthly_price_rial'];
        if($monthly<1||$monthly>intdiv(PHP_INT_MAX,$months))throw new RuntimeException('Plan change invoice amount is outside the supported range.');
        $amount=$monthly*$months;$start=(string)$sub['current_period_end'];
        $dates=$db->query('SELECT DATE_ADD('.$db->quote($start).',INTERVAL '.$months.' MONTH) AS period_end')->fetch(PDO::FETCH_ASSOC);
        $invoice=$db->prepare(
            "INSERT INTO platform_invoices
             (tenant_id,subscription_id,subscription_change_request_id,invoice_number,period_start,period_end,amount_rial,status,due_at,created_by)
             VALUES (?,?,?,?,?,?,?,'issued',?,NULL)"
        );
        $invoice->execute([(int)$request['tenant_id'],(int)$request['subscription_id'],$requestId,
            app_platform_invoice_number(),$start,$dates['period_end'],$amount,$start]);
        $invoiceId=(int)$db->lastInsertId();
        $update=$db->prepare(
            "UPDATE platform_subscription_change_requests
             SET status='invoiced',approved_price_monthly_rial=?,decided_by=?,decided_at=UTC_TIMESTAMP()
             WHERE tenant_id=? AND id=? AND status='pending'"
        );
        $update->execute([$monthly,(int)$session['id'],(int)$request['tenant_id'],$requestId]);
        app_audit_log($db,(int)$actorTenant['id'],(int)$session['id'],'platform.subscription.plan_change_approved','subscription_change_request',$requestId,[
            'tenant_id'=>(int)$request['tenant_id'],'invoice_id'=>$invoiceId,'plan_id'=>(int)$request['requested_plan_id'],
            'billing_period_months'=>$months,'monthly_price_rial'=>$monthly,'invoice_amount_rial'=>$amount
        ]);
        app_notification_notify_role($db,(int)$request['tenant_id'],'store_owner','platform-plan-change-invoice-'.$requestId,'system',
            'درخواست تغییر پلن تأیید شد','فاکتور پلن جدید صادر شد؛ پس از پرداخت کامل، پلن در اشتراک شما اعمال می‌شود.','platform_invoice',$invoiceId);
        $db->commit();
        app_json(['data'=>['request_id'=>$requestId,'invoice_id'=>$invoiceId,'status'=>'invoiced'],'message'=>'درخواست تأیید شد و فاکتور دوره بعد صادر شد.'],200);
    }catch(PDOException $e){if($db->inTransaction())$db->rollBack();if((string)$e->getCode()==='23000')app_json(['error'=>'برای این درخواست فاکتور صادر شده است.'],409);throw $e;}
    catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

function app_platform_subscription_change_reject(PDO $db,array $actorTenant,array $session,int $requestId): never {
    $scope=$db->prepare('SELECT tenant_id FROM platform_subscription_change_requests WHERE id=?');
    $scope->execute([$requestId]);$tenantId=$scope->fetchColumn();
    if(!$tenantId)app_json(['error'=>'درخواست تغییر پلن پیدا نشد.'],404);
    $db->beginTransaction();
    try{
        $tenantLock=$db->prepare('SELECT id FROM tenants WHERE id=? FOR UPDATE');$tenantLock->execute([(int)$tenantId]);
        $lock=$db->prepare("SELECT id,status FROM platform_subscription_change_requests WHERE tenant_id=? AND id=? FOR UPDATE");
        $lock->execute([(int)$tenantId,$requestId]);$request=$lock->fetch(PDO::FETCH_ASSOC);
        if(!$request||$request['status']!=='pending'){$db->rollBack();app_json(['error'=>'فقط درخواست در انتظار قابل رد است.'],409);}
        $update=$db->prepare("UPDATE platform_subscription_change_requests SET status='rejected',decided_by=?,decided_at=UTC_TIMESTAMP() WHERE tenant_id=? AND id=? AND status='pending'");
        $update->execute([(int)$session['id'],(int)$tenantId,$requestId]);
        app_audit_log($db,(int)$actorTenant['id'],(int)$session['id'],'platform.subscription.plan_change_rejected','subscription_change_request',$requestId,['tenant_id'=>(int)$tenantId]);
        app_notification_notify_role($db,(int)$tenantId,'store_owner','platform-plan-change-rejected-'.$requestId,'system',
            'درخواست تغییر پلن رد شد','درخواست تغییر پلن فروشگاه تأیید نشد. برای جزئیات با پشتیبانی تماس بگیرید.','subscription_change_request',$requestId);
        $db->commit();
        app_json(['data'=>['request_id'=>$requestId,'status'=>'rejected'],'message'=>'درخواست رد شد.']);
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
