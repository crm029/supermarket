DELIMITER //

CREATE PROCEDURE assert_stock_batch_reservation_integrity()
BEGIN
  DECLARE missing_allocations INT DEFAULT 0;
  DECLARE mismatched_allocations INT DEFAULT 0;
  DECLARE batch_reservation_mismatches INT DEFAULT 0;

  SELECT COUNT(*) INTO missing_allocations
  FROM stock_reservations sr
  LEFT JOIN (
    SELECT tenant_id,reservation_id,SUM(quantity) AS allocated
    FROM stock_reservation_batches
    WHERE status='active'
    GROUP BY tenant_id,reservation_id
  ) a ON a.tenant_id=sr.tenant_id AND a.reservation_id=sr.id
  WHERE sr.status='active' AND COALESCE(a.allocated,0)<>sr.quantity;

  SELECT COUNT(*) INTO mismatched_allocations
  FROM stock_reservation_batches a
  JOIN stock_reservations sr ON sr.tenant_id=a.tenant_id AND sr.id=a.reservation_id
  JOIN stock_batches b ON b.tenant_id=a.tenant_id AND b.id=a.batch_id
  WHERE sr.tenant_id<>b.tenant_id
     OR sr.warehouse_id<>b.warehouse_id
     OR sr.product_id<>b.product_id;

  SELECT COUNT(*) INTO batch_reservation_mismatches
  FROM stock_batches b
  LEFT JOIN (
    SELECT tenant_id,batch_id,SUM(quantity) AS allocated
    FROM stock_reservation_batches
    WHERE status='active'
    GROUP BY tenant_id,batch_id
  ) a ON a.tenant_id=b.tenant_id AND a.batch_id=b.id
  WHERE b.reserved<>COALESCE(a.allocated,0);

  IF missing_allocations>0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='An active stock reservation is not fully allocated to batches';
  END IF;
  IF mismatched_allocations>0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='A stock reservation allocation crosses warehouse or product boundaries';
  END IF;
  IF batch_reservation_mismatches>0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Batch reserved quantities do not match active reservation allocations';
  END IF;
END//

CALL assert_stock_batch_reservation_integrity()//
DROP PROCEDURE assert_stock_batch_reservation_integrity//

DELIMITER ;
