-- Supplier directory and auditable purchase order / partial receiving workflow.
CREATE TABLE suppliers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(180) NOT NULL,
  contact_person VARCHAR(160) NULL,
  phone VARCHAR(30) NULL,
  email VARCHAR(190) NULL,
  address TEXT NULL,
  notes TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_supplier_tenant_id (tenant_id,id),
  KEY ix_supplier_active (tenant_id,is_active,name),
  FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE purchase_orders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  supplier_id BIGINT UNSIGNED NOT NULL,
  order_number VARCHAR(40) NOT NULL,
  status ENUM('ordered','partially_received','received','cancelled') NOT NULL DEFAULT 'ordered',
  expected_at DATE NULL,
  notes TEXT NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_purchase_order_tenant_id (tenant_id,id),
  UNIQUE KEY uq_purchase_order_number (tenant_id,order_number),
  KEY ix_purchase_order_status (tenant_id,status,created_at),
  FOREIGN KEY (tenant_id,supplier_id) REFERENCES suppliers (tenant_id,id),
  FOREIGN KEY (tenant_id,created_by) REFERENCES users (tenant_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE purchase_order_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  purchase_order_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  ordered_quantity INT UNSIGNED NOT NULL,
  received_quantity INT UNSIGNED NOT NULL DEFAULT 0,
  unit_cost_rial BIGINT UNSIGNED NOT NULL DEFAULT 0,
  notes VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_purchase_order_item_tenant_id (tenant_id,id),
  UNIQUE KEY uq_purchase_order_product (tenant_id,purchase_order_id,product_id),
  KEY ix_purchase_order_item_product (tenant_id,product_id),
  FOREIGN KEY (tenant_id,purchase_order_id) REFERENCES purchase_orders (tenant_id,id),
  FOREIGN KEY (tenant_id,product_id) REFERENCES products (tenant_id,id),
  CHECK (ordered_quantity > 0),
  CHECK (received_quantity <= ordered_quantity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE purchase_order_receipts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  purchase_order_id BIGINT UNSIGNED NOT NULL,
  warehouse_id BIGINT UNSIGNED NOT NULL,
  receipt_number VARCHAR(40) NOT NULL,
  received_by BIGINT UNSIGNED NOT NULL,
  notes VARCHAR(500) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_purchase_receipt_tenant_id (tenant_id,id),
  UNIQUE KEY uq_purchase_receipt_number (tenant_id,receipt_number),
  KEY ix_purchase_receipt_order (tenant_id,purchase_order_id,created_at),
  FOREIGN KEY (tenant_id,purchase_order_id) REFERENCES purchase_orders (tenant_id,id),
  FOREIGN KEY (tenant_id,warehouse_id) REFERENCES warehouses (tenant_id,id),
  FOREIGN KEY (tenant_id,received_by) REFERENCES users (tenant_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE purchase_order_receipt_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  receipt_id BIGINT UNSIGNED NOT NULL,
  purchase_order_item_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  unit_cost_rial BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_purchase_receipt_item (tenant_id,receipt_id),
  FOREIGN KEY (tenant_id,receipt_id) REFERENCES purchase_order_receipts (tenant_id,id),
  FOREIGN KEY (tenant_id,purchase_order_item_id) REFERENCES purchase_order_items (tenant_id,id),
  FOREIGN KEY (tenant_id,product_id) REFERENCES products (tenant_id,id),
  CHECK (quantity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
