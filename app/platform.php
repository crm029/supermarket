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
