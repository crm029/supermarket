-- One-time tenant owner bootstrap.
-- Edit @tenant_id, @owner_name and @owner_mobile before running.
-- Run AFTER schema.sql and all migrations (tenant isolation, auth and permissions).
-- The owner is created as pending and must complete mobile OTP verification before login.

SET @tenant_id = 1;
SET @owner_name = 'مدیر فروشگاه';
SET @owner_mobile = '09120000000';

INSERT INTO users (tenant_id, full_name, mobile, status, referral_code)
VALUES (@tenant_id, @owner_name, @owner_mobile, 'pending',
        UPPER(SUBSTRING(REPLACE(UUID(), '-', ''), 1, 10)))
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name);

SET @owner_id = (
  SELECT id FROM users WHERE tenant_id = @tenant_id AND mobile = @owner_mobile LIMIT 1
);

INSERT INTO roles (tenant_id, role_key, title)
VALUES (@tenant_id, 'store_owner', 'مدیر کل فروشگاه')
ON DUPLICATE KEY UPDATE title = VALUES(title);

SET @owner_role_id = (
  SELECT id FROM roles WHERE tenant_id = @tenant_id AND role_key = 'store_owner' LIMIT 1
);

INSERT INTO user_roles (tenant_id, role_scope_id, user_id, role_id)
VALUES (@tenant_id, @tenant_id, @owner_id, @owner_role_id)
ON DUPLICATE KEY UPDATE role_scope_id = VALUES(role_scope_id);

INSERT IGNORE INTO role_permissions (role_scope_id, role_id, permission_id)
SELECT @tenant_id, @owner_role_id, id
FROM permissions
WHERE scope = 'tenant';

INSERT INTO warehouses (tenant_id, title, warehouse_type, is_active)
SELECT @tenant_id, 'انبار اصلی', 'main', 1
WHERE NOT EXISTS (
  SELECT 1 FROM warehouses WHERE tenant_id = @tenant_id AND warehouse_type = 'main' AND is_active = 1
);

INSERT INTO store_settings (tenant_id, setting_key, setting_value)
VALUES
  (@tenant_id, 'store.profile', JSON_OBJECT('name', 'سوپرمارکت ۳۶۰', 'delivery_fee_rial', 0, 'currency', 'IRR')),
  (@tenant_id, 'store.checkout', JSON_OBJECT('guest_checkout', false, 'reservation_minutes', 20))
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

SELECT @owner_id AS owner_user_id, @owner_role_id AS owner_role_id,
       (SELECT status FROM users WHERE id = @owner_id) AS owner_status;
