-- Verify barcode uniqueness is tenant-local and stock thresholds are stored.
INSERT INTO products (tenant_id,sku,barcode,title,price_rial,low_stock_threshold)
VALUES (1,'BARCODE-TEST-A','6260000000001','کالای تست بارکد',10000,3);
SET @barcode_test_one = LAST_INSERT_ID();

INSERT INTO products (tenant_id,sku,barcode,title,price_rial,low_stock_threshold)
VALUES (2,'BARCODE-TEST-B','6260000000001','کالای تست بارکد فروشگاه دوم',10000,7);
SET @barcode_test_two = LAST_INSERT_ID();

DELIMITER //
CREATE PROCEDURE assert_duplicate_barcode_rejected()
BEGIN
  DECLARE rejected BOOLEAN DEFAULT FALSE;
  BEGIN
    DECLARE CONTINUE HANDLER FOR 1062 SET rejected = TRUE;
    INSERT INTO products (tenant_id,sku,barcode,title,price_rial)
    VALUES (1,'BARCODE-TEST-C','6260000000001','بارکد تکراری',10000);
  END;
  IF rejected = FALSE THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Duplicate barcode was accepted in the same tenant';
  END IF;
END//
DELIMITER ;

CALL assert_duplicate_barcode_rejected();
DROP PROCEDURE assert_duplicate_barcode_rejected;

DELIMITER //
CREATE PROCEDURE assert_stock_threshold_saved()
BEGIN
  DECLARE threshold_value BIGINT UNSIGNED DEFAULT 0;
  SELECT low_stock_threshold INTO threshold_value FROM products WHERE id=@barcode_test_one;
  IF threshold_value <> 3 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Product low stock threshold was not saved';
  END IF;
END//
DELIMITER ;

CALL assert_stock_threshold_saved();
DROP PROCEDURE assert_stock_threshold_saved;

DELETE FROM products WHERE id IN (@barcode_test_one,@barcode_test_two);
