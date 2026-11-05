<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/policies.php';

function app_coupon_code(string $value): string {
    return strtoupper(trim($value));
}

function app_coupon_local_datetime(string $value, array $tenant): ?DateTimeImmutable {
    return app_pickup_parse_local_datetime($value, app_pickup_timezone($tenant));
}

function app_coupon_datetime_label(string $value, array $tenant): string {
    $utc = new DateTimeZone('UTC');
    return (new DateTimeImmutable($value, $utc))->setTimezone(app_pickup_timezone($tenant))->format('Y-m-d\\TH:i');
}

function app_coupon_payload(array $body, array $tenant): array {
    $code = app_coupon_code((string)($body['code'] ?? ''));
    $title = trim((string)($body['title'] ?? ''));
    $type = (string)($body['discount_type'] ?? '');
    $value = filter_var($body['discount_value'] ?? null, FILTER_VALIDATE_INT);
    $maximum = ($body['max_discount_rial'] ?? null) === '' ? null : ($body['max_discount_rial'] ?? null);
    $minimum = filter_var($body['minimum_order_rial'] ?? 0, FILTER_VALIDATE_INT);
    $usage = ($body['usage_limit'] ?? null) === '' ? null : ($body['usage_limit'] ?? null);
    $perUser = filter_var($body['per_user_limit'] ?? 1, FILTER_VALIDATE_INT);
    $start = app_coupon_local_datetime(trim((string)($body['starts_at'] ?? '')), $tenant);
    $end = app_coupon_local_datetime(trim((string)($body['ends_at'] ?? '')), $tenant);
    $active = filter_var($body['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    if (!preg_match('/^[A-Z0-9][A-Z0-9_-]{2,39}$/', $code) ||
        $title === '' || mb_strlen($title) > 120 ||
        !in_array($type, ['percentage','fixed'], true) ||
        $value === false || $value < 1 || ($type === 'percentage' && $value > 100) ||
        $minimum === false || $minimum < 0 ||
        $perUser === false || $perUser < 1 || $perUser > 1000 ||
        !$start || !$end || $end <= $start ||
        $active === null) {
        app_json(['error'=>'اطلاعات کد تخفیف معتبر نیست.'],422);
    }
    $maximumValue = null;
    if ($maximum !== null) {
        $maximumValue = filter_var($maximum, FILTER_VALIDATE_INT);
        if ($maximumValue === false || $maximumValue < 1) app_json(['error'=>'سقف تخفیف باید مبلغ مثبت باشد.'],422);
    }
    $usageValue = null;
    if ($usage !== null) {
        $usageValue = filter_var($usage, FILTER_VALIDATE_INT);
        if ($usageValue === false || $usageValue < 1 || $usageValue > 100000000) {
            app_json(['error'=>'محدودیت استفاده باید عدد صحیح مثبت باشد.'],422);
        }
    }
    if ($type === 'fixed' && $maximumValue !== null) {
        app_json(['error'=>'سقف تخفیف فقط برای تخفیف درصدی کاربرد دارد.'],422);
    }
    return [
        'code'=>$code,'title'=>$title,'discount_type'=>$type,'discount_value'=>$value,
        'max_discount_rial'=>$maximumValue,'minimum_order_rial'=>$minimum,
        'usage_limit'=>$usageValue,'per_user_limit'=>$perUser,
        'starts_at'=>$start->format('Y-m-d H:i:s'),'ends_at'=>$end->format('Y-m-d H:i:s'),
        'is_active'=>$active ? 1 : 0,
    ];
}

function app_coupon_evaluate(PDO $db, array $tenant, int $userId, string $rawCode, int $subtotalRial): array {
    $code = app_coupon_code($rawCode);
    if ($code === '') return ['coupon_id'=>null,'coupon_code'=>null,'discount_rial'=>0];
    if (!preg_match('/^[A-Z0-9][A-Z0-9_-]{2,39}$/', $code)) {
        return ['error'=>'ساختار کد تخفیف معتبر نیست.','status'=>422];
    }
    $stmt = $db->prepare(
        'SELECT id,code,discount_type,discount_value,max_discount_rial,minimum_order_rial,
                usage_limit,per_user_limit,starts_at,ends_at,is_active
         FROM coupons WHERE tenant_id=? AND code=? LIMIT 1 FOR UPDATE'
    );
    $stmt->execute([$tenant['id'],$code]);
    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$coupon || !(bool)$coupon['is_active']) return ['error'=>'کد تخفیف پیدا نشد یا غیرفعال است.','status'=>422];
    $now = new DateTimeImmutable('now',new DateTimeZone('UTC'));
    $start = new DateTimeImmutable((string)$coupon['starts_at'],new DateTimeZone('UTC'));
    $end = new DateTimeImmutable((string)$coupon['ends_at'],new DateTimeZone('UTC'));
    if ($now < $start || $now >= $end) return ['error'=>'زمان استفاده از این کد تخفیف معتبر نیست.','status'=>422];
    if ($subtotalRial < (int)$coupon['minimum_order_rial']) return ['error'=>'مبلغ سبد خرید به حداقل لازم برای این کد تخفیف نرسیده است.','status'=>422];
    $usage = $db->prepare(
        "SELECT COUNT(*) AS total_uses,
                COALESCE(SUM(CASE WHEN cr.user_id=? THEN 1 ELSE 0 END),0) AS user_uses
         FROM coupon_redemptions cr
         JOIN orders o ON o.tenant_id=cr.tenant_id AND o.id=cr.order_id
         WHERE cr.tenant_id=? AND cr.coupon_id=?
           AND (
             o.status IN ('paid','accepted','picking','packed','ready_pickup','dispatched','delivered','refunded')
             OR (o.status='pending_payment' AND EXISTS (
               SELECT 1 FROM stock_reservations sr
               WHERE sr.tenant_id=o.tenant_id AND sr.order_id=o.id
                 AND sr.status='active' AND sr.expires_at>UTC_TIMESTAMP()
             ))
           )"
    );
    $usage->execute([$userId,$tenant['id'],$coupon['id']]);
    $counts = $usage->fetch(PDO::FETCH_ASSOC) ?: ['total_uses'=>0,'user_uses'=>0];
    if ($coupon['usage_limit'] !== null && (int)$counts['total_uses'] >= (int)$coupon['usage_limit']) {
        return ['error'=>'ظرفیت استفاده از این کد تخفیف تکمیل شده است.','status'=>409];
    }
    if ((int)$counts['user_uses'] >= (int)$coupon['per_user_limit']) {
        return ['error'=>'سهمیه استفاده شما از این کد تخفیف تکمیل شده است.','status'=>409];
    }
    if ($coupon['discount_type'] === 'percentage') {
        $percent = (int)$coupon['discount_value'];
        $discount = intdiv($subtotalRial,100)*$percent + intdiv(($subtotalRial%100)*$percent,100);
        if ($coupon['max_discount_rial'] !== null) $discount = min($discount,(int)$coupon['max_discount_rial']);
    } else {
        $discount = (int)$coupon['discount_value'];
    }
    $discount = min($subtotalRial,max(0,$discount));
    if ($discount < 1) return ['error'=>'مبلغ تخفیف برای این سبد قابل محاسبه نیست.','status'=>422];
    return ['coupon_id'=>(int)$coupon['id'],'coupon_code'=>$coupon['code'],'discount_rial'=>$discount];
}

