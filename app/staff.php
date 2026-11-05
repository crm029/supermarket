<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/policies.php';

function app_admin_staff_list(PDO $db, array $tenant): never {
    $roles = $db->prepare(
        "SELECT r.id,r.role_key,r.title,
                GROUP_CONCAT(DISTINCT p.permission_key ORDER BY p.permission_key SEPARATOR ',') AS permission_keys
         FROM roles r
         LEFT JOIN role_permissions rp ON rp.role_scope_id = r.role_scope_id AND rp.role_id = r.id
         LEFT JOIN permissions p ON p.id = rp.permission_id AND p.scope = 'tenant'
         WHERE r.tenant_id = ? AND r.role_key <> 'store_owner'
         GROUP BY r.id,r.role_key,r.title ORDER BY r.title"
    );
    $roles->execute([$tenant['id']]);
    $roleRows = $roles->fetchAll(PDO::FETCH_ASSOC);
    foreach ($roleRows as &$role) {
        $role['id'] = (int)$role['id'];
        $role['permission_keys'] = $role['permission_keys'] === null || $role['permission_keys'] === ''
            ? [] : explode(',', $role['permission_keys']);
    }
    unset($role);

    $staff = $db->prepare(
        "SELECT u.id,u.full_name,u.mobile,u.status,
                GROUP_CONCAT(DISTINCT r.title ORDER BY r.title SEPARATOR '، ') AS role_titles,
                GROUP_CONCAT(DISTINCT r.id ORDER BY r.id SEPARATOR ',') AS role_ids
         FROM users u
         JOIN user_roles ur ON ur.tenant_id = u.tenant_id AND ur.user_id = u.id
         JOIN roles r ON r.role_scope_id = ur.role_scope_id AND r.id = ur.role_id
         WHERE u.tenant_id = ? AND r.role_key <> 'store_owner'
           AND NOT EXISTS (
             SELECT 1 FROM user_roles owner_ur
             JOIN roles owner_role ON owner_role.role_scope_id = owner_ur.role_scope_id
                                  AND owner_role.id = owner_ur.role_id
             WHERE owner_ur.tenant_id = u.tenant_id AND owner_ur.user_id = u.id
               AND owner_role.role_key = 'store_owner'
           )
         GROUP BY u.id,u.full_name,u.mobile,u.status ORDER BY u.full_name"
    );
    $staff->execute([$tenant['id']]);
    $staffRows = $staff->fetchAll(PDO::FETCH_ASSOC);
    foreach ($staffRows as &$person) {
        $person['id'] = (int)$person['id'];
        $person['role_ids'] = $person['role_ids'] === null || $person['role_ids'] === ''
            ? [] : array_map('intval', explode(',', $person['role_ids']));
    }
    unset($person);

    $catalog = $db->query(
        "SELECT permission_key,description FROM permissions
         WHERE scope = 'tenant' AND permission_key <> 'staff.manage'
         ORDER BY permission_key"
    )->fetchAll(PDO::FETCH_ASSOC);

    app_json(['data' => ['roles' => $roleRows, 'staff' => $staffRows, 'permissions' => $catalog]]);
}

