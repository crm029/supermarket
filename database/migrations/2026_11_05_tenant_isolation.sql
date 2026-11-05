-- Supermarket 360: tenant-isolation hardening migration
-- Apply AFTER the original database/schema.sql on MySQL 8.0.16+ or a
-- compatible MariaDB release. Back up the database first.
-- This migration is intentionally additive: existing single-column FKs remain,
-- while composite tenant-aware FKs close cross-tenant reference paths.

SET NAMES utf8mb4;

-- Parent keys used by tenant-scoped composite foreign keys.
ALTER TABLE users ADD UNIQUE KEY uq_users_tenant_id (tenant_id, id);
ALTER TABLE categories ADD UNIQUE KEY uq_categories_tenant_id (tenant_id, id);
ALTER TABLE media_assets ADD UNIQUE KEY uq_media_tenant_id (tenant_id, id);
ALTER TABLE products ADD UNIQUE KEY uq_products_tenant_id (tenant_id, id);
ALTER TABLE store_scenes ADD UNIQUE KEY uq_scenes_tenant_id (tenant_id, id);
ALTER TABLE warehouses ADD UNIQUE KEY uq_warehouses_tenant_id (tenant_id, id);
ALTER TABLE customer_addresses ADD UNIQUE KEY uq_addresses_tenant_id (tenant_id, id);
ALTER TABLE orders ADD UNIQUE KEY uq_orders_tenant_id (tenant_id, id);
ALTER TABLE wallet_accounts ADD UNIQUE KEY uq_wallets_tenant_id (tenant_id, id);

-- User referral must remain inside the same tenant.
ALTER TABLE users
  ADD CONSTRAINT fk_users_referrer_same_tenant
  FOREIGN KEY (tenant_id, referred_by_user_id)
  REFERENCES users (tenant_id, id);

-- Category tree and category media must remain inside the same tenant.
ALTER TABLE categories
  ADD CONSTRAINT fk_categories_parent_same_tenant
    FOREIGN KEY (tenant_id, parent_id) REFERENCES categories (tenant_id, id),
  ADD CONSTRAINT fk_categories_media_same_tenant
    FOREIGN KEY (tenant_id, image_media_id) REFERENCES media_assets (tenant_id, id);

-- Product category and image ownership.
ALTER TABLE products
  ADD CONSTRAINT fk_products_category_same_tenant
    FOREIGN KEY (tenant_id, category_id) REFERENCES categories (tenant_id, id),
  ADD CONSTRAINT fk_products_media_same_tenant
    FOREIGN KEY (tenant_id, image_media_id) REFERENCES media_assets (tenant_id, id);

-- Scene media and all hotspot targets.
ALTER TABLE store_scenes
  ADD CONSTRAINT fk_scenes_media_same_tenant
    FOREIGN KEY (tenant_id, media_id) REFERENCES media_assets (tenant_id, id),
  ADD CONSTRAINT fk_scenes_fallback_media_same_tenant
    FOREIGN KEY (tenant_id, fallback_media_id) REFERENCES media_assets (tenant_id, id);

ALTER TABLE scene_hotspots
  ADD CONSTRAINT fk_hotspots_scene_same_tenant
    FOREIGN KEY (tenant_id, scene_id) REFERENCES store_scenes (tenant_id, id),
  ADD CONSTRAINT fk_hotspots_target_scene_same_tenant
    FOREIGN KEY (tenant_id, target_scene_id) REFERENCES store_scenes (tenant_id, id),
  ADD CONSTRAINT fk_hotspots_product_same_tenant
    FOREIGN KEY (tenant_id, product_id) REFERENCES products (tenant_id, id);

-- Inventory references.
ALTER TABLE stock_levels
  ADD CONSTRAINT fk_stock_levels_warehouse_same_tenant
    FOREIGN KEY (tenant_id, warehouse_id) REFERENCES warehouses (tenant_id, id),
  ADD CONSTRAINT fk_stock_levels_product_same_tenant
    FOREIGN KEY (tenant_id, product_id) REFERENCES products (tenant_id, id);

