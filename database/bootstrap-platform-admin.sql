-- One-time bootstrap for a platform administrator.
-- Run only after all migrations, and after the intended account has completed OTP verification.
-- Edit the tenant ID and mobile number before execution.
SET @platform_tenant_id = 1;
SET @platform_admin_mobile = '09120000000';
SET @platform_user_id = (
  SELECT id FROM users
  WHERE tenant_id=@platform_tenant_id
    AND mobile=@platform_admin_mobile
    AND status='active'
    AND mobile_verified_at IS NOT NULL
  LIMIT 1
);

DELIMITER //
CREATE PROCEDURE assert_verified_platform_admin()
BEGIN
  IF @platform_user_id IS NULL THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT='Platform admin must be an active user with verified mobile in the selected tenant';
  END IF;
END//
DELIMITER ;

CALL assert_verified_platform_admin();
DROP PROCEDURE assert_verified_platform_admin;

INSERT INTO roles (tenant_id,role_key,title)
SELECT NULL,'platform_admin','مدیر پلتفرم'
WHERE NOT EXISTS (
  SELECT 1 FROM roles WHERE tenant_id IS NULL AND role_key='platform_admin'
);

SET @platform_role_id = (
  SELECT id FROM roles WHERE tenant_id IS NULL AND role_key='platform_admin' ORDER BY id LIMIT 1
);

INSERT IGNORE INTO role_permissions (role_scope_id,role_id,permission_id)
SELECT 0,@platform_role_id,id
FROM permissions
WHERE scope='platform'
  AND permission_key IN ('platform.tenants.manage','platform.audit.read','platform.payments.manage');

INSERT IGNORE INTO user_roles (tenant_id,role_scope_id,user_id,role_id)
VALUES (@platform_tenant_id,0,@platform_user_id,@platform_role_id);

SELECT @platform_user_id AS platform_admin_user_id,
       @platform_role_id AS platform_role_id,
       'Platform administrator access granted' AS result;
