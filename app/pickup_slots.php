<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/policies.php';

function app_pickup_timezone(array $tenant): DateTimeZone {
    try { return new DateTimeZone((string)($tenant['timezone'] ?? 'UTC')); }
    catch (Throwable) { return new DateTimeZone('UTC'); }
}

function app_pickup_parse_local_datetime(string $value, DateTimeZone $timezone): ?DateTimeImmutable {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i', $value, $timezone);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) ||
        $date->format('Y-m-d\\TH:i') !== $value) return null;
    return $date->setTimezone(new DateTimeZone('UTC'));
}

function app_pickup_slot_public_row(array $row, DateTimeZone $timezone): array {
    $utc = new DateTimeZone('UTC');
    $start = new DateTimeImmutable((string)$row['starts_at'], $utc);
    $end = new DateTimeImmutable((string)$row['ends_at'], $utc);
    return [
        'id'=>(int)$row['id'],
        'title'=>$row['title'],
        'starts_at'=>$start->setTimezone($timezone)->format('Y-m-d\\TH:i:sP'),
        'ends_at'=>$end->setTimezone($timezone)->format('Y-m-d\\TH:i:sP'),
        'capacity'=>(int)$row['capacity'],
        'remaining_capacity'=>isset($row['remaining_capacity'])?(int)$row['remaining_capacity']:null,
        'is_active'=>(bool)$row['is_active'],
    ];
}

function app_pickup_slot_payload(array $body, array $tenant): array {
    $title=trim((string)($body['title']??''));
    $startValue=trim((string)($body['starts_at']??''));
    $endValue=trim((string)($body['ends_at']??''));
    $capacity=filter_var($body['capacity']??null,FILTER_VALIDATE_INT);
    $timezone=app_pickup_timezone($tenant);
    $start=app_pickup_parse_local_datetime($startValue,$timezone);
    $end=app_pickup_parse_local_datetime($endValue,$timezone);
    if($title===''||mb_strlen($title)>120||!$start||!$end||$end<=$start||
       $start<=new DateTimeImmutable('now',new DateTimeZone('UTC'))||
       $capacity===false||$capacity<1||$capacity>1000){
        app_json(['error'=>'عنوان، زمان شروع/پایان آینده و ظرفیت بازه دریافت معتبر نیست.'],422);
    }
    return [
        'title'=>$title,
        'starts_at'=>$start->format('Y-m-d H:i:s'),
        'ends_at'=>$end->format('Y-m-d H:i:s'),
        'capacity'=>$capacity,
    ];
}

function app_admin_pickup_slot_list(PDO $db, array $tenant): never {
    $stmt=$db->prepare('SELECT id,title,starts_at,ends_at,capacity,is_active FROM pickup_slots WHERE tenant_id=? ORDER BY starts_at DESC LIMIT 200');
    $stmt->execute([$tenant['id']]);
    $rows=array_map(fn($row)=>app_pickup_slot_public_row($row,app_pickup_timezone($tenant)),$stmt->fetchAll(PDO::FETCH_ASSOC));
    app_json(['data'=>$rows]);
}

function app_admin_pickup_slot_create(PDO $db, array $tenant, array $session): never {
    $data=app_pickup_slot_payload(app_auth_request_body(),$tenant);
    try{
        $stmt=$db->prepare('INSERT INTO pickup_slots (tenant_id,title,starts_at,ends_at,capacity) VALUES (?,?,?,?,?)');
        $stmt->execute([$tenant['id'],$data['title'],$data['starts_at'],$data['ends_at'],$data['capacity']]);
    }catch(PDOException $e){
        if((string)$e->getCode()==='23000'&&(int)($e->errorInfo[1]??0)===1062)app_json(['error'=>'این بازه دریافت قبلاً ثبت شده است.'],409);
        throw $e;
    }
    $id=(int)$db->lastInsertId();
    app_audit_log($db,(int)$tenant['id'],(int)$session['id'],'pickup_slot.created','pickup_slot',$id);
    app_json(['data'=>['id'=>$id],'message'=>'بازه دریافت حضوری ثبت شد.'],201);
}

function app_admin_pickup_slot_disable(PDO $db, array $tenant, array $session, int $id): never {
    $stmt=$db->prepare('UPDATE pickup_slots SET is_active=0 WHERE tenant_id=? AND id=? AND is_active=1');
    $stmt->execute([$tenant['id'],$id]);
    if($stmt->rowCount()!==1)app_json(['error'=>'بازه فعال پیدا نشد.'],404);
    app_audit_log($db,(int)$tenant['id'],(int)$session['id'],'pickup_slot.disabled','pickup_slot',$id);
    app_json(['data'=>['id'=>$id],'message'=>'بازه دریافت غیرفعال شد.']);
}

function app_public_pickup_slots(PDO $db, array $tenant): never {
    $configured=$db->prepare('SELECT COUNT(*) FROM pickup_slots WHERE tenant_id=?');
    $configured->execute([$tenant['id']]);
    $count=(int)$configured->fetchColumn();
    $stmt=$db->prepare(
        "SELECT ps.id,ps.title,ps.starts_at,ps.ends_at,ps.capacity,ps.is_active,
          ps.capacity-(
            SELECT COUNT(*) FROM orders o
            WHERE o.tenant_id=ps.tenant_id AND o.pickup_slot_id=ps.id
              AND (
                o.status IN ('paid','accepted','picking','packed','ready_pickup','dispatched','delivered')
                OR (o.status='pending_payment' AND EXISTS (
                  SELECT 1 FROM stock_reservations sr
                  WHERE sr.tenant_id=o.tenant_id AND sr.order_id=o.id
                    AND sr.status='active' AND sr.expires_at>UTC_TIMESTAMP()
                ))
              )
          ) AS remaining_capacity
         FROM pickup_slots ps
         WHERE ps.tenant_id=? AND ps.is_active=1 AND ps.ends_at>UTC_TIMESTAMP()
         HAVING remaining_capacity>0
         ORDER BY ps.starts_at LIMIT 100"
    );
    $stmt->execute([$tenant['id']]);
    $rows=array_map(fn($row)=>app_pickup_slot_public_row($row,app_pickup_timezone($tenant)),$stmt->fetchAll(PDO::FETCH_ASSOC));
    app_json(['data'=>['configured'=>$count>0,'slots'=>$rows]]);
}
