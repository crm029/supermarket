-- Cashier assignment schema and permission checks.
DELIMITER //
CREATE PROCEDURE assert_order_cashier_assignment()
BEGIN
  DECLARE cashier_enum_count INT DEFAULT 0;
  DECLARE cashier_permission_count INT DEFAULT 0;

  SELECT COUNT(*) INTO cashier_enum_count
  FROM information_schema.columns
  WHERE table_schema=DATABASE() AND table_name='order_assignments'
    AND column_name='assignment_type' AND column_type LIKE '%cashier%';
  IF cashier_enum_count <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Order assignments must support cashier tasks';
  END IF;

  SELECT COUNT(*) INTO cashier_permission_count
  FROM permissions
  WHERE scope='tenant' AND permission_key='orders.cashier';
  IF cashier_permission_count <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Tenant cashier permission is missing';
  END IF;
END//
DELIMITER ;

CALL assert_order_cashier_assignment();
DROP PROCEDURE assert_order_cashier_assignment;
SELECT 'order cashier assignment checks passed' AS result;
