-- Introduce batch-level inventory metadata and backfill the current aggregate stock.
CREATE TABLE stock_batches (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  warehouse_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  receipt_item_id BIGINT UNSIGNED NULL,
  batch_number VARCHAR(80) NOT NULL,
  expiry_date DATE NULL,
  received_quantity BIGINT UNSIGNED NOT NULL,
  on_hand BIGINT UNSIGNED NOT NULL,
  reserved BIGINT UNSIGNED NOT NULL DEFAULT 0,
  unit_cost_rial BIGINT UNSIGNED NOT NULL DEFAULT 0,
  received_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_stock_batch_tenant_id (tenant_id,id),
  UNIQUE KEY uq_stock_batch_code (tenant_id,warehouse_id,product_id,batch_number),
  KEY ix_stock_batch_fefo (tenant_id,warehouse_id,product_id,expiry_date,received_at,id),
  KEY ix_stock_batch_expiry (tenant_id,expiry_date,on_hand),
  KEY ix_stock_batch_receipt (tenant_id,receipt_item_id),
  FOREIGN KEY (tenant_id,warehouse_id) REFERENCES warehouses (tenant_id,id),
  FOREIGN KEY (tenant_id,product_id) REFERENCES products (tenant_id,id),
  FOREIGN KEY (tenant_id,receipt_item_id) REFERENCES purchase_order_receipt_items (tenant_id,id),
  CHECK (received_quantity >= on_hand),
  CHECK (reserved <= on_hand)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Preserve existing stock without inventing expiry dates or supplier batch identifiers.
INSERT INTO stock_batches
  (tenant_id,warehouse_id,product_id,batch_number,received_quantity,on_hand,reserved,unit_cost_rial)
SELECT tenant_id,warehouse_id,product_id,
       CONCAT('LEGACY-',warehouse_id,'-',product_id),
       on_hand,on_hand,reserved,0
FROM stock_levels
WHERE on_hand > 0;

-- Link each order reservation to the exact lots reserved, including pre-migration
-- active reservations which are represented by the single LEGACY lot per SKU/location.
ALTER TABLE stock_reservations ADD UNIQUE KEY uq_stock_reservation_tenant_id (tenant_id,id);

CREATE TABLE stock_reservation_batches (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  reservation_id BIGINT UNSIGNED NOT NULL,
  batch_id BIGINT UNSIGNED NOT NULL,
  quantity BIGINT UNSIGNED NOT NULL,
  status ENUM('active','committed','released','expired') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_reservation_batch (tenant_id,reservation_id,batch_id),
  KEY ix_reservation_batch_status (tenant_id,reservation_id,status),
  FOREIGN KEY (tenant_id,reservation_id) REFERENCES stock_reservations (tenant_id,id),
  FOREIGN KEY (tenant_id,batch_id) REFERENCES stock_batches (tenant_id,id),
  CHECK (quantity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO stock_reservation_batches (tenant_id,reservation_id,batch_id,quantity,status)
SELECT sr.tenant_id,sr.id,sb.id,sr.quantity,'active'
FROM stock_reservations sr
JOIN stock_batches sb
  ON sb.tenant_id=sr.tenant_id AND sb.warehouse_id=sr.warehouse_id
 AND sb.product_id=sr.product_id
 AND sb.batch_number=CONCAT('LEGACY-',sr.warehouse_id,'-',sr.product_id)
WHERE sr.status='active';
