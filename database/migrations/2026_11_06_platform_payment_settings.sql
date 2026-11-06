-- Owner-only ZarinPal gateway settings. Credentials are encrypted by the application before storage.
CREATE TABLE platform_payment_settings (
  id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
  merchant_id_encrypted TEXT NULL,
  mode ENUM('sandbox','live') NOT NULL DEFAULT 'sandbox',
  updated_by BIGINT UNSIGNED NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT chk_platform_payment_settings_singleton CHECK (id = 1),
  CONSTRAINT fk_platform_payment_settings_actor FOREIGN KEY (updated_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO platform_payment_settings (id, mode) VALUES (1, 'sandbox');

-- Existing platform owners receive this new capability when the migration is applied.
INSERT IGNORE INTO role_permissions (role_scope_id, role_id, permission_id)
SELECT 0, r.id, p.id
FROM roles r
JOIN permissions p ON p.permission_key = 'platform.payments.manage' AND p.scope = 'platform'
WHERE r.tenant_id IS NULL AND r.role_key = 'platform_admin';

ALTER TABLE payment_attempts
  ADD COLUMN gateway_mode ENUM('sandbox','live') NOT NULL DEFAULT 'sandbox' AFTER amount_rial,
  ADD COLUMN merchant_id_encrypted TEXT NULL AFTER gateway_mode;
