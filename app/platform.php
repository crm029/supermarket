<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/policies.php';

function app_platform_normalize_host(mixed $value): ?string {
    if(!is_string($value))return null;
    $host=strtolower(rtrim(trim($value),'.'));
    if($host===''||strlen($host)>253||str_contains($host,'://')||str_contains($host,'/')||
       str_contains($host,':')||str_contains($host,'@')||filter_var($host,FILTER_VALIDATE_IP)||
       $host==='localhost'||!str_contains($host,'.'))return null;
    foreach(explode('.',$host) as $label){
        if($label===''||strlen($label)>63||!preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/',$label))return null;
    }
    return $host;
}

function app_platform_domain_txt_name(string $host): string {
    return '_supermarket360-verification.'.$host;
}

function app_platform_new_domain_token(): string {
    if(strtolower((string)app_env('APP_ENV',''))==='testing'){
        $fixture=trim((string)app_env('TEST_DOMAIN_VERIFICATION_TOKEN',''));
        if($fixture!=='')return $fixture;
    }
    return bin2hex(random_bytes(24));
}

/** Returns TXT values, or null when DNS lookup is unavailable. */
function app_platform_dns_txt_values(string $host): ?array {
    $name=app_platform_domain_txt_name($host);
    if(strtolower((string)app_env('APP_ENV',''))==='testing'){
        $fixtureHost=strtolower(trim((string)app_env('TEST_DOMAIN_VERIFICATION_HOST','')));
        $fixtureToken=trim((string)app_env('TEST_DOMAIN_VERIFICATION_TOKEN',''));
        if($fixtureHost===$host&&$fixtureToken!=='')return [$fixtureToken];
    }
    if(!function_exists('dns_get_record'))return null;
    $records=@dns_get_record($name,DNS_TXT);
    if(!is_array($records))return [];
    $values=[];
    foreach($records as $record){
        if(isset($record['txt'])&&is_string($record['txt']))$values[]=trim($record['txt']);
        elseif(isset($record['entries'])&&is_array($record['entries']))$values[]=trim(implode('',$record['entries']));
    }
    return $values;
}

function app_platform_audit_list(PDO $db): never {
    $stmt=$db->query(
        "SELECT a.id,a.action,a.entity_type,a.entity_id,a.details,a.created_at,u.full_name AS actor_name
         FROM audit_logs a
         LEFT JOIN users u ON u.tenant_id=a.tenant_id AND u.id=a.actor_user_id
         WHERE a.action LIKE 'platform.%'
         ORDER BY a.id DESC LIMIT 200"
    );
    $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($rows as &$row){
        $row['id']=(int)$row['id'];
        $row['entity_id']=$row['entity_id']===null?null:(int)$row['entity_id'];
        $row['details']=$row['details']===null?null:json_decode((string)$row['details'],true);
    }
    unset($row);
    app_json(['data'=>$rows]);
}

function app_platform_tenant_list(PDO $db): never {
    $stmt=$db->query(
        "SELECT t.id,t.name,t.slug,t.status,t.timezone,t.created_at,
          (SELECT GROUP_CONCAT(d.host ORDER BY d.is_primary DESC,d.id SEPARATOR ',')
             FROM tenant_domains d WHERE d.tenant_id=t.id) AS domains,
          (SELECT COUNT(*) FROM users u WHERE u.tenant_id=t.id) AS user_count,
          (SELECT COUNT(*) FROM orders o WHERE o.tenant_id=t.id) AS order_count,
          (SELECT COUNT(*) FROM tenant_domains d WHERE d.tenant_id=t.id AND d.verified_at IS NOT NULL) AS verified_domain_count,
          (SELECT d.verification_expires_at FROM tenant_domains d WHERE d.tenant_id=t.id AND d.is_primary=1 LIMIT 1) AS verification_expires_at,
          (SELECT d.verification_attempts FROM tenant_domains d WHERE d.tenant_id=t.id AND d.is_primary=1 LIMIT 1) AS verification_attempts
         FROM tenants t ORDER BY t.id DESC LIMIT 500"
    );
    $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($rows as &$row){
        $row['id']=(int)$row['id'];
        $row['user_count']=(int)$row['user_count'];
        $row['order_count']=(int)$row['order_count'];
        $row['verified_domain_count']=(int)$row['verified_domain_count'];
        $row['domains']=$row['domains']?explode(',',$row['domains']):[];
    }
    unset($row);
    app_json(['data'=>$rows]);
}

function app_platform_tenant_create(PDO $db, array $actorTenant, array $session): never {
    $body=app_auth_request_body();
    $name=trim((string)($body['name']??''));
    $slug=strtolower(trim((string)($body['slug']??'')));
    $host=app_platform_normalize_host($body['host']??null);
    $ownerName=trim((string)($body['owner_name']??''));
    $ownerMobile=app_auth_mobile((string)($body['owner_mobile']??''));
    $timezone=trim((string)($body['timezone']??'Asia/Tehran'));
    if($name===''||mb_strlen($name)>160||
       !preg_match('/^[a-z0-9](?:[a-z0-9-]{0,78}[a-z0-9])$/',$slug)||
       !$host||$host===app_host()||
       $ownerName===''||mb_strlen($ownerName)>160||$ownerMobile===null||
       !in_array($timezone,DateTimeZone::listIdentifiers(),true)){
        app_json(['error'=>'نام فروشگاه، شناسه، دامنه، مالک یا منطقه زمانی معتبر نیست.'],422);
    }

    $verificationToken=app_platform_new_domain_token();
    $verificationExpiresAt=gmdate('c',time()+86400);
    $db->beginTransaction();
    try{
        $tenantInsert=$db->prepare("INSERT INTO tenants (name,slug,status,timezone,currency) VALUES (?,?,'pending',?,'IRR')");
        $tenantInsert->execute([$name,$slug,$timezone]);
        $tenantId=(int)$db->lastInsertId();

        $domain=$db->prepare(
            'INSERT INTO tenant_domains
             (tenant_id,host,is_primary,verified_at,verification_token_hash,verification_expires_at,verification_attempts)
             VALUES (?,?,1,NULL,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 24 HOUR),0)'
        );
        $domain->execute([$tenantId,$host,hash('sha256',$verificationToken)]);

        $referral=strtoupper(substr(bin2hex(random_bytes(8)),0,10));
        $owner=$db->prepare(
            "INSERT INTO users (tenant_id,full_name,mobile,status,referral_code)
             VALUES (?, ?, ?, 'pending', ?)"
        );
        $owner->execute([$tenantId,$ownerName,$ownerMobile,$referral]);
        $ownerId=(int)$db->lastInsertId();

        $role=$db->prepare("INSERT INTO roles (tenant_id,role_key,title) VALUES (?,'store_owner','مدیر کل فروشگاه')");
        $role->execute([$tenantId]);
        $roleId=(int)$db->lastInsertId();

        $grant=$db->prepare(
            "INSERT INTO role_permissions (role_scope_id,role_id,permission_id)
             SELECT ?,?,id FROM permissions WHERE scope='tenant'"
        );
        $grant->execute([$tenantId,$roleId]);

        $assign=$db->prepare('INSERT INTO user_roles (tenant_id,role_scope_id,user_id,role_id) VALUES (?,?,?,?)');
        $assign->execute([$tenantId,$tenantId,$ownerId,$roleId]);

        $warehouse=$db->prepare("INSERT INTO warehouses (tenant_id,title,warehouse_type,is_active) VALUES (?,'انبار اصلی','main',1)");
        $warehouse->execute([$tenantId]);

        $settings=$db->prepare(
            "INSERT INTO store_settings (tenant_id,setting_key,setting_value) VALUES
             (?, 'store.profile', JSON_OBJECT('name',?,'currency','IRR')),
             (?, 'store.checkout', JSON_OBJECT('guest_checkout',false,'reservation_minutes',20))"
        );
        $settings->execute([$tenantId,$name,$tenantId]);

        app_audit_log($db,(int)$actorTenant['id'],(int)$session['id'],'platform.tenant.created','tenant',$tenantId,[
            'slug'=>$slug,'host'=>$host,'owner_user_id'=>$ownerId
        ]);
        $db->commit();
        app_json(['data'=>[
            'tenant_id'=>$tenantId,'owner_user_id'=>$ownerId,'domain'=>$host,'status'=>'pending',
            'verification'=>['record_type'=>'TXT','record_name'=>app_platform_domain_txt_name($host),'record_value'=>$verificationToken,'expires_at'=>$verificationExpiresAt]
        ],'message'=>'فروشگاه ساخته شد؛ پس از ثبت رکورد DNS TXT و تأیید خودکار دامنه فعال می‌شود.'],201);
    }catch(PDOException $e){
        if($db->inTransaction())$db->rollBack();
        if((string)$e->getCode()==='23000')app_json(['error'=>'شناسه فروشگاه یا دامنه قبلاً ثبت شده است.'],409);
        throw $e;
    }catch(Throwable $e){
        if($db->inTransaction())$db->rollBack();
        throw $e;
    }
}

