-- Global platform-owner SMS provider credentials. Secrets are AES-256-GCM encrypted by the application.
CREATE TABLE platform_sms_settings (
  id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
  provider ENUM('disabled','0098vip','kavenegar') NOT NULL DEFAULT 'disabled',
  sender_number VARCHAR(32) NULL,
  username_encrypted TEXT NULL,
  password_encrypted TEXT NULL,
  domain VARCHAR(32) NOT NULL DEFAULT '0098',
  updated_by BIGINT UNSIGNED NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_platform_sms_settings_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
