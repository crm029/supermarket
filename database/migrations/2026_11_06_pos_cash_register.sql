-- Tenant-scoped cashier shifts and cash-only counter sales.
-- Apply after the stock batch and permission migrations.

INSERT INTO permissions (permission_key, scope, description) VALUES
('pos.read','tenant','مشاهده وضعیت صندوق و فروش‌های حضوری'),
('pos.shift.open','tenant','بازکردن شیفت صندوق‌داری'),
('pos.shift.close','tenant','بستن شیفت صندوق‌داری و ثبت شمارش وجه'),
('pos.sale.create','tenant','ثبت فروش نقدی حضوری')
ON DUPLICATE KEY UPDATE description=VALUES(description);

CREATE TABLE pos_shifts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  cashier_user_id BIGINT UNSIGNED NOT NULL,
  warehouse_id BIGINT UNSIGNED NOT NULL,
  status ENUM('open','closed') NOT NULL DEFAULT 'open',
  opening_float_rial BIGINT UNSIGNED NOT NULL DEFAULT 0,
  expected_cash_rial BIGINT UNSIGNED NULL,
  counted_cash_rial BIGINT UNSIGNED NULL,
  cash_variance_rial BIGINT NULL,
  opening_note VARCHAR(255) NULL,
  closing_note VARCHAR(255) NULL,
  opened_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  closed_at TIMESTAMP NULL,
  active_cashier_id BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN status='open' THEN cashier_user_id ELSE NULL END) STORED,
  UNIQUE KEY uq_pos_one_open_shift (tenant_id,active_cashier_id),
  KEY ix_pos_shift_history (tenant_id,status,opened_at),
  CONSTRAINT fk_pos_shift_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
  CONSTRAINT fk_pos_shift_cashier FOREIGN KEY (cashier_user_id) REFERENCES users(id),
  CONSTRAINT fk_pos_shift_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pos_sales (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  shift_id BIGINT UNSIGNED NOT NULL,
  cashier_user_id BIGINT UNSIGNED NOT NULL,
  sale_number VARCHAR(36) NOT NULL,
  idempotency_key VARCHAR(100) NOT NULL,
  request_hash CHAR(64) NOT NULL,
  subtotal_rial BIGINT UNSIGNED NOT NULL,
  discount_rial BIGINT UNSIGNED NOT NULL DEFAULT 0,
  total_rial BIGINT UNSIGNED NOT NULL,
  cash_received_rial BIGINT UNSIGNED NOT NULL,
  change_rial BIGINT UNSIGNED NOT NULL,
  note VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pos_sale_number (tenant_id,sale_number),
  UNIQUE KEY uq_pos_sale_idempotency (tenant_id,cashier_user_id,idempotency_key),
  KEY ix_pos_sales_shift (tenant_id,shift_id,created_at),
  CONSTRAINT fk_pos_sale_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
  CONSTRAINT fk_pos_sale_shift FOREIGN KEY (shift_id) REFERENCES pos_shifts(id),
  CONSTRAINT fk_pos_sale_cashier FOREIGN KEY (cashier_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pos_sale_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  sale_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  product_title_snapshot VARCHAR(180) NOT NULL,
  sku_snapshot VARCHAR(80) NOT NULL,
  unit_price_rial BIGINT UNSIGNED NOT NULL,
  quantity BIGINT UNSIGNED NOT NULL,
  line_total_rial BIGINT UNSIGNED NOT NULL,
  KEY ix_pos_sale_items_sale (tenant_id,sale_id),
  CONSTRAINT fk_pos_sale_item_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
  CONSTRAINT fk_pos_sale_item_sale FOREIGN KEY (sale_id) REFERENCES pos_sales(id),
  CONSTRAINT fk_pos_sale_item_product FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