function app_admin_coupon_list(PDO $db, array $tenant): never {
    $stmt=$db->prepare(
        "SELECT c.id,c.code,c.title,c.discount_type,c.discount_value,c.max_discount_rial,
                c.minimum_order_rial,c.usage_limit,c.per_user_limit,c.starts_at,c.ends_at,c.is_active,
                (SELECT COUNT(*) FROM coupon_redemptions cr JOIN orders o ON o.tenant_id=cr.tenant_id AND o.id=cr.order_id
                 WHERE cr.tenant_id=c.tenant_id AND cr.coupon_id=c.id
                   AND (o.status IN ('paid','accepted','picking','packed','ready_pickup','dispatched','delivered','refunded')
                     OR (o.status='pending_payment' AND EXISTS (
                       SELECT 1 FROM stock_reservations sr WHERE sr.tenant_id=o.tenant_id AND sr.order_id=o.id
                         AND sr.status='active' AND sr.expires_at>UTC_TIMESTAMP()
                     )))
                ) AS used_count
         FROM coupons c WHERE c.tenant_id=? ORDER BY c.created_at DESC LIMIT 200"
    );
    $stmt->execute([$tenant['id']]);
    $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($rows as &$row){
        $row['starts_at_local']=app_coupon_datetime_label((string)$row['starts_at'],$tenant);
        $row['ends_at_local']=app_coupon_datetime_label((string)$row['ends_at'],$tenant);
        $row['is_active']=(bool)$row['is_active'];
        $row['discount_value']=(int)$row['discount_value'];
        $row['used_count']=(int)$row['used_count'];
    }
    unset($row);
    app_json(['data'=>$rows]);
}

