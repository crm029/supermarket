-- Permission model integration checks.
-- Run after tenant-isolation, auth and permissions migrations plus their seed tests.

SELECT id INTO @tenant_one_user FROM users WHERE tenant_id = 1 AND mobile = '09120000001' LIMIT 1;
SELECT id INTO @tenant_two_user FROM users WHERE tenant_id = 2 AND mobile = '09120000002' LIMIT 1;

INSERT INTO roles (tenant_id, role_key, title)
VALUES (1, 'store_manager_test', 'مدیر فروشگاه آزمایشی');
SET @manager_role = LAST_INSERT_ID();

INSERT INTO user_roles (tenant_id, user_id, role_id)
VALUES (1, @tenant_one_user, @manager_role);

INSERT INTO role_permissions (role_scope_id, role_id, permission_id)
SELECT 1, @manager_role, id
FROM permissions
WHERE permission_key IN ('catalog.products.read', 'catalog.products.manage', 'catalog.categories.read', 'catalog.categories.manage', 'orders.read', 'orders.manage') AND scope = 'tenant';

DELIMITER //
CREATE PROCEDURE assert_platform_permission_rejected()
BEGIN
  DECLARE rejected BOOLEAN DEFAULT FALSE;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLSTATE '45000' SET rejected = TRUE;
    INSERT INTO role_permissions (role_scope_id, role_id, permission_id)
    SELECT 1, @manager_role, id
    FROM permissions
    WHERE permission_key = 'platform.tenants.manage' AND scope = 'platform';
  END;
  IF rejected = FALSE THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tenant role accepted a platform permission';
  END IF;
END//

CREATE PROCEDURE assert_tenant_permission_is_scoped()
BEGIN
  DECLARE granted_count INT DEFAULT 0;
  SELECT COUNT(*) INTO granted_count
  FROM user_roles ur
  JOIN role_permissions rp
    ON rp.role_scope_id = ur.role_scope_id AND rp.role_id = ur.role_id
  JOIN permissions p ON p.id = rp.permission_id
  WHERE ur.tenant_id = 1
    AND ur.user_id = @tenant_one_user
    AND p.permission_key = 'catalog.products.manage'
    AND p.scope = 'tenant';

  IF granted_count <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tenant product permission was not granted to assigned user';
  END IF;

  SELECT COUNT(*) INTO granted_count
  FROM user_roles ur
  JOIN role_permissions rp
    ON rp.role_scope_id = ur.role_scope_id AND rp.role_id = ur.role_id
  JOIN permissions p ON p.id = rp.permission_id
  WHERE ur.tenant_id = 1
    AND ur.user_id = @tenant_one_user
    AND p.permission_key = 'catalog.categories.manage'
    AND p.scope = 'tenant';

  IF granted_count <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tenant category permission was not granted to assigned user';
  END IF;

  SELECT COUNT(*) INTO granted_count
  FROM user_roles ur
  JOIN role_permissions rp
    ON rp.role_scope_id = ur.role_scope_id AND rp.role_id = ur.role_id
  JOIN permissions p ON p.id = rp.permission_id
  WHERE ur.tenant_id = 1
    AND ur.user_id = @tenant_one_user
    AND p.permission_key = 'platform.tenants.manage'
    AND p.scope = 'tenant';

  IF granted_count <> 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Platform permission leaked into tenant permission scope';
  END IF;

  SELECT COUNT(*) INTO granted_count
  FROM user_roles ur
  JOIN role_permissions rp
    ON rp.role_scope_id = ur.role_scope_id AND rp.role_id = ur.role_id
  JOIN permissions p ON p.id = rp.permission_id
  WHERE ur.tenant_id = 2
    AND ur.user_id = @tenant_two_user
    AND p.permission_key = 'catalog.products.manage'
    AND p.scope = 'tenant';

  IF granted_count <> 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tenant permission leaked to another tenant user';
  END IF;
END//
DELIMITER ;

CALL assert_platform_permission_rejected();
CALL assert_tenant_permission_is_scoped();
DROP PROCEDURE assert_platform_permission_rejected;
DROP PROCEDURE assert_tenant_permission_is_scoped;

SELECT 'permission model integration checks passed' AS result;
