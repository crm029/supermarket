-- Capacity-controlled scheduled pickup windows.
CREATE TABLE pickup_slots (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(120) NOT NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NOT NULL,
  capacity INT UNSIGNED NOT NULL DEFAULT 1,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pickup_slot_tenant_id (tenant_id,id),
  UNIQUE KEY uq_pickup_slot_window (tenant_id,starts_at,ends_at),
  KEY ix_pickup_slot_availability (tenant_id,is_active,starts_at),
  FOREIGN KEY (tenant_id) REFERENCES tenants(id),
  CHECK (capacity BETWEEN 1 AND 1000),
  CHECK (ends_at > starts_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE orders
  ADD COLUMN pickup_slot_id BIGINT UNSIGNED NULL AFTER delivery_zone_id,
  ADD KEY ix_orders_pickup_slot (tenant_id,pickup_slot_id),
  ADD CONSTRAINT fk_orders_pickup_slot
    FOREIGN KEY (tenant_id,pickup_slot_id)
    REFERENCES pickup_slots (tenant_id,id);