ALTER TABLE stock_movements
  ADD CONSTRAINT fk_stock_movements_warehouse_same_tenant
    FOREIGN KEY (tenant_id, warehouse_id) REFERENCES warehouses (tenant_id, id),
  ADD CONSTRAINT fk_stock_movements_product_same_tenant
    FOREIGN KEY (tenant_id, product_id) REFERENCES products (tenant_id, id),
  ADD CONSTRAINT fk_stock_movements_actor_same_tenant
    FOREIGN KEY (tenant_id, actor_user_id) REFERENCES users (tenant_id, id);

-- Customer addresses, orders and order lines.
ALTER TABLE customer_addresses
  ADD CONSTRAINT fk_addresses_user_same_tenant
    FOREIGN KEY (tenant_id, user_id) REFERENCES users (tenant_id, id);

ALTER TABLE orders
  ADD CONSTRAINT fk_orders_user_same_tenant
    FOREIGN KEY (tenant_id, user_id) REFERENCES users (tenant_id, id),
  ADD CONSTRAINT fk_orders_address_same_tenant
    FOREIGN KEY (tenant_id, address_id) REFERENCES customer_addresses (tenant_id, id);

ALTER TABLE order_items
  ADD CONSTRAINT fk_order_items_order_same_tenant
    FOREIGN KEY (tenant_id, order_id) REFERENCES orders (tenant_id, id),
  ADD CONSTRAINT fk_order_items_product_same_tenant
    FOREIGN KEY (tenant_id, product_id) REFERENCES products (tenant_id, id);

ALTER TABLE stock_reservations
  ADD CONSTRAINT fk_reservations_order_same_tenant
    FOREIGN KEY (tenant_id, order_id) REFERENCES orders (tenant_id, id),
  ADD CONSTRAINT fk_reservations_warehouse_same_tenant
    FOREIGN KEY (tenant_id, warehouse_id) REFERENCES warehouses (tenant_id, id),
  ADD CONSTRAINT fk_reservations_product_same_tenant
    FOREIGN KEY (tenant_id, product_id) REFERENCES products (tenant_id, id);

ALTER TABLE payment_attempts
  ADD CONSTRAINT fk_payments_order_same_tenant
    FOREIGN KEY (tenant_id, order_id) REFERENCES orders (tenant_id, id);

-- Wallet ownership and ledger.
ALTER TABLE wallet_accounts
  ADD CONSTRAINT fk_wallets_user_same_tenant
    FOREIGN KEY (tenant_id, user_id) REFERENCES users (tenant_id, id);

ALTER TABLE wallet_entries
  ADD CONSTRAINT fk_wallet_entries_wallet_same_tenant
    FOREIGN KEY (tenant_id, wallet_id) REFERENCES wallet_accounts (tenant_id, id);

-- Referral events cannot connect users or orders across tenants.
ALTER TABLE referral_events
  ADD CONSTRAINT fk_referrals_referrer_same_tenant
    FOREIGN KEY (tenant_id, referrer_user_id) REFERENCES users (tenant_id, id),
  ADD CONSTRAINT fk_referrals_referred_same_tenant
    FOREIGN KEY (tenant_id, referred_user_id) REFERENCES users (tenant_id, id),
  ADD CONSTRAINT fk_referrals_order_same_tenant
    FOREIGN KEY (tenant_id, qualifying_order_id) REFERENCES orders (tenant_id, id);

-- Audit actor must belong to the tenant recorded on the audit row.
-- A trigger is used because tenant_id is nullable for platform-level events.
-- Backfill user_roles. This is intentionally performed before adding the
-- tenant-aware user FK; existing role links are then checked by the triggers
-- on subsequent writes. Review tenant-specific legacy role assignments first.
ALTER TABLE user_roles ADD COLUMN tenant_id BIGINT UNSIGNED NULL AFTER user_id;
UPDATE user_roles ur
JOIN users u ON u.id = ur.user_id
SET ur.tenant_id = u.tenant_id;
ALTER TABLE user_roles
  MODIFY tenant_id BIGINT UNSIGNED NOT NULL,
  ADD KEY ix_user_roles_tenant_role (tenant_id, role_id),
  ADD CONSTRAINT fk_user_roles_user_same_tenant
    FOREIGN KEY (tenant_id, user_id) REFERENCES users (tenant_id, id);



