-- Prevent global platform roles from being granted tenant-scoped permissions.
-- Tenant permissions must belong to a role owned by that same tenant.

DELIMITER //

CREATE TRIGGER trg_role_permissions_tenant_scope_insert
BEFORE INSERT ON role_permissions
FOR EACH ROW
BEGIN
  DECLARE role_tenant BIGINT UNSIGNED DEFAULT NULL;
  DECLARE permission_scope VARCHAR(20) DEFAULT NULL;

  SELECT tenant_id INTO role_tenant
    FROM roles WHERE id = NEW.role_id LIMIT 1;
  SELECT scope INTO permission_scope
    FROM permissions WHERE id = NEW.permission_id LIMIT 1;

  IF permission_scope = 'tenant' AND role_tenant IS NULL THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Tenant permissions can only be granted to tenant-owned roles';
  END IF;
END//

CREATE TRIGGER trg_role_permissions_tenant_scope_update
BEFORE UPDATE ON role_permissions
FOR EACH ROW
BEGIN
  DECLARE role_tenant BIGINT UNSIGNED DEFAULT NULL;
  DECLARE permission_scope VARCHAR(20) DEFAULT NULL;

  SELECT tenant_id INTO role_tenant
    FROM roles WHERE id = NEW.role_id LIMIT 1;
  SELECT scope INTO permission_scope
    FROM permissions WHERE id = NEW.permission_id LIMIT 1;

  IF permission_scope = 'tenant' AND role_tenant IS NULL THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Tenant permissions can only be granted to tenant-owned roles';
  END IF;
END//

DELIMITER ;
