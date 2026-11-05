-- Order picker/courier assignment workflow.
-- Apply after tenant isolation and permissions migrations.

INSERT INTO permissions (permission_key, scope, description) VALUES
('orders.assign','tenant','تخصیص نیروی جمع‌آوری و پیک به سفارش'),
('orders.pick','tenant','دریافت وظایف جمع‌آوری سفارش'),
('orders.deliver','tenant','دریافت وظایف ارسال و تحویل سفارش')
ON DUPLICATE KEY UPDATE description=VALUES(description);

-- Preserve the manager's ability to delegate the new operational permissions.
INSERT IGNORE INTO role_permissions (role_scope_id,role_id,permission_id)
SELECT r.role_scope_id,r.id,p.id
FROM roles r
JOIN permissions p ON p.permission_key='orders.assign' AND p.scope='tenant'
WHERE r.role_key='store_owner' AND r.tenant_id IS NOT NULL;

CREATE TABLE order_assignments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NOT NULL,
  assignee_user_id BIGINT UNSIGNED NOT NULL,
  assignment_type ENUM('picker','courier') NOT NULL,
  status ENUM('active','completed','cancelled','replaced') NOT NULL DEFAULT 'active',
  assigned_by_user_id BIGINT UNSIGNED NOT NULL,
  assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at TIMESTAMP NULL,
  KEY ix_order_assignments_active (tenant_id,order_id,assignment_type,status),
  KEY ix_order_assignments_assignee (tenant_id,assignee_user_id,status,assigned_at),
  CONSTRAINT fk_order_assignments_order
    FOREIGN KEY (tenant_id,order_id) REFERENCES orders(tenant_id,id),
  CONSTRAINT fk_order_assignments_assignee
    FOREIGN KEY (tenant_id,assignee_user_id) REFERENCES users(tenant_id,id),
  CONSTRAINT fk_order_assignments_actor
    FOREIGN KEY (tenant_id,assigned_by_user_id) REFERENCES users(tenant_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
