<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function app_auth_has_permission(PDO $db, array $tenant, array $session, string $permission): bool {
    if (!preg_match('/^[a-z0-9][a-z0-9._-]{1,99}$/', $permission)) return false;

    $stmt = $db->prepare(
        "SELECT 1
         FROM user_roles ur
         JOIN role_permissions rp
           ON rp.role_scope_id = ur.role_scope_id AND rp.role_id = ur.role_id
         JOIN permissions p ON p.id = rp.permission_id
         WHERE ur.tenant_id = :tenant
           AND ur.user_id = :user
           AND p.permission_key = :permission
           AND p.scope = 'tenant'
         LIMIT 1"
    );
    $stmt->execute([
        'tenant' => $tenant['id'],
        'user' => $session['id'],
        'permission' => $permission,
    ]);
    return (bool)$stmt->fetchColumn();
}

function app_auth_require_permission(PDO $db, array $tenant, array $session, string $permission): void {
    if (!app_auth_has_permission($db, $tenant, $session, $permission)) {
        app_json(['error' => 'برای انجام این عملیات دسترسی کافی ندارید.'], 403);
    }
}

function app_auth_has_platform_permission(PDO $db, array $tenant, array $session, string $permission): bool {
    if (!preg_match('/^[a-z0-9][a-z0-9._-]{1,99}$/', $permission)) return false;
    $stmt=$db->prepare(
        "SELECT 1
         FROM user_roles ur
         JOIN roles r ON r.role_scope_id=ur.role_scope_id AND r.id=ur.role_id
         JOIN role_permissions rp ON rp.role_scope_id=r.role_scope_id AND rp.role_id=r.id
         JOIN permissions p ON p.id=rp.permission_id
         WHERE ur.tenant_id=:tenant AND ur.user_id=:user
           AND ur.role_scope_id=0 AND r.tenant_id IS NULL
           AND p.scope='platform' AND p.permission_key=:permission
         LIMIT 1"
    );
    $stmt->execute(['tenant'=>$tenant['id'],'user'=>$session['id'],'permission'=>$permission]);
    return (bool)$stmt->fetchColumn();
}

function app_auth_require_platform_permission(PDO $db, array $tenant, array $session, string $permission): void {
    if (!app_auth_has_platform_permission($db,$tenant,$session,$permission)) {
        app_json(['error'=>'برای مدیریت پلتفرم دسترسی کافی ندارید.'],403);
    }
}

function app_audit_log(
    PDO $db,
    int $tenantId,
    int $actorUserId,
    string $action,
    string $entityType,
    ?int $entityId = null,
    array $details = []
): void {
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $packedIp = $ip !== '' ? @inet_pton($ip) : false;
    $json = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

    $stmt = $db->prepare(
        "INSERT INTO audit_logs
          (tenant_id, actor_user_id, action, entity_type, entity_id, ip_address, details)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $tenantId,
        $actorUserId,
        substr($action, 0, 100),
        substr($entityType, 0, 80),
        $entityId,
        $packedIp === false ? null : $packedIp,
        $json === false ? null : $json,
    ]);
}
