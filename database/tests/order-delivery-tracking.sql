-- Courier delivery timeline schema and tenant-scope checks.
DELIMITER //
CREATE PROCEDURE assert_order_delivery_tracking()
BEGIN
  DECLARE status_has_delivery INT DEFAULT 0;
  DECLARE event_table_count INT DEFAULT 0;
  DECLARE foreign_key_count INT DEFAULT 0;
  DECLARE assignment_index_count INT DEFAULT 0;
  DECLARE event_index_count INT DEFAULT 0;
  DECLARE event_date_index_count INT DEFAULT 0;

  SELECT COUNT(*) INTO status_has_delivery
  FROM information_schema.columns
  WHERE table_schema=DATABASE() AND table_name='orders' AND column_name='status'
    AND column_type LIKE '%out_for_delivery%';
  IF status_has_delivery <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Orders status must include out_for_delivery';
  END IF;

  SELECT COUNT(*) INTO event_table_count
  FROM information_schema.tables
  WHERE table_schema=DATABASE() AND table_name='order_delivery_events';
  IF event_table_count <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Order delivery event table is missing';
  END IF;

  SELECT COUNT(DISTINCT constraint_name) INTO foreign_key_count
  FROM information_schema.key_column_usage
  WHERE table_schema=DATABASE() AND table_name='order_delivery_events'
    AND constraint_name IN (
      'fk_order_delivery_events_order',
      'fk_order_delivery_events_assignment',
      'fk_order_delivery_events_actor'
    );
  IF foreign_key_count <> 3 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Delivery events must be tenant-scoped to order, assignment and actor';
  END IF;

  SELECT COUNT(*) INTO assignment_index_count
  FROM information_schema.statistics
  WHERE table_schema=DATABASE() AND table_name='order_assignments'
    AND index_name='uq_order_assignments_tenant_id';
  IF assignment_index_count <> 2 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Order assignments need a tenant-scoped unique reference';
  END IF;

  SELECT COUNT(*) INTO event_index_count
  FROM information_schema.statistics
  WHERE table_schema=DATABASE() AND table_name='order_delivery_events'
    AND index_name='uq_order_delivery_event_assignment_type';
  IF event_index_count <> 3 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Delivery event idempotency index is missing';
  END IF;

  SELECT COUNT(*) INTO event_date_index_count
  FROM information_schema.statistics
  WHERE table_schema=DATABASE() AND table_name='order_delivery_events'
    AND index_name='ix_order_delivery_events_type_date';
  IF event_date_index_count <> 3 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Delivery event date index is missing';
  END IF;
END//
DELIMITER ;

CALL assert_order_delivery_tracking();
DROP PROCEDURE assert_order_delivery_tracking;
SELECT 'order delivery tracking checks passed' AS result;
