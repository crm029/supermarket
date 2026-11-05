-- Auditable supplier returns tied to actual received purchase-order receipt lines.
ALTER TABLE stock_movements
  MODIFY movement_type ENUM('receipt','transfer_in','transfer_out','adjustment','reservation','release','sale','return','supplier_return') NOT NULL;

CREATE TABLE supplier_returns (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  supplier_id BIGINT UNSIGNED NOT NULL,
  warehouse_id BIGINT UNSIGNED NOT NULL,
  return_number VARCHAR(40) NOT NULL,
  reason VARCHAR(255) NOT NULL,
  notes VARCHAR(1000) NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_supplier_return_tenant_id (tenant_id,id),
  UNIQUE KEY uq_supplier_return_number (tenant_id,return_number),
  KEY ix_supplier_return_supplier (tenant_id,supplier_id,created_at),
  FOREIGN KEY (tenant_id,supplier_id) REFERENCES suppliers (tenant_id,id),
  FOREIGN KEY (tenant_id,warehouse_id) REFERENCES warehouses (tenant_id,id),
  FOREIGN KEY (tenant_id,created_by) REFERENCES users (tenant_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE supplier_return_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  supplier_return_id BIGINT UNSIGNED NOT NULL,
  receipt_item_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  unit_cost_rial BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_supplier_return_item_return (tenant_id,supplier_return_id),
  KEY ix_supplier_return_item_receipt (tenant_id,receipt_item_id),
  FOREIGN KEY (tenant_id,supplier_return_id) REFERENCES supplier_returns (tenant_id,id),
  FOREIGN KEY (tenant_id,receipt_item_id) REFERENCES purchase_order_receipt_items (tenant_id,id),
  FOREIGN KEY (tenant_id,product_id) REFERENCES products (tenant_id,id),
  CHECK (quantity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
