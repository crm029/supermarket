-- Verify the dedicated pickup-handoff permission and assignment type.
DELIMITER //
CREATE PROCEDURE assert_pickup_handoff_schema()
BEGIN
  DECLARE permission_count INT DEFAULT 0;
  DECLARE assignment_enum TEXT DEFAULT '';
  SELECT COUNT(*) INTO permission_count
    FROM permissions
    WHERE permission_key='orders.pickup_handoff' AND scope='tenant';
  SELECT COLUMN_TYPE INTO assignment_enum
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE()
      AND TABLE_NAME='order_assignments'
      AND COLUMN_NAME='assignment_type'
    LIMIT 1;
  IF permission_count <> 1 OR assignment_enum NOT LIKE '%pickup%' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT='Pickup handoff permission or assignment type is missing';
  END IF;
END//
DELIMITER ;

CALL assert_pickup_handoff_schema();
DROP PROCEDURE assert_pickup_handoff_schema;