function app_platform_issue_domain_challenge(PDO $db, array $actorTenant, array $session, int $tenantId): never {
    $token=app_platform_new_domain_token();
    $expiresAt=gmdate('c',time()+86400);
    $db->beginTransaction();
    try{
        $stmt=$db->prepare(
            "SELECT d.id,d.host,d.verified_at,t.status
             FROM tenant_domains d JOIN tenants t ON t.id=d.tenant_id
             WHERE d.tenant_id=? AND d.is_primary=1 FOR UPDATE"
        );
        $stmt->execute([$tenantId]);
        $domain=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$domain){
            $db->rollBack();
            app_json(['error'=>'دامنه اصلی فروشگاه پیدا نشد.'],404);
        }
        if($domain['status']!=='pending'||$domain['verified_at']!==null){
            $db->rollBack();
            app_json(['error'=>'فقط فروشگاه در انتظار با دامنه تأییدنشده می‌تواند چالش جدید دریافت کند.'],409);
        }
        $update=$db->prepare(
            'UPDATE tenant_domains
             SET verification_token_hash=?,verification_expires_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 24 HOUR),verification_attempts=0
             WHERE id=? AND tenant_id=?'
        );
        $update->execute([hash('sha256',$token),$domain['id'],$tenantId]);
        app_audit_log($db,(int)$actorTenant['id'],(int)$session['id'],'platform.tenant.domain_challenge_issued','tenant_domain',(int)$domain['id'],[
            'target_tenant_id'=>$tenantId,'host'=>$domain['host']
        ]);
        $db->commit();
        app_json(['data'=>[
            'tenant_id'=>$tenantId,'record_type'=>'TXT',
            'record_name'=>app_platform_domain_txt_name($domain['host']),
            'record_value'=>$token,'expires_at'=>$expiresAt
        ],'message'=>'رکورد TXT جدید صادر شد؛ پس از انتشار DNS آن را بررسی کنید.']);
    }catch(Throwable $e){
        if($db->inTransaction())$db->rollBack();
        throw $e;
    }
}

