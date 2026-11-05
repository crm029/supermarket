-- Persistent tenant-scoped customer carts for cross-device continuity.
CREATE TABLE customer_carts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_customer_cart_owner (tenant_id,user_id),
  UNIQUE KEY uq_customer_cart_tenant_id (tenant_id,id),
  CONSTRAINT fk_customer_carts_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
  CONSTRAINT fk_customer_carts_user FOREIGN KEY (tenant_id,user_id) REFERENCES users(tenant_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customer_cart_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  cart_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  quantity SMALLINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_customer_cart_product (tenant_id,cart_id,product_id),
  CONSTRAINT fk_customer_cart_items_cart FOREIGN KEY (tenant_id,cart_id) REFERENCES customer_carts(tenant_id,id),
  CONSTRAINT fk_customer_cart_items_product FOREIGN KEY (tenant_id,product_id) REFERENCES products(tenant_id,id),
  CONSTRAINT chk_customer_cart_quantity CHECK (quantity BETWEEN 1 AND 99)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
