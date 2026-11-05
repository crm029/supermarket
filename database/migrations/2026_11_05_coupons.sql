-- Tenant-scoped promotion coupons and order redemption snapshots.
CREATE TABLE coupons (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(40) NOT NULL,
  title VARCHAR(120) NOT NULL,
  discount_type ENUM('percentage','fixed') NOT NULL,
  discount_value BIGINT UNSIGNED NOT NULL,
  max_discount_rial BIGINT UNSIGNED NULL,
  minimum_order_rial BIGINT UNSIGNED NOT NULL DEFAULT 0,
  usage_limit INT UNSIGNED NULL,
  per_user_limit INT UNSIGNED NOT NULL DEFAULT 1,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_coupon_tenant_id (tenant_id,id),
  UNIQUE KEY uq_coupon_tenant_code (tenant_id,code),
  KEY ix_coupon_availability (tenant_id,is_active,starts_at,ends_at),
  FOREIGN KEY (tenant_id) REFERENCES tenants(id),
  CHECK (discount_value > 0),
  CHECK (discount_type <> 'percentage' OR discount_value <= 100),
  CHECK (discount_type <> 'fixed' OR max_discount_rial IS NULL),
  CHECK (max_discount_rial IS NULL OR max_discount_rial > 0),
  CHECK (usage_limit IS NULL OR usage_limit > 0),
  CHECK (per_user_limit BETWEEN 1 AND 1000),
  CHECK (ends_at > starts_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE orders
  ADD COLUMN coupon_id BIGINT UNSIGNED NULL AFTER pickup_slot_id,
  ADD KEY ix_orders_coupon (tenant_id,coupon_id),
  ADD CONSTRAINT fk_orders_coupon
    FOREIGN KEY (tenant_id,coupon_id)
    REFERENCES coupons (tenant_id,id);

CREATE TABLE coupon_redemptions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  coupon_id BIGINT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  discount_rial BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_coupon_redemption_order (tenant_id,order_id),
  KEY ix_coupon_redemption_usage (tenant_id,coupon_id,user_id),
  FOREIGN KEY (tenant_id,coupon_id) REFERENCES coupons (tenant_id,id),
  FOREIGN KEY (tenant_id,order_id) REFERENCES orders (tenant_id,id),
  FOREIGN KEY (tenant_id,user_id) REFERENCES users (tenant_id,id),
  CHECK (discount_rial > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