function app_platform_tenant_verify_domain(PDO $db, array $actorTenant, array $session, int $tenantId): never {
    $db->beginTransaction();
    try{
        $stmt=$db->prepare(
            "SELECT d.id,d.host,d.verified_at,d.verification_token_hash,d.verification_expires_at,
                    d.verification_attempts,t.status
             FROM tenant_domains d JOIN tenants t ON t.id=d.tenant_id
             WHERE d.tenant_id=? AND d.is_primary=1 FOR UPDATE"
        );
        $stmt->execute([$tenantId]);
        $domain=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$domain){
            $db->rollBack();
            app_json(['error'=>'دامنه اصلی فروشگاه پیدا نشد.'],404);
        }
        if($domain['status']==='suspended'){
            $db->rollBack();
            app_json(['error'=>'فروشگاه تعلیق شده است و با تأیید دامنه فعال نمی‌شود.'],409);
        }
        if($domain['verified_at']!==null){
            $db->rollBack();
            app_json(['error'=>'دامنه قبلاً تأیید شده است.'],409);
        }
        if(empty($domain['verification_token_hash'])||empty($domain['verification_expires_at'])){
            $db->rollBack();
            app_json(['error'=>'چالش تأیید دامنه صادر نشده است؛ ابتدا رکورد TXT را ایجاد کنید.'],409);
        }
        if(strtotime((string)$domain['verification_expires_at'].' UTC')<time()){
            $db->rollBack();
            app_json(['error'=>'مهلت رکورد تأیید تمام شده است؛ یک رکورد جدید صادر کنید.'],410);
        }
        if((int)$domain['verification_attempts']>=10){
            $db->rollBack();
            app_json(['error'=>'حداکثر تلاش برای بررسی DNS انجام شده است؛ رکورد جدید صادر کنید.'],429);
        }
        $values=app_platform_dns_txt_values((string)$domain['host']);
        if($values===null){
            $db->rollBack();
            app_json(['error'=>'بررسی DNS TXT روی این سرور در دسترس نیست.'],503);
        }
        $matched=false;
        foreach($values as $value){
            if(hash_equals((string)$domain['verification_token_hash'],hash('sha256',(string)$value))){
                $matched=true;break;
            }
        }
        if(!$matched){
            $attempt=$db->prepare('UPDATE tenant_domains SET verification_attempts=verification_attempts+1 WHERE id=? AND tenant_id=?');
            $attempt->execute([$domain['id'],$tenantId]);
            $db->commit();
            app_json(['error'=>'رکورد DNS TXT هنوز با مقدار مورد انتظار مطابقت ندارد. پس از انتشار DNS دوباره تلاش کنید.'],422);
        }
        $verify=$db->prepare(
            'UPDATE tenant_domains
             SET verified_at=UTC_TIMESTAMP(),verification_token_hash=NULL,verification_expires_at=NULL
             WHERE id=? AND tenant_id=? AND verified_at IS NULL'
        );
        $verify->execute([$domain['id'],$tenantId]);
        $activate=$db->prepare("UPDATE tenants SET status='active' WHERE id=? AND status='pending'");
        $activate->execute([$tenantId]);
        app_audit_log($db,(int)$actorTenant['id'],(int)$session['id'],'platform.tenant.domain_verified','tenant_domain',(int)$domain['id'],[
            'target_tenant_id'=>$tenantId,'host'=>$domain['host'],
            'verification_method'=>'dns_txt','record_name'=>app_platform_domain_txt_name((string)$domain['host'])
        ]);
        $db->commit();
        app_json(['data'=>['tenant_id'=>$tenantId,'domain'=>$domain['host'],'status'=>'active'],
            'message'=>'رکورد DNS TXT تأیید شد و فروشگاه فعال شد.']);
    }catch(Throwable $e){
        if($db->inTransaction())$db->rollBack();
        throw $e;
    }
}