-- Stop before trigger creation if a legacy tenant-specific role assignment is
-- already inconsistent. Composite foreign keys below similarly reject any
-- cross-tenant legacy reference rather than silently rewriting ownership.
DELIMITER //
CREATE PROCEDURE assert_legacy_role_scope()
BEGIN
  IF EXISTS (
    SELECT 1 FROM user_roles ur
    JOIN roles r ON r.id = ur.role_id
    WHERE r.tenant_id IS NOT NULL AND r.tenant_id <> ur.tenant_id
  ) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Legacy user_roles contains cross-tenant role assignments';
  END IF;
END//
DELIMITER ;
CALL assert_legacy_role_scope();
DROP PROCEDURE assert_legacy_role_scope;

DELIMITER //
CREATE TRIGGER trg_audit_actor_tenant_insert
BEFORE INSERT ON audit_logs
FOR EACH ROW
BEGIN
  DECLARE actor_tenant BIGINT UNSIGNED DEFAULT NULL;
  IF NEW.actor_user_id IS NOT NULL THEN
    SELECT tenant_id INTO actor_tenant FROM users WHERE id = NEW.actor_user_id LIMIT 1;
    IF actor_tenant IS NULL OR NEW.tenant_id IS NULL OR actor_tenant <> NEW.tenant_id THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Audit actor must belong to the recorded tenant';
    END IF;
  END IF;
END//

CREATE TRIGGER trg_audit_actor_tenant_update
BEFORE UPDATE ON audit_logs
FOR EACH ROW
BEGIN
  DECLARE actor_tenant BIGINT UNSIGNED DEFAULT NULL;
  IF NEW.actor_user_id IS NOT NULL THEN
    SELECT tenant_id INTO actor_tenant FROM users WHERE id = NEW.actor_user_id LIMIT 1;
    IF actor_tenant IS NULL OR NEW.tenant_id IS NULL OR actor_tenant <> NEW.tenant_id THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Audit actor must belong to the recorded tenant';
    END IF;
  END IF;
END//

-- Global roles (tenant_id NULL) remain usable, but tenant-specific roles may
-- only be assigned to users in that same tenant.
CREATE TRIGGER trg_user_roles_scope_insert
BEFORE INSERT ON user_roles
FOR EACH ROW
BEGIN
  DECLARE user_tenant BIGINT UNSIGNED DEFAULT NULL;
  DECLARE role_tenant BIGINT UNSIGNED DEFAULT NULL;
  SELECT tenant_id INTO user_tenant FROM users WHERE id = NEW.user_id LIMIT 1;
  SELECT tenant_id INTO role_tenant FROM roles WHERE id = NEW.role_id LIMIT 1;
  IF user_tenant IS NULL OR user_tenant <> NEW.tenant_id OR
     (role_tenant IS NOT NULL AND role_tenant <> NEW.tenant_id) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'User role assignment crosses tenant boundary';
  END IF;
END//

CREATE TRIGGER trg_user_roles_scope_update
BEFORE UPDATE ON user_roles
FOR EACH ROW
BEGIN
  DECLARE user_tenant BIGINT UNSIGNED DEFAULT NULL;
  DECLARE role_tenant BIGINT UNSIGNED DEFAULT NULL;
  SELECT tenant_id INTO user_tenant FROM users WHERE id = NEW.user_id LIMIT 1;
  SELECT tenant_id INTO role_tenant FROM roles WHERE id = NEW.role_id LIMIT 1;
  IF user_tenant IS NULL OR user_tenant <> NEW.tenant_id OR
     (role_tenant IS NOT NULL AND role_tenant <> NEW.tenant_id) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'User role assignment crosses tenant boundary';
  END IF;
END//
CREATE TRIGGER trg_roles_scope_update
BEFORE UPDATE ON roles
FOR EACH ROW
BEGIN
  IF NEW.tenant_id IS NOT NULL AND EXISTS (
    SELECT 1
    FROM user_roles ur
    JOIN users u ON u.id = ur.user_id
    WHERE ur.role_id = OLD.id AND u.tenant_id <> NEW.tenant_id
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Role update would cross tenant boundary';
  END IF;
END//
DELIMITER ;


-- Existing role links are validated during migration; this query should
-- return zero rows before the migration is considered successful.
-- SELECT ur.user_id, ur.role_id, ur.tenant_id, r.tenant_id AS role_tenant
-- FROM user_roles ur JOIN roles r ON r.id = ur.role_id
-- WHERE r.tenant_id IS NOT NULL AND r.tenant_id <> ur.tenant_id;
