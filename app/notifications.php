<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function app_notification_publish_permission(
    PDO $db,
    int $tenantId,
    string $permission,
    string $type,
    string $title,
    string $body,
    string $entityType,
    ?int $entityId
): void {
    $recipients = $db->prepare(
        "SELECT DISTINCT ur.user_id
         FROM user_roles ur
         JOIN role_permissions rp
           ON rp.role_scope_id=ur.role_scope_id AND rp.role_id=ur.role_id
         JOIN permissions p ON p.id=rp.permission_id
         JOIN users u ON u.tenant_id=ur.tenant_id AND u.id=ur.user_id
         WHERE ur.tenant_id=? AND p.permission_key=? AND p.scope='tenant' AND u.status='active'"
    );
    $recipients->execute([$tenantId,$permission]);
    $ids = $recipients->fetchAll(PDO::FETCH_COLUMN);
    if (!$ids) return;

    $insert = $db->prepare(
        'INSERT IGNORE INTO notifications
         (tenant_id,recipient_user_id,notification_type,title,body,entity_type,entity_id)
         VALUES (?,?,?,?,?,?,?)'
    );
    foreach ($ids as $userId) {
        $insert->execute([$tenantId,(int)$userId,$type,$title,$body,$entityType,$entityId]);
    }
}

function app_admin_notification_list(PDO $db, array $tenant, array $session): never {
    $tenantId=(int)$tenant['id'];
    $userId=(int)$session['id'];
    $stmt=$db->prepare(
        'SELECT id,notification_type,title,body,entity_type,entity_id,read_at,created_at
         FROM notifications
         WHERE tenant_id=? AND recipient_user_id=?
         ORDER BY created_at DESC,id DESC LIMIT 50'
    );
    $stmt->execute([$tenantId,$userId]);
    $count=$db->prepare(
        'SELECT COUNT(*) FROM notifications WHERE tenant_id=? AND recipient_user_id=? AND read_at IS NULL'
    );
    $count->execute([$tenantId,$userId]);
    app_json(['data'=>$stmt->fetchAll(PDO::FETCH_ASSOC),'meta'=>['unread_count'=>(int)$count->fetchColumn(),'limit'=>50]]);
}

function app_admin_notification_mark_read(PDO $db, array $tenant, array $session, int $notificationId): never {
    $stmt=$db->prepare(
        'UPDATE notifications SET read_at=COALESCE(read_at,UTC_TIMESTAMP())
         WHERE tenant_id=? AND recipient_user_id=? AND id=?'
    );
    $stmt->execute([(int)$tenant['id'],(int)$session['id'],$notificationId]);
    $exists=$db->prepare(
        'SELECT id,read_at FROM notifications WHERE tenant_id=? AND recipient_user_id=? AND id=? LIMIT 1'
    );
    $exists->execute([(int)$tenant['id'],(int)$session['id'],$notificationId]);
    $row=$exists->fetch(PDO::FETCH_ASSOC);
    if (!$row) app_json(['error'=>'اعلان پیدا نشد.'],404);
    app_json(['data'=>['id'=>(int)$row['id'],'read_at'=>$row['read_at']]]);
}

function app_admin_notification_mark_all_read(PDO $db, array $tenant, array $session): never {
    $stmt=$db->prepare(
        'UPDATE notifications SET read_at=UTC_TIMESTAMP()
         WHERE tenant_id=? AND recipient_user_id=? AND read_at IS NULL'
    );
    $stmt->execute([(int)$tenant['id'],(int)$session['id']]);
    app_json(['data'=>['marked_count'=>$stmt->rowCount()]]);
}
