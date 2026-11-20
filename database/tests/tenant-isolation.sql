-- MySQL integration checks for tenant boundary enforcement.
-- Run only against a disposable database after schema.sql, demo-seed.sql,
-- and migrations/2026_11_05_tenant_isolation.sql.

INSERT INTO tenants (id,name,slug,status,timezone,currency)
VALUES (2,'فروشگاه آزمایشی دوم','tenant-two-test','active','Asia/Tehran','IRR');

INSERT INTO categories (id,tenant_id,title,slug,is_active)
VALUES (20,2,'دسته آزمایشی مستأجر دوم','tenant-two-test-category',TRUE);

INSERT INTO warehouses (tenant_id,title,warehouse_type,is_active)
VALUES (2,'انبار آزمایشی مستأجر دوم','main',TRUE);
SET @tenant_two_warehouse = LAST_INSERT_ID();
SET @tenant_one_product = (SELECT id FROM products WHERE tenant_id=1 ORDER BY id LIMIT 1);


INSERT INTO users (tenant_id,full_name,mobile,referral_code,status)
VALUES (1,'کاربر مستأجر اول','09120000001','TENANTONE01','active');
SET @tenant_one_user = LAST_INSERT_ID();

INSERT INTO users (tenant_id,full_name,mobile,referral_code,status)
VALUES (2,'کاربر مستأجر دوم','09120000002','TENANTTWO01','active');
SET @tenant_two_user = LAST_INSERT_ID();

INSERT INTO roles (tenant_id,role_key,title)
VALUES (2,'tenant_manager','مدیر فروشگاه دوم');
SET @tenant_two_role = LAST_INSERT_ID();
INSERT INTO user_roles (tenant_id,user_id,role_id)
VALUES (2,@tenant_two_user,@tenant_two_role);
INSERT INTO roles (tenant_id,role_key,title)
VALUES (NULL,'platform_support','پشتیبانی سراسری');
SET @global_role = LAST_INSERT_ID();
INSERT INTO user_roles (tenant_id,user_id,role_id)
VALUES (1,@tenant_one_user,@global_role);

DELIMITER //
CREATE PROCEDURE assert_cross_tenant_product_rejected()
BEGIN
  DECLARE rejected BOOLEAN DEFAULT FALSE;
  DECLARE CONTINUE HANDLER FOR SQLSTATE '23000' SET rejected = TRUE;
  INSERT INTO products
    (tenant_id,category_id,sku,title,unit_label,price_rial,is_active)
  VALUES
    (1,20,'CROSS-TENANT-TEST','نباید ثبت شود','عدد',1000,TRUE);
  IF rejected = FALSE THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cross-tenant product category reference was accepted';
  END IF;
END//

CREATE PROCEDURE assert_cross_tenant_stock_warehouse_rejected()
BEGIN
  DECLARE rejected BOOLEAN DEFAULT FALSE;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLSTATE '23000' SET rejected = TRUE;
    INSERT INTO stock_levels (tenant_id,warehouse_id,product_id,on_hand,reserved)
    VALUES (1,@tenant_two_warehouse,@tenant_one_product,1,0);
  END;
  IF rejected = FALSE THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cross-tenant stock warehouse reference was accepted';
  END IF;
END//

CREATE PROCEDURE assert_cross_tenant_role_rejected()
BEGIN
  DECLARE rejected BOOLEAN DEFAULT FALSE;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLSTATE '45000' SET rejected = TRUE;
    INSERT INTO user_roles (tenant_id,user_id,role_id)
    VALUES (1,@tenant_one_user,@tenant_two_role);
  END;
  IF rejected = FALSE THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cross-tenant role assignment was accepted';
  END IF;
END//


CREATE PROCEDURE assert_role_scope_change_rejected()
BEGIN
  DECLARE rejected BOOLEAN DEFAULT FALSE;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLSTATE '23000' SET rejected = TRUE;
    UPDATE roles SET tenant_id = 1 WHERE id = @tenant_two_role;
  END;
  IF rejected = FALSE THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Role tenant change invalidated an existing assignment';
  END IF;
END//