function app_platform_tenant_suspend(PDO $db, array $actorTenant, array $session, int $tenantId): never {
    $body=app_auth_request_body();
    $reason=trim((string)($body['reason']??''));
    if($tenantId===(int)$actorTenant['id'])app_json(['error'=>'برای جلوگیری از قطع دسترسی، فروشگاه میزبان حساب فعلی را نمی‌توانید تعلیق کنید.'],409);
    if($reason===''||mb_strlen($reason)>500)app_json(['error'=>'دلیل تعلیق الزامی است.'],422);
    $db->beginTransaction();
    try{
        $lock=$db->prepare('SELECT status FROM tenants WHERE id=? FOR UPDATE');
        $lock->execute([$tenantId]);
        $status=$lock->fetchColumn();
        if($status!=='active'){
            $db->rollBack();
            app_json(['error'=>'فروشگاه فعال پیدا نشد.'],404);
        }
        $stmt=$db->prepare("UPDATE tenants SET status='suspended' WHERE id=? AND status='active'");
        $stmt->execute([$tenantId]);
        if($stmt->rowCount()!==1){
            $db->rollBack();
            app_json(['error'=>'وضعیت فروشگاه تغییر کرده است؛ دوباره تلاش کنید.'],409);
        }
        app_audit_log($db,(int)$actorTenant['id'],(int)$session['id'],'platform.tenant.suspended','tenant',$tenantId,[
            'reason'=>$reason
        ]);
        $db->commit();
        app_json(['data'=>['tenant_id'=>$tenantId,'status'=>'suspended'],'message'=>'فروشگاه تعلیق شد.']);
    }catch(Throwable $e){
        if($db->inTransaction())$db->rollBack();
        throw $e;
    }
}


// --- SaaS plans, subscriptions and invoice ledger ----------------------------

function app_platform_billing_months(mixed $value): ?int {
    $months=filter_var($value,FILTER_VALIDATE_INT);
    return in_array($months,[1,3,12],true)?$months:null;
}

