<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function app_notification_create(
    PDO $db,
    int $tenantId,
    int $userId,
    string $eventKey,
    string $type,
    string $title,
    string $body,
    ?string $entityType = null,
    ?int $entityId = null
): void {
    if(!in_array($type,['order','assignment','system'],true)||
       $eventKey===''||mb_strlen($eventKey)>140||
       $title===''||mb_strlen($title)>180||
       $body===''||mb_strlen($body)>500){
        throw new InvalidArgumentException('Invalid notification payload.');
    }
    $stmt=$db->prepare(
        'INSERT IGNORE INTO notifications
         (tenant_id,user_id,event_key,notification_type,title,body,entity_type,entity_id)
         VALUES (?,?,?,?,?,?,?,?)'
    );
    $stmt->execute([$tenantId,$userId,$eventKey,$type,$title,$body,$entityType,$entityId]);
}

function app_notification_notify_permission(
    PDO $db,
    int $tenantId,
    string $permission,
    string $eventKey,
    string $type,
    string $title,
    string $body,
    ?string $entityType = null,
    ?int $entityId = null,
    ?int $excludeUserId = null
): void {
    $stmt=$db->prepare(
        "SELECT DISTINCT u.id
         FROM users u
         JOIN user_roles ur ON ur.tenant_id=u.tenant_id AND ur.user_id=u.id
         JOIN role_permissions rp ON rp.role_scope_id=ur.role_scope_id AND rp.role_id=ur.role_id
         JOIN permissions p ON p.id=rp.permission_id
         WHERE u.tenant_id=? AND u.status='active'
           AND p.scope='tenant' AND p.permission_key=?
         ORDER BY u.id"
    );
    $stmt->execute([$tenantId,$permission]);
    foreach($stmt->fetchAll(PDO::FETCH_COLUMN) as $userId){
        $userId=(int)$userId;
        if($excludeUserId!==null&&$userId===$excludeUserId)continue;
        app_notification_create($db,$tenantId,$userId,$eventKey,$type,$title,$body,$entityType,$entityId);
    }
}

function app_notification_list(PDO $db, array $tenant, array $session): never {
    $unreadOnly=($_GET['unread']??'')==='1';
    $where='tenant_id=? AND user_id=?';
    if($unreadOnly)$where.=' AND read_at IS NULL';
    $stmt=$db->prepare(
        "SELECT id,notification_type,title,body,entity_type,entity_id,created_at,read_at
         FROM notifications WHERE {$where}
         ORDER BY id DESC LIMIT 50"
    );
    $stmt->execute([$tenant['id'],$session['id']]);
    $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($rows as &$row){
        $row['id']=(int)$row['id'];
        $row['entity_id']=$row['entity_id']===null?null:(int)$row['entity_id'];
    }
    unset($row);
    $count=$db->prepare('SELECT COUNT(*) FROM notifications WHERE tenant_id=? AND user_id=? AND read_at IS NULL');
    $count->execute([$tenant['id'],$session['id']]);
    app_json(['data'=>$rows,'unread_count'=>(int)$count->fetchColumn()]);
}

function app_notification_mark_read(PDO $db, array $tenant, array $session, int $notificationId): never {
    $stmt=$db->prepare(
        'UPDATE notifications SET read_at=UTC_TIMESTAMP()
         WHERE tenant_id=? AND user_id=? AND id=? AND read_at IS NULL'
    );
    $stmt->execute([$tenant['id'],$session['id'],$notificationId]);
    if($stmt->rowCount()===1)app_json(['message'=>'اعلان خوانده‌شده علامت‌گذاری شد.']);
    $exists=$db->prepare('SELECT 1 FROM notifications WHERE tenant_id=? AND user_id=? AND id=?');
    $exists->execute([$tenant['id'],$session['id'],$notificationId]);
    if(!$exists->fetchColumn())app_json(['error'=>'اعلان پیدا نشد.'],404);
    app_json(['message'=>'این اعلان قبلاً خوانده شده است.']);
}

function app_notification_mark_all_read(PDO $db, array $tenant, array $session): never {
    $stmt=$db->prepare(
        'UPDATE notifications SET read_at=UTC_TIMESTAMP()
         WHERE tenant_id=? AND user_id=? AND read_at IS NULL'
    );
    $stmt->execute([$tenant['id'],$session['id']]);
    app_json(['data'=>['updated'=>$stmt->rowCount()],'message'=>'همه اعلان‌ها خوانده‌شده شدند.']);
}