CREATE PROCEDURE assert_cross_tenant_audit_actor_rejected()
BEGIN
  DECLARE rejected BOOLEAN DEFAULT FALSE;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLSTATE '23000' SET rejected = TRUE;
    DECLARE CONTINUE HANDLER FOR SQLSTATE '45000' SET rejected = TRUE;
    INSERT INTO audit_logs
      (tenant_id,actor_user_id,action,entity_type,entity_id)
    VALUES
      (1,@tenant_two_user,'test.cross_tenant','test',1);
  END;
  IF rejected = FALSE THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cross-tenant audit actor was accepted';
  END IF;
END//
DELIMITER ;

CALL assert_cross_tenant_product_rejected();
CALL assert_cross_tenant_stock_warehouse_rejected();
CALL assert_cross_tenant_role_rejected();
CALL assert_role_scope_change_rejected();
CALL assert_cross_tenant_audit_actor_rejected();

DROP PROCEDURE assert_cross_tenant_product_rejected;
DROP PROCEDURE assert_cross_tenant_stock_warehouse_rejected;
DROP PROCEDURE assert_cross_tenant_role_rejected;
DROP PROCEDURE assert_role_scope_change_rejected;
DROP PROCEDURE assert_cross_tenant_audit_actor_rejected;

SELECT 'tenant isolation integration checks passed' AS result;

-- Media ownership must be enforced by composite tenant foreign keys, not only API validation.
INSERT INTO media_assets (tenant_id,media_type,storage_key,alt_text,width,height)
VALUES (1,'other','tenant-1/other/ffffffffffffffffffffffffffffffffffffffff.jpg','tenant-one-media',64,64);
SET @tenant_one_media = LAST_INSERT_ID();

DELIMITER //
CREATE PROCEDURE assert_cross_tenant_category_media_rejected()
BEGIN
  DECLARE rejected BOOLEAN DEFAULT FALSE;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLSTATE '23000' SET rejected = TRUE;
    UPDATE categories SET image_media_id=@tenant_one_media WHERE tenant_id=2 AND id=20;
  END;
  IF rejected = FALSE THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cross-tenant category media reference was accepted';
  END IF;
END//

CREATE PROCEDURE assert_cross_tenant_product_media_rejected()
BEGIN
  DECLARE rejected BOOLEAN DEFAULT FALSE;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLSTATE '23000' SET rejected = TRUE;
    INSERT INTO products
      (tenant_id,category_id,sku,title,unit_label,price_rial,image_media_id,is_active)
    VALUES
      (2,20,'CROSS-TENANT-MEDIA','نباید ثبت شود','عدد',1000,@tenant_one_media,TRUE);
  END;
  IF rejected = FALSE THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cross-tenant product media reference was accepted';
  END IF;
END//

CREATE PROCEDURE assert_cross_tenant_scene_media_rejected()
BEGIN
  DECLARE rejected BOOLEAN DEFAULT FALSE;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLSTATE '23000' SET rejected = TRUE;
    INSERT INTO store_scenes (tenant_id,title,scene_type,media_id,is_published)
    VALUES (2,'صحنه نامعتبر','aisle_photo',@tenant_one_media,FALSE);
  END;
  IF rejected = FALSE THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cross-tenant scene media reference was accepted';
  END IF;
END//

CREATE PROCEDURE assert_cross_tenant_scene_fallback_media_rejected()
BEGIN
  DECLARE rejected BOOLEAN DEFAULT FALSE;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLSTATE '23000' SET rejected = TRUE;
    INSERT INTO store_scenes (tenant_id,title,scene_type,fallback_media_id,is_published)
    VALUES (2,'صحنه جایگزین نامعتبر','aisle_photo',@tenant_one_media,FALSE);
  END;
  IF rejected = FALSE THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cross-tenant scene fallback media reference was accepted';
  END IF;
END//
DELIMITER ;

CALL assert_cross_tenant_category_media_rejected();
CALL assert_cross_tenant_product_media_rejected();
CALL assert_cross_tenant_scene_media_rejected();
CALL assert_cross_tenant_scene_fallback_media_rejected();

DROP PROCEDURE assert_cross_tenant_category_media_rejected;
DROP PROCEDURE assert_cross_tenant_product_media_rejected;
DROP PROCEDURE assert_cross_tenant_scene_media_rejected;
DROP PROCEDURE assert_cross_tenant_scene_fallback_media_rejected;

SELECT 'cross-tenant media ownership checks passed' AS result;
