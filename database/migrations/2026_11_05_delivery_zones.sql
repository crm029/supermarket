-- Tenant-specific delivery coverage, fees and minimum order amounts.
CREATE TABLE delivery_zones (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(120) NOT NULL,
  province VARCHAR(100) NOT NULL,
  city VARCHAR(100) NOT NULL,
  postal_prefix VARCHAR(10) NOT NULL DEFAULT '',
  delivery_fee_rial BIGINT UNSIGNED NOT NULL DEFAULT 0,
  minimum_order_rial BIGINT UNSIGNED NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_delivery_zone_match (tenant_id,province,city,postal_prefix),
  UNIQUE KEY uq_delivery_zone_tenant_id (tenant_id,id),
  KEY ix_delivery_zone_active (tenant_id,is_active,province,city),
  FOREIGN KEY (tenant_id) REFERENCES tenants(id),
  CHECK (delivery_fee_rial >= 0 AND minimum_order_rial >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE orders
  ADD COLUMN delivery_zone_id BIGINT UNSIGNED NULL AFTER address_id,
  ADD KEY ix_orders_delivery_zone (tenant_id,delivery_zone_id),
  ADD CONSTRAINT fk_orders_delivery_zone
    FOREIGN KEY (tenant_id,delivery_zone_id)
    REFERENCES delivery_zones (tenant_id,id);
