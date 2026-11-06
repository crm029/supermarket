-- Supermarket 360 — MySQL 8 / MariaDB schema foundation
-- All monetary values are integer RIAL. Apply to a fresh database only.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE tenants (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  slug VARCHAR(80) NOT NULL UNIQUE,
  status ENUM('pending','active','suspended') NOT NULL DEFAULT 'pending',
  timezone VARCHAR(64) NOT NULL DEFAULT 'Asia/Tehran',
  currency CHAR(3) NOT NULL DEFAULT 'IRR',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tenant_domains (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  host VARCHAR(253) NOT NULL UNIQUE,
  is_primary BOOLEAN NOT NULL DEFAULT FALSE,
  verified_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_domains_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  full_name VARCHAR(160) NOT NULL,
  mobile VARCHAR(20) NOT NULL,
  password_hash VARCHAR(255) NULL,
  status ENUM('pending','active','blocked') NOT NULL DEFAULT 'pending',
  referral_code VARCHAR(16) NOT NULL,
  referred_by_user_id BIGINT UNSIGNED NULL,
  mobile_verified_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_mobile (tenant_id,mobile),
  UNIQUE KEY uq_user_referral (tenant_id,referral_code),
  CONSTRAINT fk_users_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
  CONSTRAINT fk_users_referrer FOREIGN KEY (referred_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE roles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NULL,
  role_key VARCHAR(60) NOT NULL,
  title VARCHAR(100) NOT NULL,
  UNIQUE KEY uq_role_scope (tenant_id,role_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_roles (
  user_id BIGINT UNSIGNED NOT NULL,
  role_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY(user_id,role_id),
  FOREIGN KEY(user_id) REFERENCES users(id),
  FOREIGN KEY(role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE store_settings (
  tenant_id BIGINT UNSIGNED NOT NULL,
  setting_key VARCHAR(100) NOT NULL,
  setting_value JSON NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY(tenant_id,setting_key),
  FOREIGN KEY(tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE media_assets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  media_type ENUM('logo','hero','aisle','panorama','category','product','other') NOT NULL,
  storage_key VARCHAR(500) NOT NULL,
  alt_text VARCHAR(255) NOT NULL DEFAULT '',
  width INT UNSIGNED NULL,
  height INT UNSIGNED NULL,
  sort_order INT NOT NULL DEFAULT 0,
  metadata JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_media_storage (tenant_id,storage_key(190)),
  FOREIGN KEY(tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  parent_id BIGINT UNSIGNED NULL,
  title VARCHAR(120) NOT NULL,
  slug VARCHAR(140) NOT NULL,
  image_media_id BIGINT UNSIGNED NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  UNIQUE KEY uq_category_slug (tenant_id,slug),
  FOREIGN KEY(tenant_id) REFERENCES tenants(id),
  FOREIGN KEY(parent_id) REFERENCES categories(id),
  FOREIGN KEY(image_media_id) REFERENCES media_assets(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  category_id BIGINT UNSIGNED NULL,
  sku VARCHAR(80) NOT NULL,
  barcode VARCHAR(80) NULL,
  title VARCHAR(180) NOT NULL,
  brand VARCHAR(120) NULL,
  description TEXT NULL,
  unit_label VARCHAR(80) NOT NULL DEFAULT 'عدد',
  price_rial BIGINT UNSIGNED NOT NULL,
  compare_at_rial BIGINT UNSIGNED NULL,
  image_media_id BIGINT UNSIGNED NULL,
  is_weighted BOOLEAN NOT NULL DEFAULT FALSE,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_product_sku (tenant_id,sku),
  KEY ix_product_search (tenant_id,is_active,title),
  FOREIGN KEY(tenant_id) REFERENCES tenants(id),
  FOREIGN KEY(category_id) REFERENCES categories(id),
  FOREIGN KEY(image_media_id) REFERENCES media_assets(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE store_scenes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(140) NOT NULL,
  scene_type ENUM('aisle_photo','panorama','webgl_scene') NOT NULL,
  media_id BIGINT UNSIGNED NULL,
  fallback_media_id BIGINT UNSIGNED NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_published BOOLEAN NOT NULL DEFAULT FALSE,
  metadata JSON NULL,
  FOREIGN KEY(tenant_id) REFERENCES tenants(id),
  FOREIGN KEY(media_id) REFERENCES media_assets(id),
  FOREIGN KEY(fallback_media_id) REFERENCES media_assets(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE scene_hotspots (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  scene_id BIGINT UNSIGNED NOT NULL,
  target_scene_id BIGINT UNSIGNED NULL,
  product_id BIGINT UNSIGNED NULL,
  label VARCHAR(120) NOT NULL,
  x DECIMAL(8,5) NOT NULL,
  y DECIMAL(8,5) NOT NULL,
  action ENUM('navigate','product','category') NOT NULL,
  payload JSON NULL,
  FOREIGN KEY(tenant_id) REFERENCES tenants(id),
  FOREIGN KEY(scene_id) REFERENCES store_scenes(id),
  FOREIGN KEY(target_scene_id) REFERENCES store_scenes(id),
  FOREIGN KEY(product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE warehouses (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(120) NOT NULL,
  warehouse_type ENUM('main','store','cold','other') NOT NULL DEFAULT 'main',
  address TEXT NULL,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  FOREIGN KEY(tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stock_levels (
  tenant_id BIGINT UNSIGNED NOT NULL,
  warehouse_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  on_hand BIGINT NOT NULL DEFAULT 0,
  reserved BIGINT NOT NULL DEFAULT 0,
  PRIMARY KEY(tenant_id,warehouse_id,product_id),
  FOREIGN KEY(tenant_id) REFERENCES tenants(id),
  FOREIGN KEY(warehouse_id) REFERENCES warehouses(id),
  FOREIGN KEY(product_id) REFERENCES products(id),
  CHECK(on_hand >= 0 AND reserved >= 0 AND reserved <= on_hand)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stock_movements (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  warehouse_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  movement_type ENUM('receipt','transfer_in','transfer_out','adjustment','reservation','release','sale','return') NOT NULL,
  quantity BIGINT NOT NULL,
  reference_type VARCHAR(60) NULL,
  reference_id BIGINT UNSIGNED NULL,
  reason VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_stock_history (tenant_id,product_id,created_at),
  FOREIGN KEY(tenant_id) REFERENCES tenants(id),
  FOREIGN KEY(warehouse_id) REFERENCES warehouses(id),
  FOREIGN KEY(product_id) REFERENCES products(id),
  FOREIGN KEY(actor_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customer_addresses (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(80) NOT NULL,
  recipient VARCHAR(160) NOT NULL,
  mobile VARCHAR(20) NOT NULL,
  province VARCHAR(100) NULL,
  city VARCHAR(100) NULL,
  address TEXT NOT NULL,
  postal_code VARCHAR(20) NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  FOREIGN KEY(tenant_id) REFERENCES tenants(id),
  FOREIGN KEY(user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE orders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  address_id BIGINT UNSIGNED NULL,
  order_number VARCHAR(32) NOT NULL,
  status ENUM('pending_payment','paid','accepted','picking','packed','ready_pickup','dispatched','out_for_delivery','delivered','cancelled','refunded') NOT NULL DEFAULT 'pending_payment',
  fulfillment ENUM('delivery','pickup') NOT NULL,
  subtotal_rial BIGINT UNSIGNED NOT NULL,
  discount_rial BIGINT UNSIGNED NOT NULL DEFAULT 0,
  delivery_fee_rial BIGINT UNSIGNED NOT NULL DEFAULT 0,
  total_rial BIGINT UNSIGNED NOT NULL,
  customer_note TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_order_number (tenant_id,order_number),
  KEY ix_order_status (tenant_id,status,created_at),
  FOREIGN KEY(tenant_id) REFERENCES tenants(id),
  FOREIGN KEY(user_id) REFERENCES users(id),
  FOREIGN KEY(address_id) REFERENCES customer_addresses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  product_title_snapshot VARCHAR(180) NOT NULL,
  unit_price_rial BIGINT UNSIGNED NOT NULL,
  quantity BIGINT UNSIGNED NOT NULL,
  line_total_rial BIGINT UNSIGNED NOT NULL,
  FOREIGN KEY(tenant_id) REFERENCES tenants(id),
  FOREIGN KEY(order_id) REFERENCES orders(id),
  FOREIGN KEY(product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stock_reservations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NOT NULL,
  warehouse_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  quantity BIGINT UNSIGNED NOT NULL,
  status ENUM('active','committed','released','expired') NOT NULL DEFAULT 'active',
  expires_at TIMESTAMP NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_reservation_expiry (status,expires_at),
  FOREIGN KEY(tenant_id) REFERENCES tenants(id),
  FOREIGN KEY(order_id) REFERENCES orders(id),
  FOREIGN KEY(warehouse_id) REFERENCES warehouses(id),
  FOREIGN KEY(product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payment_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NOT NULL,
  provider VARCHAR(40) NOT NULL,
  authority VARCHAR(120) NULL,
  idempotency_key VARCHAR(100) NOT NULL,
  amount_rial BIGINT UNSIGNED NOT NULL,
  status ENUM('created','redirected','verified','failed','refunded') NOT NULL DEFAULT 'created',
  provider_reference VARCHAR(120) NULL,
  verified_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_payment_idempotency (tenant_id,idempotency_key),
  UNIQUE KEY uq_payment_authority (provider,authority),
  FOREIGN KEY(tenant_id) REFERENCES tenants(id),
  FOREIGN KEY(order_id) REFERENCES orders(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wallet_accounts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_wallet_user (tenant_id,user_id),
  FOREIGN KEY(tenant_id) REFERENCES tenants(id),
  FOREIGN KEY(user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wallet_entries (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  wallet_id BIGINT UNSIGNED NOT NULL,
  entry_type ENUM('credit','debit','reversal') NOT NULL,
  amount_rial BIGINT UNSIGNED NOT NULL,
  source_type VARCHAR(60) NOT NULL,
  source_id BIGINT UNSIGNED NULL,
  idempotency_key VARCHAR(100) NOT NULL,
  note VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_wallet_idempotency (tenant_id,idempotency_key),
  KEY ix_wallet_history (wallet_id,created_at),
  FOREIGN KEY(tenant_id) REFERENCES tenants(id),
  FOREIGN KEY(wallet_id) REFERENCES wallet_accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE referral_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  referrer_user_id BIGINT UNSIGNED NOT NULL,
  referred_user_id BIGINT UNSIGNED NOT NULL,
  qualifying_order_id BIGINT UNSIGNED NULL,
  reward_rial BIGINT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('pending','qualified','rewarded','rejected') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_referral_pair (tenant_id,referred_user_id),
  FOREIGN KEY(tenant_id) REFERENCES tenants(id),
  FOREIGN KEY(referrer_user_id) REFERENCES users(id),
  FOREIGN KEY(referred_user_id) REFERENCES users(id),
  FOREIGN KEY(qualifying_order_id) REFERENCES orders(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  action VARCHAR(100) NOT NULL,
  entity_type VARCHAR(80) NOT NULL,
  entity_id BIGINT UNSIGNED NULL,
  ip_address VARBINARY(16) NULL,
  details JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_audit_tenant (tenant_id,created_at),
  FOREIGN KEY(tenant_id) REFERENCES tenants(id),
  FOREIGN KEY(actor_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
