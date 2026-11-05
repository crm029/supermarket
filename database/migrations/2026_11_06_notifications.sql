-- In-app notification center, scoped to each tenant and recipient.
CREATE TABLE notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  event_key VARCHAR(140) NOT NULL,
  notification_type ENUM('order','assignment','system') NOT NULL DEFAULT 'system',
  title VARCHAR(180) NOT NULL,
  body VARCHAR(500) NOT NULL,
  entity_type VARCHAR(80) NULL,
  entity_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  read_at TIMESTAMP NULL,
  UNIQUE KEY uq_notification_event (tenant_id,user_id,event_key),
  KEY ix_notifications_inbox (tenant_id,user_id,read_at,created_at),
  CONSTRAINT fk_notifications_recipient
    FOREIGN KEY (tenant_id,user_id) REFERENCES users(tenant_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
