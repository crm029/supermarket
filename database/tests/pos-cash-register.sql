-- POS schema and tenant-scope assertions.
DELIMITER //
CREATE PROCEDURE assert_pos_cash_register_schema()
BEGIN
  DECLARE table_count INT DEFAULT 0;
  DECLARE permission_count INT DEFAULT 0;
  DECLARE shift_unique_count INT DEFAULT 0;
  DECLARE sale_idempotency_count INT DEFAULT 0;
  DECLARE sale_item_fk_count INT DEFAULT 0;
  DECLARE request_hash_column_count INT DEFAULT 0;
  DECLARE tenant_fk_count INT DEFAULT 0;
  DECLARE refund_table_count INT DEFAULT 0;
  DECLARE refund_permission_count INT DEFAULT 0;
  DECLARE refund_idempotency_count INT DEFAULT 0;
  DECLARE refund_tenant_fk_count INT DEFAULT 0;

  SELECT COUNT(*) INTO table_count
  FROM information_schema.tables
  WHERE table_schema=DATABASE()
    AND table_name IN ('pos_shifts','pos_sales','pos_sale_items');
  IF table_count <> 3 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='POS shift, sale and sale-item tables must exist';
  END IF;

  SELECT COUNT(*) INTO permission_count
  FROM permissions
  WHERE scope='tenant'
    AND permission_key IN ('pos.read','pos.shift.open','pos.shift.close','pos.sale.create');
  IF permission_count <> 4 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='All four tenant-scoped POS permissions must exist';
  END IF;

  SELECT COUNT(*) INTO shift_unique_count
  FROM information_schema.statistics
  WHERE table_schema=DATABASE() AND table_name='pos_shifts'
    AND index_name='uq_pos_one_open_shift';
  IF shift_unique_count <> 2 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='A cashier must not have multiple open shifts in one tenant';
  END IF;

  SELECT COUNT(*) INTO sale_idempotency_count
  FROM information_schema.statistics
  WHERE table_schema=DATABASE() AND table_name='pos_sales'
    AND index_name='uq_pos_sale_idempotency';
  IF sale_idempotency_count <> 3 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='POS sale idempotency unique key is missing';
  END IF;

  SELECT COUNT(DISTINCT constraint_name) INTO sale_item_fk_count
  FROM information_schema.key_column_usage
  WHERE table_schema=DATABASE() AND table_name='pos_sale_items'
    AND constraint_name IN ('fk_pos_sale_item_tenant','fk_pos_sale_item_sale_tenant','fk_pos_sale_item_product_tenant');
  IF sale_item_fk_count <> 3 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='POS sale items must be tenant-scoped to sale and product';
  END IF;

  SELECT COUNT(*) INTO request_hash_column_count
  FROM information_schema.columns
  WHERE table_schema=DATABASE() AND table_name='pos_sales'
    AND column_name='request_hash' AND character_maximum_length=64;
  IF request_hash_column_count <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='POS idempotency must bind a request hash';
  END IF;

  SELECT COUNT(DISTINCT constraint_name) INTO tenant_fk_count
  FROM information_schema.key_column_usage
  WHERE table_schema=DATABASE() AND table_name='pos_shifts'
    AND constraint_name IN ('fk_pos_shift_cashier_tenant','fk_pos_shift_warehouse_tenant');
  IF tenant_fk_count <> 2 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='POS shifts must enforce tenant-safe cashier and warehouse foreign keys';
  END IF;

  SELECT COUNT(*) INTO refund_table_count
  FROM information_schema.tables
  WHERE table_schema=DATABASE() AND table_name IN ('pos_refunds','pos_refund_items');
  IF refund_table_count <> 2 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='POS refund ledger tables must exist';
  END IF;

  SELECT COUNT(*) INTO refund_permission_count
  FROM permissions WHERE scope='tenant' AND permission_key='pos.refund.create';
  IF refund_permission_count <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='POS refund permission must be tenant-scoped';
  END IF;

  SELECT COUNT(*) INTO refund_idempotency_count
  FROM information_schema.statistics
  WHERE table_schema=DATABASE() AND table_name='pos_refunds'
    AND index_name='uq_pos_refund_idempotency';
  IF refund_idempotency_count <> 3 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='POS refund idempotency unique key is missing';
  END IF;

  SELECT COUNT(DISTINCT constraint_name) INTO refund_tenant_fk_count
  FROM information_schema.key_column_usage
  WHERE table_schema=DATABASE() AND table_name='pos_refund_items'
    AND constraint_name IN ('fk_pos_refund_item_refund_tenant','fk_pos_refund_item_sale_item_tenant','fk_pos_refund_item_product_tenant');
  IF refund_tenant_fk_count <> 3 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='POS refund items must enforce tenant-safe references';
  END IF;
END//
DELIMITER ;

CALL assert_pos_cash_register_schema();
DROP PROCEDURE assert_pos_cash_register_schema;
SELECT 'POS cash register schema checks passed' AS result;