function app_platform_billing_summary(PDO $db): never {
    $plans=$db->query("SELECT id,code,name,description,monthly_price_rial,limits_json,is_active,sort_order FROM platform_plans ORDER BY sort_order,id")->fetchAll(PDO::FETCH_ASSOC);
    foreach($plans as &$plan){
        $plan['id']=(int)$plan['id'];
        $plan['monthly_price_rial']=(int)$plan['monthly_price_rial'];
        $plan['is_active']=(bool)$plan['is_active'];
        $plan['limits']=json_decode((string)$plan['limits_json'],true)?:new stdClass();
        unset($plan['limits_json']);
    }
    unset($plan);
    $tenants=$db->query(
        "SELECT t.id,t.name,t.slug,t.status,
          s.id AS subscription_id,s.status AS subscription_status,s.plan_id,s.billing_period_months,
          s.price_monthly_rial,s.starts_at,s.current_period_start,s.current_period_end,
          p.name AS plan_name,
          (SELECT i.id FROM platform_invoices i WHERE i.tenant_id=t.id AND i.subscription_id=s.id AND i.status='issued' ORDER BY i.id DESC LIMIT 1) AS open_invoice_id
         FROM tenants t
         LEFT JOIN tenant_subscriptions s ON s.id=(
           SELECT s2.id FROM tenant_subscriptions s2 WHERE s2.tenant_id=t.id
           ORDER BY CASE s2.status WHEN 'active' THEN 0 WHEN 'pending_payment' THEN 1 WHEN 'past_due' THEN 2 ELSE 3 END,s2.id DESC LIMIT 1
         )
         LEFT JOIN platform_plans p ON p.id=s.plan_id
         ORDER BY t.id DESC LIMIT 500"
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach($tenants as &$tenant){
        foreach(['id','subscription_id','plan_id','billing_period_months','price_monthly_rial','open_invoice_id'] as $key){
            $tenant[$key]=$tenant[$key]===null?null:(int)$tenant[$key];
        }
    }
    unset($tenant);
    $invoices=$db->query(
        "SELECT i.id,i.tenant_id,i.subscription_id,i.invoice_number,i.period_start,i.period_end,
          i.amount_rial,i.status,i.due_at,i.paid_at,t.name AS tenant_name,
          COALESCE(SUM(pay.amount_rial),0) AS paid_rial
         FROM platform_invoices i JOIN tenants t ON t.id=i.tenant_id
         LEFT JOIN platform_invoice_payments pay ON pay.tenant_id=i.tenant_id AND pay.invoice_id=i.id
         GROUP BY i.id,i.tenant_id,i.subscription_id,i.invoice_number,i.period_start,i.period_end,
          i.amount_rial,i.status,i.due_at,i.paid_at,t.name
         ORDER BY i.id DESC LIMIT 200"
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach($invoices as &$invoice){
        foreach(['id','tenant_id','subscription_id','amount_rial','paid_rial'] as $key)$invoice[$key]=(int)$invoice[$key];
    }
    unset($invoice);
    $totals=$db->query(
        "SELECT COUNT(*) AS invoice_count,
          COALESCE(SUM(CASE WHEN status='paid' THEN amount_rial ELSE 0 END),0) AS collected_rial,
          COALESCE(SUM(CASE WHEN status='issued' THEN amount_rial ELSE 0 END),0) AS outstanding_rial
         FROM platform_invoices"
    )->fetch(PDO::FETCH_ASSOC)?:[];
    app_json(['data'=>[
        'plans'=>$plans,'tenants'=>$tenants,'invoices'=>$invoices,
        'totals'=>['invoice_count'=>(int)($totals['invoice_count']??0),'collected_rial'=>(int)($totals['collected_rial']??0),'outstanding_rial'=>(int)($totals['outstanding_rial']??0)]
    ]]);
}

function app_platform_plan_create(PDO $db,array $actorTenant,array $session): never {
    $body=app_auth_request_body();
    $code=strtolower(trim((string)($body['code']??'')));
    $name=trim((string)($body['name']??''));
    $description=trim((string)($body['description']??''));
    $price=filter_var($body['monthly_price_rial']??null,FILTER_VALIDATE_INT);
    $limits=$body['limits']??[];
    if(!preg_match('/^[a-z0-9][a-z0-9_-]{1,39}$/',$code)||$name===''||mb_strlen($name)>120||
       mb_strlen($description)>500||$price===false||$price<0||$price>1000000000000||
       !is_array($limits)||count($limits)>30){
        app_json(['error'=>'اطلاعات پلن معتبر نیست.'],422);
    }
    foreach($limits as $key=>$value){
        if(!is_string($key)||!preg_match('/^[a-z][a-z0-9_]{1,39}$/',$key)||
           !(is_int($value)&&$value>=0)&&!is_bool($value)&&!is_string($value)){
            app_json(['error'=>'محدودیت‌های پلن باید مقادیر ساده و معتبر داشته باشند.'],422);
        }
        if(is_string($value)&&mb_strlen($value)>100)app_json(['error'=>'مقدار محدودیت پلن بیش از حد طولانی است.'],422);
    }
    $stmt=$db->prepare('INSERT INTO platform_plans (code,name,description,monthly_price_rial,limits_json) VALUES (?,?,?,?,?)');
    try{$stmt->execute([$code,$name,$description,$price,json_encode($limits,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);}
    catch(PDOException $e){if((string)$e->getCode()==='23000')app_json(['error'=>'کد این پلن قبلاً ثبت شده است.'],409);throw $e;}
    $id=(int)$db->lastInsertId();
    app_audit_log($db,(int)$actorTenant['id'],(int)$session['id'],'platform.plan.created','platform_plan',$id,['code'=>$code,'monthly_price_rial'=>$price]);
    app_json(['data'=>['id'=>$id],'message'=>'پلن جدید ثبت شد.'],201);
}

function app_platform_plan_update(PDO $db,array $actorTenant,array $session,int $planId): never {
    $body=app_auth_request_body();
    $name=trim((string)($body['name']??''));
    $description=trim((string)($body['description']??''));
    $price=filter_var($body['monthly_price_rial']??null,FILTER_VALIDATE_INT);
    $active=filter_var($body['is_active']??null,FILTER_VALIDATE_BOOLEAN,FILTER_NULL_ON_FAILURE);
    $limits=$body['limits']??[];
    if($name===''||mb_strlen($name)>120||mb_strlen($description)>500||
       $price===false||$price<0||$price>1000000000000||$active===null||!is_array($limits)||count($limits)>30){
        app_json(['error'=>'اطلاعات و وضعیت پلن معتبر نیست.'],422);
    }
    $stmt=$db->prepare('UPDATE platform_plans SET name=?,description=?,monthly_price_rial=?,limits_json=?,is_active=? WHERE id=?');
    $stmt->execute([$name,$description,$price,json_encode($limits,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$active?1:0,$planId]);
    if($stmt->rowCount()===0){
        $exists=$db->prepare('SELECT id FROM platform_plans WHERE id=?');$exists->execute([$planId]);
        if(!$exists->fetchColumn())app_json(['error'=>'پلن پیدا نشد.'],404);
    }
    app_audit_log($db,(int)$actorTenant['id'],(int)$session['id'],'platform.plan.updated','platform_plan',$planId,['monthly_price_rial'=>$price,'is_active'=>$active]);
    app_json(['data'=>['id'=>$planId,'is_active'=>$active],'message'=>'پلن به‌روزرسانی شد.']);
}

function app_platform_invoice_number(): string {
    return 'SM360-'.gmdate('Ymd').'-'.strtoupper(bin2hex(random_bytes(4)));
}

function app_platform_invoice_insert(PDO $db,int $tenantId,int $subscriptionId,int $months,int $amount,array $actorTenant,array $session): int {
    $dates=$db->query("SELECT UTC_TIMESTAMP() AS period_start,DATE_ADD(UTC_TIMESTAMP(),INTERVAL ".$months." MONTH) AS period_end")->fetch(PDO::FETCH_ASSOC);
    $stmt=$db->prepare(
        "INSERT INTO platform_invoices (tenant_id,subscription_id,invoice_number,period_start,period_end,amount_rial,status,due_at,created_by)
         VALUES (?,?,?,?,?,?,'issued',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 7 DAY),?)"
    );
    $stmt->execute([$tenantId,$subscriptionId,app_platform_invoice_number(),$dates['period_start'],$dates['period_end'],$amount,$session['id']]);
    return (int)$db->lastInsertId();
}

function app_platform_subscription_assign(PDO $db,array $actorTenant,array $session,int $tenantId): never {
    $body=app_auth_request_body();
    $planId=filter_var($body['plan_id']??null,FILTER_VALIDATE_INT);
    $months=app_platform_billing_months($body['billing_period_months']??1);
    if($planId===false||$planId<1||$months===null)app_json(['error'=>'پلن یا دوره پرداخت معتبر نیست.'],422);
    $db->beginTransaction();
    try{
        $tenant=$db->prepare('SELECT id,status FROM tenants WHERE id=? FOR UPDATE');$tenant->execute([$tenantId]);$tenantRow=$tenant->fetch(PDO::FETCH_ASSOC);
        if(!$tenantRow){$db->rollBack();app_json(['error'=>'فروشگاه پیدا نشد.'],404);}
        if($tenantRow['status']==='suspended'){$db->rollBack();app_json(['error'=>'برای فروشگاه تعلیق‌شده اشتراک جدید صادر نمی‌شود.'],409);}
        $active=$db->prepare("SELECT id FROM tenant_subscriptions WHERE tenant_id=? AND status IN ('pending_payment','active','past_due') ORDER BY id DESC LIMIT 1 FOR UPDATE");
        $active->execute([$tenantId]);if($active->fetchColumn()){$db->rollBack();app_json(['error'=>'این فروشگاه اشتراک باز دارد؛ ابتدا وضعیت اشتراک فعلی را تعیین کنید.'],409);}
        $plan=$db->prepare('SELECT id,monthly_price_rial FROM platform_plans WHERE id=? AND is_active=1 FOR UPDATE');$plan->execute([$planId]);$planRow=$plan->fetch(PDO::FETCH_ASSOC);
        if(!$planRow){$db->rollBack();app_json(['error'=>'پلن فعال پیدا نشد.'],404);}
        $monthly=(int)$planRow['monthly_price_rial'];$amount=$monthly*$months;
        if($amount<0||$amount>PHP_INT_MAX){$db->rollBack();app_json(['error'=>'مبلغ فاکتور خارج از محدوده مجاز است.'],422);}
        $insert=$db->prepare("INSERT INTO tenant_subscriptions (tenant_id,plan_id,status,billing_period_months,price_monthly_rial,created_by) VALUES (?,?,'pending_payment',?,?,?)");
        $insert->execute([$tenantId,$planId,$months,$monthly,$session['id']]);$subscriptionId=(int)$db->lastInsertId();
        $invoiceId=app_platform_invoice_insert($db,$tenantId,$subscriptionId,$months,$amount,$actorTenant,$session);
        app_audit_log($db,(int)$actorTenant['id'],(int)$session['id'],'platform.subscription.created','tenant_subscription',$subscriptionId,['tenant_id'=>$tenantId,'plan_id'=>$planId,'invoice_id'=>$invoiceId,'amount_rial'=>$amount,'months'=>$months]);
        $db->commit();
        app_json(['data'=>['subscription_id'=>$subscriptionId,'invoice_id'=>$invoiceId,'amount_rial'=>$amount],'message'=>'اشتراک در انتظار پرداخت ایجاد و فاکتور صادر شد.'],201);
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

function app_platform_subscription_renew(PDO $db,array $actorTenant,array $session,int $subscriptionId): never {
    $db->beginTransaction();
    try{
        $stmt=$db->prepare("SELECT id,tenant_id,status,billing_period_months,price_monthly_rial,current_period_end FROM tenant_subscriptions WHERE id=? FOR UPDATE");
        $stmt->execute([$subscriptionId]);$sub=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$sub){$db->rollBack();app_json(['error'=>'اشتراک پیدا نشد.'],404);}
        if(!in_array($sub['status'],['active','past_due'],true)){$db->rollBack();app_json(['error'=>'فقط اشتراک فعال یا سررسیدگذشته قابل تمدید است.'],409);}
        $start=$sub['current_period_end']&&strtotime($sub['current_period_end'].' UTC')>time()?$sub['current_period_end']:gmdate('Y-m-d H:i:s');
        $months=(int)$sub['billing_period_months'];
        $dates=$db->query("SELECT ". $db->quote($start) ." AS period_start,DATE_ADD(". $db->quote($start) .",INTERVAL ".$months." MONTH) AS period_end")->fetch(PDO::FETCH_ASSOC);
        $amount=(int)$sub['price_monthly_rial']*$months;
        $insert=$db->prepare("INSERT INTO platform_invoices (tenant_id,subscription_id,invoice_number,period_start,period_end,amount_rial,status,due_at,created_by) VALUES (?,?,?,?,?,?,'issued',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 7 DAY),?)");
        $insert->execute([(int)$sub['tenant_id'],(int)$sub['id'],app_platform_invoice_number(),$dates['period_start'],$dates['period_end'],$amount,$session['id']]);
        $invoiceId=(int)$db->lastInsertId();
        app_audit_log($db,(int)$actorTenant['id'],(int)$session['id'],'platform.subscription.renewal_invoiced','tenant_subscription',(int)$sub['id'],['tenant_id'=>(int)$sub['tenant_id'],'invoice_id'=>$invoiceId,'amount_rial'=>$amount,'months'=>$months]);
        $db->commit();app_json(['data'=>['invoice_id'=>$invoiceId,'amount_rial'=>$amount],'message'=>'فاکتور تمدید صادر شد.'],201);
    }catch(PDOException $e){if($db->inTransaction())$db->rollBack();if((string)$e->getCode()==='23000')app_json(['error'=>'برای این دوره قبلاً فاکتور تمدید صادر شده است.'],409);throw $e;}
    catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

function app_platform_invoice_record_payment(PDO $db,array $actorTenant,array $session,int $invoiceId): never {
    $body=app_auth_request_body();
    $amount=filter_var($body['amount_rial']??null,FILTER_VALIDATE_INT);
    $method=(string)($body['method']??'bank_transfer');
    $reference=trim((string)($body['reference']??''));
    $note=trim((string)($body['note']??''));
    $key=trim((string)($body['idempotency_key']??''));
    if($amount===false||$amount<1||$amount>1000000000000||!in_array($method,['bank_transfer','cash','other'],true)||
       mb_strlen($reference)>120||mb_strlen($note)>500||!preg_match('/^[A-Za-z0-9._:-]{16,100}$/',$key)){
        app_json(['error'=>'مبلغ، روش دریافت، شناسه پیگیری یا کلید یکتا معتبر نیست.'],422);
    }
    $db->beginTransaction();
    try{
        $stmt=$db->prepare("SELECT id,tenant_id,subscription_id,amount_rial,status FROM platform_invoices WHERE id=? FOR UPDATE");
        $stmt->execute([$invoiceId]);$invoice=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$invoice){$db->rollBack();app_json(['error'=>'فاکتور پیدا نشد.'],404);}
        if($invoice['status']!=='issued'){$db->rollBack();app_json(['error'=>'این فاکتور دیگر قابل دریافت وجه نیست.'],409);}
        $sum=$db->prepare('SELECT COALESCE(SUM(amount_rial),0) FROM platform_invoice_payments WHERE tenant_id=? AND invoice_id=?');
        $sum->execute([$invoice['tenant_id'],$invoiceId]);$paid=(int)$sum->fetchColumn();
        $due=(int)$invoice['amount_rial']-$paid;
        if($amount>$due){$db->rollBack();app_json(['error'=>'مبلغ دریافت از مانده فاکتور بیشتر است.'],422);}
        $payment=$db->prepare("INSERT INTO platform_invoice_payments (tenant_id,invoice_id,amount_rial,method,reference,idempotency_key,note,received_by) VALUES (?,?,?,?,?,?,?,?)");
        $payment->execute([(int)$invoice['tenant_id'],$invoiceId,$amount,$method,$reference,$key,$note,(int)$session['id']]);
        $newPaid=$paid+$amount;$fullyPaid=$newPaid===(int)$invoice['amount_rial'];
        if($fullyPaid){
            $update=$db->prepare("UPDATE platform_invoices SET status='paid',paid_at=UTC_TIMESTAMP() WHERE id=? AND tenant_id=? AND status='issued'");
            $update->execute([$invoiceId,$invoice['tenant_id']]);
            $activate=$db->prepare("UPDATE tenant_subscriptions SET status='active',starts_at=COALESCE(starts_at,?),current_period_start=COALESCE(current_period_start,?),current_period_end=CASE WHEN current_period_end IS NULL OR current_period_end<? THEN ? ELSE current_period_end END WHERE id=? AND tenant_id=?");
            $period=$db->prepare('SELECT period_start,period_end FROM platform_invoices WHERE id=?');$period->execute([$invoiceId]);$dates=$period->fetch(PDO::FETCH_ASSOC);
            $activate->execute([$dates['period_start'],$dates['period_start'],$dates['period_end'],$dates['period_end'],$invoice['subscription_id'],$invoice['tenant_id']]);
        }
        app_audit_log($db,(int)$actorTenant['id'],(int)$session['id'],'platform.invoice.payment_recorded','platform_invoice',$invoiceId,['tenant_id'=>(int)$invoice['tenant_id'],'amount_rial'=>$amount,'method'=>$method,'reference'=>$reference,'fully_paid'=>$fullyPaid]);
        $db->commit();
        app_json(['data'=>['invoice_id'=>$invoiceId,'paid_rial'=>$newPaid,'remaining_rial'=>(int)$invoice['amount_rial']-$newPaid,'status'=>$fullyPaid?'paid':'issued'],'message'=>$fullyPaid?'دریافت کامل ثبت شد و اشتراک فعال شد.':'دریافت جزئی ثبت شد.']);
    }catch(PDOException $e){if($db->inTransaction())$db->rollBack();if((string)$e->getCode()==='23000')app_json(['error'=>'این کلید یکتا قبلاً استفاده شده است.'],409);throw $e;}
    catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
