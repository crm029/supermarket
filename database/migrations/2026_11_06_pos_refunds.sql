-- Audited cash refunds for in-person POS sales.
-- Returned goods are quarantined logically (pending_inspection) and are NOT
-- added back to sellable stock by this migration or the refund endpoint.
INSERT INTO permissions (permission_key,scope,description) VALUES
('pos.refund.create','tenant','ثبت بازپرداخت نقدی فروش حضوری')
ON DUPLICATE KEY UPDATE description=VALUES(description);

INSERT IGNORE INTO role_permissions (role_scope_id,role_id,permission_id)
SELECT r.role_scope_id,r.id,p.id
FROM roles r JOIN permissions p ON p.scope='tenant' AND p.permission_key='pos.refund.create'
WHERE r.tenant_id IS NOT NULL AND r.role_key='store_owner';

ALTER TABLE pos_sale_items
  ADD UNIQUE KEY uq_pos_sale_item_tenant_id (tenant_id,id);

CREATE TABLE pos_refunds (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  sale_id BIGINT UNSIGNED NOT NULL,
  shift_id BIGINT UNSIGNED NOT NULL,
  cashier_user_id BIGINT UNSIGNED NOT NULL,
  idempotency_key VARCHAR(100) NOT NULL,
  request_hash CHAR(64) NOT NULL,
  amount_rial BIGINT UNSIGNED NOT NULL,
  reason VARCHAR(500) NOT NULL,
  stock_status ENUM('pending_inspection') NOT NULL DEFAULT 'pending_inspection',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pos_refund_tenant_id (tenant_id,id),
  UNIQUE KEY uq_pos_refund_idempotency (tenant_id,cashier_user_id,idempotency_key),
  KEY ix_pos_refunds_sale (tenant_id,sale_id,created_at),
  KEY ix_pos_refunds_shift (tenant_id,shift_id,created_at),
  CONSTRAINT fk_pos_refund_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
  CONSTRAINT fk_pos_refund_sale_tenant FOREIGN KEY (tenant_id,sale_id) REFERENCES pos_sales(tenant_id,id),
  CONSTRAINT fk_pos_refund_shift_tenant FOREIGN KEY (tenant_id,shift_id) REFERENCES pos_shifts(tenant_id,id),
  CONSTRAINT fk_pos_refund_cashier_tenant FOREIGN KEY (tenant_id,cashier_user_id) REFERENCES users(tenant_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pos_refund_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  refund_id BIGINT UNSIGNED NOT NULL,
  sale_item_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  product_title_snapshot VARCHAR(180) NOT NULL,
  sku_snapshot VARCHAR(80) NOT NULL,
  quantity BIGINT UNSIGNED NOT NULL,
  amount_rial BIGINT UNSIGNED NOT NULL,
  stock_status ENUM('pending_inspection') NOT NULL DEFAULT 'pending_inspection',
  UNIQUE KEY uq_pos_refund_item_tenant_id (tenant_id,id),
  UNIQUE KEY uq_pos_refund_item_once (tenant_id,refund_id,sale_item_id),
  KEY ix_pos_refund_items_sale_item (tenant_id,sale_item_id),
  CONSTRAINT fk_pos_refund_item_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
  CONSTRAINT fk_pos_refund_item_refund_tenant FOREIGN KEY (tenant_id,refund_id) REFERENCES pos_refunds(tenant_id,id),
  CONSTRAINT fk_pos_refund_item_sale_item_tenant FOREIGN KEY (tenant_id,sale_item_id) REFERENCES pos_sale_items(tenant_id,id),
  CONSTRAINT fk_pos_refund_item_product_tenant FOREIGN KEY (tenant_id,product_id) REFERENCES products(tenant_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
