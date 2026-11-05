-- Enforce tenant-local barcode uniqueness and add configurable low-stock thresholds.
UPDATE products SET barcode = NULL WHERE barcode IS NOT NULL AND TRIM(barcode) = '';

DELIMITER //
CREATE PROCEDURE assert_existing_barcodes_unique()
BEGIN
  IF EXISTS (
    SELECT tenant_id, barcode
    FROM products
    WHERE barcode IS NOT NULL
    GROUP BY tenant_id, barcode
    HAVING COUNT(*) > 1
  ) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Duplicate product barcodes exist; resolve them before applying this migration';
  END IF;
END//
DELIMITER ;

CALL assert_existing_barcodes_unique();
DROP PROCEDURE assert_existing_barcodes_unique;

ALTER TABLE products
  ADD COLUMN low_stock_threshold BIGINT UNSIGNED NOT NULL DEFAULT 5 AFTER is_active,
  ADD UNIQUE KEY uq_product_barcode (tenant_id, barcode);
