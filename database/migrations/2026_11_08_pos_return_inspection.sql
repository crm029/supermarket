-- Controlled inspection workflow for goods returned through the in-person POS.
-- Only explicitly inspected, sealed, in-date items may re-enter sellable stock.
INSERT INTO permissions (permission_key,scope,description) VALUES
('pos.refund.inspect','tenant','بازرسی و تعیین تکلیف کالای مرجوعی صندوق')
ON DUPLICATE KEY UPDATE description=VALUES(description);

INSERT IGNORE INTO role_permissions (role_scope_id,role_id,permission_id)
SELECT r.role_scope_id,r.id,p.id
FROM roles r JOIN permissions p ON p.scope='tenant' AND p.permission_key='pos.refund.inspect'
WHERE r.tenant_id IS NOT NULL AND r.role_key='store_owner';

ALTER TABLE pos_refund_items
  MODIFY stock_status ENUM('pending_inspection','restocked','rejected') NOT NULL DEFAULT 'pending_inspection';

CREATE TABLE pos_refund_inspections (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  refund_item_id BIGINT UNSIGNED NOT NULL,
  inspector_user_id BIGINT UNSIGNED NOT NULL,
  decision ENUM('restock','reject') NOT NULL,
  inspection_note VARCHAR(500) NOT NULL,
  warehouse_id BIGINT UNSIGNED NOT NULL,
  batch_number VARCHAR(80) NULL,
  expiry_date DATE NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pos_refund_inspection_item (tenant_id,refund_item_id),
  UNIQUE KEY uq_pos_refund_inspection_tenant_id (tenant_id,id),
  KEY ix_pos_refund_inspection_date (tenant_id,created_at),
  CONSTRAINT fk_pos_refund_inspection_item FOREIGN KEY (tenant_id,refund_item_id) REFERENCES pos_refund_items(tenant_id,id),
  CONSTRAINT fk_pos_refund_inspection_actor FOREIGN KEY (tenant_id,inspector_user_id) REFERENCES users(tenant_id,id),
  CONSTRAINT fk_pos_refund_inspection_warehouse FOREIGN KEY (tenant_id,warehouse_id) REFERENCES warehouses(tenant_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