function app_admin_staff_create_role(PDO $db, array $tenant, array $session): never {
    $body = app_auth_request_body();
    $key = strtolower(trim((string)($body['role_key'] ?? '')));
    $title = trim((string)($body['title'] ?? ''));
    $permissionKeys = $body['permission_keys'] ?? [];
    if (!preg_match('/^[a-z][a-z0-9_]{2,59}$/', $key) ||
        $key === 'store_owner' || $title === '' || mb_strlen($title) > 100 ||
        !is_array($permissionKeys) || count($permissionKeys) > 20) {
        app_json(['error' => 'اطلاعات نقش معتبر نیست.'], 422);
    }
    $permissionKeys = array_values(array_unique(array_filter($permissionKeys, 'is_string')));
    if (in_array('staff.manage', $permissionKeys, true)) {
        app_json(['error' => 'مجوز مدیریت کارکنان فقط برای مدیر اصلی محفوظ است.'], 422);
    }
    if ($permissionKeys !== []) {
        $marks = implode(',', array_fill(0, count($permissionKeys), '?'));
        $check = $db->prepare("SELECT permission_key FROM permissions WHERE scope='tenant' AND permission_key IN ({$marks})");
        $check->execute($permissionKeys);
        $found = $check->fetchAll(PDO::FETCH_COLUMN);
        if (count($found) !== count($permissionKeys)) app_json(['error' => 'یکی از مجوزهای انتخاب‌شده معتبر نیست.'], 422);
    }

    $db->beginTransaction();
    try {
        $insert = $db->prepare('INSERT INTO roles (tenant_id,role_key,title) VALUES (?,?,?)');
        $insert->execute([$tenant['id'], $key, $title]);
        $roleId = (int)$db->lastInsertId();
        if ($permissionKeys !== []) {
            $marks = implode(',', array_fill(0, count($permissionKeys), '?'));
            $grant = $db->prepare(
                "INSERT INTO role_permissions (role_scope_id,role_id,permission_id)
                 SELECT ?,?,id FROM permissions WHERE scope='tenant' AND permission_key IN ({$marks})"
            );
            $grant->execute(array_merge([(int)$tenant['id'], $roleId], $permissionKeys));
        }
        app_audit_log($db,(int)$tenant['id'],(int)$session['id'],'staff.role.created','role',$roleId,[
            'role_key' => $key, 'permission_count' => count($permissionKeys)
        ]);
        $db->commit();
    } catch (PDOException $e) {
        if ($db->inTransaction()) $db->rollBack();
        if ((string)$e->getCode() === '23000') app_json(['error' => 'شناسه این نقش قبلاً استفاده شده است.'], 409);
        throw $e;
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
    app_json(['data' => ['id' => $roleId], 'message' => 'نقش جدید ساخته شد.'], 201);
}

function app_admin_staff_assign_role(PDO $db, array $tenant, array $session): never {
    $body = app_auth_request_body();
    $mobile = app_auth_mobile((string)($body['mobile'] ?? ''));
    $roleId = filter_var($body['role_id'] ?? null, FILTER_VALIDATE_INT);
    if ($mobile === null || $roleId === false || $roleId < 1) {
        app_json(['error' => 'شماره موبایل یا نقش معتبر نیست.'], 422);
    }
    $user = $db->prepare(
        "SELECT u.id FROM users u
         WHERE u.tenant_id = ? AND u.mobile = ? AND u.status = 'active'
           AND NOT EXISTS (
             SELECT 1 FROM user_roles ur JOIN roles r
               ON r.role_scope_id = ur.role_scope_id AND r.id = ur.role_id
             WHERE ur.tenant_id = u.tenant_id AND ur.user_id = u.id AND r.role_key = 'store_owner'
           )
         LIMIT 1"
    );
    $user->execute([$tenant['id'], $mobile]);
    $userId = $user->fetchColumn();
    if (!$userId) app_json(['error' => 'کاربر فعالی با این شماره در همین فروشگاه پیدا نشد؛ ابتدا باید ثبت‌نام و تأیید موبایل انجام شود.'], 404);

    $role = $db->prepare("SELECT id,role_key FROM roles WHERE tenant_id = ? AND id = ? LIMIT 1");
    $role->execute([$tenant['id'], $roleId]);
    $roleRow = $role->fetch(PDO::FETCH_ASSOC);
    if (!$roleRow || $roleRow['role_key'] === 'store_owner') app_json(['error' => 'نقش انتخاب‌شده قابل تخصیص نیست.'], 404);

    $assign = $db->prepare('INSERT IGNORE INTO user_roles (tenant_id,role_scope_id,user_id,role_id) VALUES (?,?,?,?)');
    $assign->execute([$tenant['id'],$tenant['id'],$userId,$roleId]);
    if ($assign->rowCount() !== 1) app_json(['error' => 'این نقش قبلاً به کاربر اختصاص داده شده است.'], 409);
    app_audit_log($db,(int)$tenant['id'],(int)$session['id'],'staff.role.assigned','user',(int)$userId,[
        'role_id' => (int)$roleId
    ]);
    app_json(['data' => ['user_id' => (int)$userId,'role_id' => (int)$roleId], 'message' => 'نقش به کاربر اختصاص یافت.'], 201);
}

function app_admin_staff_remove_role(PDO $db, array $tenant, array $session, int $userId, int $roleId): never {
    if ($userId === (int)$session['id']) app_json(['error' => 'برای جلوگیری از قفل‌شدن پنل، نمی‌توانید نقش خودتان را از این بخش حذف کنید.'], 409);
    $role = $db->prepare("SELECT role_key FROM roles WHERE tenant_id = ? AND id = ? LIMIT 1");
    $role->execute([$tenant['id'],$roleId]);
    $key = $role->fetchColumn();
    if (!$key || $key === 'store_owner') app_json(['error' => 'این نقش قابل حذف از حساب نیست.'], 404);
    $remove = $db->prepare('DELETE FROM user_roles WHERE tenant_id = ? AND user_id = ? AND role_id = ?');
    $remove->execute([$tenant['id'],$userId,$roleId]);
    if ($remove->rowCount() !== 1) app_json(['error' => 'تخصیص نقش پیدا نشد.'], 404);
    app_audit_log($db,(int)$tenant['id'],(int)$session['id'],'staff.role.removed','user',$userId,['role_id'=>$roleId]);
    app_json(['data' => ['user_id' => $userId,'role_id' => $roleId], 'message' => 'نقش از حساب کاربر برداشته شد.']);
}
