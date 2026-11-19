-- Run after 2026_11_20_media_orphan_reviews.sql.
DELIMITER //
CREATE PROCEDURE assert_orphan_review_permission()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM permissions
    WHERE permission_key='platform.media.cleanup.manage' AND scope='platform'
  ) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Missing platform media cleanup permission'; END IF;
  IF EXISTS (SELECT 1 FROM roles WHERE tenant_id IS NULL AND role_key='platform_admin')
     AND NOT EXISTS (
       SELECT 1 FROM role_permissions rp
       JOIN roles r ON r.id=rp.role_id AND r.role_scope_id=rp.role_scope_id
       JOIN permissions p ON p.id=rp.permission_id
       WHERE r.tenant_id IS NULL AND r.role_key='platform_admin'
         AND rp.role_scope_id=0 AND p.permission_key='platform.media.cleanup.manage'
     ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Existing platform admin lacks media cleanup permission';
  END IF;
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.tables
    WHERE table_schema=DATABASE() AND table_name='media_orphan_reviews'
  ) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Missing media orphan review table'; END IF;
END//
CALL assert_orphan_review_permission()//
DROP PROCEDURE assert_orphan_review_permission//
DELIMITER ;