function app_admin_coupon_create(PDO $db, array $tenant, array $session): never {
    $data=app_coupon_payload(app_auth_request_body(),$tenant);
    try{
        $stmt=$db->prepare(
            'INSERT INTO coupons (tenant_id,code,title,discount_type,discount_value,max_discount_rial,minimum_order_rial,usage_limit,per_user_limit,starts_at,ends_at,is_active)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([$tenant['id'],$data['code'],$data['title'],$data['discount_type'],$data['discount_value'],$data['max_discount_rial'],$data['minimum_order_rial'],$data['usage_limit'],$data['per_user_limit'],$data['starts_at'],$data['ends_at'],$data['is_active']]);
    }catch(PDOException $e){
        if((string)$e->getCode()==='23000'&&(int)($e->errorInfo[1]??0)===1062)app_json(['error'=>'این کد تخفیف قبلاً ثبت شده است.'],409);
        throw $e;
    }
    $id=(int)$db->lastInsertId();
    app_audit_log($db,(int)$tenant['id'],(int)$session['id'],'coupon.created','coupon',$id);
    app_json(['data'=>['id'=>$id],'message'=>'کد تخفیف ایجاد شد.'],201);
}

function app_admin_coupon_update(PDO $db, array $tenant, array $session, int $id): never {
    $currentQuery=$db->prepare('SELECT * FROM coupons WHERE tenant_id=? AND id=? LIMIT 1');
    $currentQuery->execute([$tenant['id'],$id]);
    $current=$currentQuery->fetch(PDO::FETCH_ASSOC);
    if(!$current)app_json(['error'=>'کد تخفیف پیدا نشد.'],404);
    $current['starts_at']=app_coupon_datetime_label((string)$current['starts_at'],$tenant);
    $current['ends_at']=app_coupon_datetime_label((string)$current['ends_at'],$tenant);
    $current['is_active']=(bool)$current['is_active'];
    $data=app_coupon_payload(array_merge($current,app_auth_request_body()),$tenant);
    try{
        $stmt=$db->prepare(
            'UPDATE coupons SET code=?,title=?,discount_type=?,discount_value=?,max_discount_rial=?,minimum_order_rial=?,usage_limit=?,per_user_limit=?,starts_at=?,ends_at=?,is_active=?
             WHERE tenant_id=? AND id=?'
        );
        $stmt->execute([$data['code'],$data['title'],$data['discount_type'],$data['discount_value'],$data['max_discount_rial'],$data['minimum_order_rial'],$data['usage_limit'],$data['per_user_limit'],$data['starts_at'],$data['ends_at'],$data['is_active'],$tenant['id'],$id]);
    }catch(PDOException $e){
        if((string)$e->getCode()==='23000'&&(int)($e->errorInfo[1]??0)===1062)app_json(['error'=>'این کد تخفیف قبلاً ثبت شده است.'],409);
        throw $e;
    }
    app_audit_log($db,(int)$tenant['id'],(int)$session['id'],'coupon.updated','coupon',$id);
    app_json(['data'=>['id'=>$id],'message'=>'کد تخفیف به‌روزرسانی شد.']);
}

function app_admin_coupon_disable(PDO $db, array $tenant, array $session, int $id): never {
    $stmt=$db->prepare('UPDATE coupons SET is_active=0 WHERE tenant_id=? AND id=? AND is_active=1');
    $stmt->execute([$tenant['id'],$id]);
    if($stmt->rowCount()!==1)app_json(['error'=>'کد تخفیف فعال پیدا نشد.'],404);
    app_audit_log($db,(int)$tenant['id'],(int)$session['id'],'coupon.disabled','coupon',$id);
    app_json(['data'=>['id'=>$id],'message'=>'کد تخفیف غیرفعال شد.']);
}
