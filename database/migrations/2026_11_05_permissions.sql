-- Permission catalog and role-to-permission assignments.
-- Apply after tenant-isolation and authentication migrations.

CREATE TABLE permissions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  permission_key VARCHAR(100) NOT NULL,
  scope ENUM('tenant','platform') NOT NULL DEFAULT 'tenant',
  description VARCHAR(255) NOT NULL DEFAULT '',
  UNIQUE KEY uq_permissions_key (permission_key),
  KEY ix_permissions_scope (scope, permission_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
  role_scope_id BIGINT UNSIGNED NOT NULL,
  role_id BIGINT UNSIGNED NOT NULL,
  permission_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (role_id, permission_id),
  KEY ix_role_permissions_scope (role_scope_id, role_id),
  CONSTRAINT fk_role_permissions_role_scope
    FOREIGN KEY (role_scope_id, role_id) REFERENCES roles(role_scope_id, id),
  CONSTRAINT fk_role_permissions_permission
    FOREIGN KEY (permission_id) REFERENCES permissions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (permission_key, scope, description) VALUES
('catalog.products.read','tenant','مشاهده محصولات فروشگاه'),
('catalog.products.manage','tenant','ایجاد و ویرایش و غیرفعال‌سازی محصولات'),
('catalog.categories.read','tenant','مشاهده دسته‌بندی‌های فروشگاه'),
('catalog.categories.manage','tenant','مدیریت دسته‌بندی‌های فروشگاه'),
('orders.read','tenant','مشاهده سفارش‌های فروشگاه'),
('orders.manage','tenant','مدیریت وضعیت سفارش‌ها'),
('inventory.read','tenant','مشاهده موجودی انبار'),
('inventory.manage','tenant','ثبت و اصلاح گردش موجودی'),
('reports.read','tenant','مشاهده گزارش‌های فروشگاه'),
('store.settings.manage','tenant','مدیریت تنظیمات فروشگاه'),
('staff.manage','tenant','مدیریت کارکنان فروشگاه'),
('media.manage','tenant','مدیریت رسانه‌های فروشگاه'),
('platform.tenants.manage','platform','مدیریت مستأجران پلتفرم'),
('platform.audit.read','platform','مشاهده گزارش حسابرسی پلتفرم');

DELIMITER //
CREATE TRIGGER trg_role_permissions_scope_insert
BEFORE INSERT ON role_permissions
FOR EACH ROW
BEGIN
  DECLARE current_scope BIGINT UNSIGNED DEFAULT NULL;
  SELECT role_scope_id INTO current_scope FROM roles WHERE id = NEW.role_id LIMIT 1;
  SET NEW.role_scope_id = current_scope;
END//

CREATE TRIGGER trg_role_permissions_scope_update
BEFORE UPDATE ON role_permissions
FOR EACH ROW
BEGIN
  DECLARE current_scope BIGINT UNSIGNED DEFAULT NULL;
  SELECT role_scope_id INTO current_scope FROM roles WHERE id = NEW.role_id LIMIT 1;
  SET NEW.role_scope_id = current_scope;
END//
DELIMITER ;
