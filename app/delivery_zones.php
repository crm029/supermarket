<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/policies.php';

function app_delivery_zone_payload(array $body, bool $partial = false): array {
    $data = [];
    foreach (['title'=>120,'province'=>100,'city'=>100] as $field=>$max) {
        if (!$partial || array_key_exists($field,$body)) {
            $value=trim((string)($body[$field]??''));
            if ($value==='' || mb_strlen($value)>$max) app_json(['error'=>'نام محدوده، استان و شهر الزامی و معتبر هستند.'],422);
            $data[$field]=$value;
        }
    }
    if (!$partial || array_key_exists('postal_prefix',$body)) {
        $prefix=preg_replace('/\\s+/','',(string)($body['postal_prefix']??''));
        if (mb_strlen($prefix)>10 || ($prefix!=='' && !preg_match('/^[0-9]+$/',$prefix))) {
            app_json(['error'=>'پیش‌شماره کد پستی باید حداکثر ۱۰ رقم باشد.'],422);
        }
        $data['postal_prefix']=$prefix;
    }
    foreach (['delivery_fee_rial','minimum_order_rial'] as $field) {
        if (!$partial || array_key_exists($field,$body)) {
            $value=filter_var($body[$field]??0,FILTER_VALIDATE_INT);
            if ($value===false || $value<0 || $value>10000000000) app_json(['error'=>'هزینه ارسال یا حداقل سفارش معتبر نیست.'],422);
            $data[$field]=$value;
        }
    }
    if (!$partial || array_key_exists('sort_order',$body)) {
        $sort=filter_var($body['sort_order']??0,FILTER_VALIDATE_INT);
        if ($sort===false || $sort<0 || $sort>100000) app_json(['error'=>'ترتیب نمایش معتبر نیست.'],422);
        $data['sort_order']=$sort;
    }
    if (!$partial || array_key_exists('is_active',$body)) {
        $active=$body['is_active']??true;
        if (!is_bool($active)) app_json(['error'=>'وضعیت محدوده معتبر نیست.'],422);
        $data['is_active']=$active?1:0;
    }
    return $data;
}

function app_admin_delivery_zone_list(PDO $db, array $tenant): never {
    $stmt=$db->prepare(
        'SELECT id,title,province,city,postal_prefix,delivery_fee_rial,minimum_order_rial,sort_order,is_active
         FROM delivery_zones WHERE tenant_id=? ORDER BY sort_order,id'
    );
    $stmt->execute([$tenant['id']]);
    app_json(['data'=>$stmt->fetchAll()]);
}

function app_admin_delivery_zone_create(PDO $db, array $tenant, array $session): never {
    $data=app_delivery_zone_payload(app_auth_request_body());
    try {
        $stmt=$db->prepare(
            'INSERT INTO delivery_zones
             (tenant_id,title,province,city,postal_prefix,delivery_fee_rial,minimum_order_rial,sort_order,is_active)
             VALUES (?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $tenant['id'],$data['title'],$data['province'],$data['city'],$data['postal_prefix'],
            $data['delivery_fee_rial'],$data['minimum_order_rial'],$data['sort_order'],$data['is_active']
        ]);
    } catch(PDOException $e) {
        if ((string)$e->getCode()==='23000' && (int)($e->errorInfo[1]??0)===1062) app_json(['error'=>'این محدوده با همین پیش‌شماره قبلاً ثبت شده است.'],409);
        throw $e;
    }
    $id=(int)$db->lastInsertId();
    app_audit_log($db,(int)$tenant['id'],(int)$session['id'],'delivery_zone.created','delivery_zone',$id);
    app_json(['data'=>['id'=>$id],'message'=>'محدوده ارسال ثبت شد.'],201);
}

function app_admin_delivery_zone_update(PDO $db, array $tenant, array $session, int $id): never {
    $body=app_auth_request_body();
    $currentQuery=$db->prepare('SELECT title,province,city,postal_prefix,delivery_fee_rial,minimum_order_rial,sort_order,is_active FROM delivery_zones WHERE tenant_id=? AND id=? LIMIT 1');
    $currentQuery->execute([$tenant['id'],$id]);
    $current=$currentQuery->fetch(PDO::FETCH_ASSOC);
    if(!$current)app_json(['error'=>'محدوده ارسال پیدا نشد.'],404);
    $data=app_delivery_zone_payload(array_merge($current,$body));
    try {
        $stmt=$db->prepare(
            'UPDATE delivery_zones SET title=?,province=?,city=?,postal_prefix=?,delivery_fee_rial=?,minimum_order_rial=?,sort_order=?,is_active=?
             WHERE tenant_id=? AND id=?'
        );
        $stmt->execute([
            $data['title'],$data['province'],$data['city'],$data['postal_prefix'],$data['delivery_fee_rial'],
            $data['minimum_order_rial'],$data['sort_order'],$data['is_active'],$tenant['id'],$id
        ]);
    } catch(PDOException $e) {
        if ((string)$e->getCode()==='23000' && (int)($e->errorInfo[1]??0)===1062) app_json(['error'=>'این محدوده با همین پیش‌شماره قبلاً ثبت شده است.'],409);
        throw $e;
    }
    app_audit_log($db,(int)$tenant['id'],(int)$session['id'],'delivery_zone.updated','delivery_zone',$id,['fields'=>array_keys($body)]);
    app_json(['message'=>'محدوده ارسال ویرایش شد.']);
}

function app_admin_delivery_zone_disable(PDO $db, array $tenant, array $session, int $id): never {
    $stmt=$db->prepare('UPDATE delivery_zones SET is_active=0 WHERE tenant_id=? AND id=? AND is_active=1');
    $stmt->execute([$tenant['id'],$id]);
    if($stmt->rowCount()!==1)app_json(['error'=>'محدوده فعال پیدا نشد.'],404);
    app_audit_log($db,(int)$tenant['id'],(int)$session['id'],'delivery_zone.disabled','delivery_zone',$id);
    app_json(['data'=>['id'=>$id],'message'=>'محدوده ارسال غیرفعال شد.']);
}
