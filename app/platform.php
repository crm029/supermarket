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

function app_platform_audit_list(PDO $db): never {
    $stmt=$db->query(
        "SELECT id,action,entity_type,entity_id,details,created_at
         FROM audit_logs
         WHERE action LIKE 'platform.%'
         ORDER BY id DESC LIMIT 200"
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
          (SELECT COUNT(*) FROM tenant_domains d WHERE d.tenant_id=t.id AND d.verified_at IS NOT NULL) AS verified_domain_count
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

    $db->beginTransaction();
    try{
        $tenantInsert=$db->prepare("INSERT INTO tenants (name,slug,status,timezone,currency) VALUES (?,?,'pending',?,'IRR')");
        $tenantInsert->execute([$name,$slug,$timezone]);
        $tenantId=(int)$db->lastInsertId();

        $domain=$db->prepare('INSERT INTO tenant_domains (tenant_id,host,is_primary,verified_at) VALUES (?,?,1,NULL)');
        $domain->execute([$tenantId,$host]);

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
            'tenant_id'=>$tenantId,'owner_user_id'=>$ownerId,'domain'=>$host,'status'=>'pending'
        ],'message'=>'فروشگاه ساخته شد؛ تا تأیید مالکیت دامنه فعال نخواهد شد.'],201);
    }catch(PDOException $e){
        if($db->inTransaction())$db->rollBack();
        if((string)$e->getCode()==='23000')app_json(['error'=>'شناسه فروشگاه یا دامنه قبلاً ثبت شده است.'],409);
        throw $e;
    }catch(Throwable $e){
        if($db->inTransaction())$db->rollBack();
        throw $e;
    }
}

function app_platform_tenant_verify_domain(PDO $db, array $actorTenant, array $session, int $tenantId): never {
    $body=app_auth_request_body();
    $confirmed=$body['ownership_confirmed']??false;
    $method=trim((string)($body['verification_method']??''));
    $note=trim((string)($body['verification_note']??''));
    if($confirmed!==true||!in_array($method,['dns_txt','http_file','manual'],true)||mb_strlen($note)<12||mb_strlen($note)>500){
        app_json(['error'=>'تأیید صریح مالکیت دامنه، روش بررسی و یادداشت حداقل ۱۲ حرفی الزامی است.'],422);
    }
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
        if($domain['status']==='suspended'){
            $db->rollBack();
            app_json(['error'=>'فروشگاه تعلیق شده است و با تأیید دامنه فعال نمی‌شود.'],409);
        }
        if($domain['verified_at']!==null){
            $db->rollBack();
            app_json(['error'=>'دامنه قبلاً تأیید شده است.'],409);
        }
        $verify=$db->prepare('UPDATE tenant_domains SET verified_at=UTC_TIMESTAMP() WHERE id=? AND tenant_id=? AND verified_at IS NULL');
        $verify->execute([$domain['id'],$tenantId]);
        $activate=$db->prepare("UPDATE tenants SET status='active' WHERE id=? AND status='pending'");
        $activate->execute([$tenantId]);
        app_audit_log($db,(int)$actorTenant['id'],(int)$session['id'],'platform.tenant.domain_verified','tenant_domain',(int)$domain['id'],[
            'target_tenant_id'=>$tenantId,'host'=>$domain['host'],
            'verification_method'=>$method,'verification_note'=>$note
        ]);
        $db->commit();
        app_json(['data'=>['tenant_id'=>$tenantId,'domain'=>$domain['host'],'status'=>'active'],
            'message'=>'تأیید دستی دامنه ثبت شد و فروشگاه فعال شد.']);
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
