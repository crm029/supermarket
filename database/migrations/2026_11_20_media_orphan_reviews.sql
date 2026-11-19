-- Operator-reviewed orphan media cleanup requests. Approval only enqueues; the existing worker performs deletion.
CREATE TABLE media_orphan_reviews (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  storage_key VARCHAR(500) NOT NULL,
  reason VARCHAR(500) NOT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  requested_by BIGINT UNSIGNED NOT NULL,
  reviewed_by BIGINT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_media_orphan_review_storage (tenant_id,storage_key),
  KEY ix_media_orphan_review_status (status,created_at,id),
  CONSTRAINT fk_media_orphan_review_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
  CONSTRAINT fk_media_orphan_review_requester FOREIGN KEY (requested_by) REFERENCES users(id),
  CONSTRAINT fk_media_orphan_review_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (permission_key,scope,description)
VALUES ('platform.media.cleanup.manage','platform','بررسی و تأیید پاک‌سازی فایل‌های رسانه‌ای یتیم')
ON DUPLICATE KEY UPDATE description=VALUES(description);

INSERT IGNORE INTO role_permissions (role_scope_id,role_id,permission_id)
SELECT 0,r.id,p.id
FROM roles r JOIN permissions p
  ON p.permission_key='platform.media.cleanup.manage' AND p.scope='platform'
WHERE r.tenant_id IS NULL AND r.role_key='platform_admin';
