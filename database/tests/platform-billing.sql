-- SaaS billing ledger integrity and tenant isolation.
-- Run after the platform subscriptions migration and tenant isolation tests.

INSERT INTO platform_plans (code,name,description,monthly_price_rial,limits_json)
VALUES ('ci-billing','CI Billing Plan','Integration fixture',120000,JSON_OBJECT('products',100,'staff',5));
SET @billing_plan=LAST_INSERT_ID();

INSERT INTO tenant_subscriptions (tenant_id,plan_id,status,billing_period_months,price_monthly_rial)
VALUES (2,@billing_plan,'pending_payment',1,120000);
SET @billing_subscription=LAST_INSERT_ID();

INSERT INTO platform_invoices (tenant_id,subscription_id,invoice_number,period_start,period_end,amount_rial,status,due_at)
VALUES (2,@billing_subscription,'SM360-CI-BILLING-001',UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 MONTH),150000,'issued',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 7 DAY));
SET @billing_invoice=LAST_INSERT_ID();

INSERT INTO platform_invoice_payments (tenant_id,invoice_id,amount_rial,method,reference,idempotency_key,note,received_by)
SELECT 2,@billing_invoice,50000,'bank_transfer','CI-PARTIAL-1','ci-billing-payment-0001','Partial payment',id
FROM users WHERE tenant_id=2 AND mobile='09120000002' LIMIT 1;

INSERT INTO platform_invoice_payments (tenant_id,invoice_id,amount_rial,method,reference,idempotency_key,note,received_by)
SELECT 2,@billing_invoice,70000,'bank_transfer','CI-PARTIAL-2','ci-billing-payment-0002','Final payment',id
FROM users WHERE tenant_id=2 AND mobile='09120000002' LIMIT 1;

DELIMITER //
CREATE PROCEDURE assert_platform_billing_ledger()
BEGIN
  DECLARE paid_total BIGINT DEFAULT 0;
  DECLARE payment_count INT DEFAULT 0;
  SELECT COALESCE(SUM(amount_rial),0),COUNT(*) INTO paid_total,payment_count
  FROM platform_invoice_payments WHERE tenant_id=2 AND invoice_id=@billing_invoice;
  IF paid_total<>120000 OR payment_count<>2 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Partial invoice payment ledger sum or count is incorrect';
  END IF;
END//

CREATE PROCEDURE assert_cross_tenant_billing_payment_rejected()
BEGIN
  DECLARE rejected BOOLEAN DEFAULT FALSE;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLSTATE '23000' SET rejected=TRUE;
    INSERT INTO platform_invoice_payments (tenant_id,invoice_id,amount_rial,method,reference,idempotency_key,received_by)
    SELECT 1,@billing_invoice,1,'other','cross-tenant','ci-billing-cross-tenant',id
    FROM users WHERE tenant_id=1 AND mobile='09120000001' LIMIT 1;
  END;
  IF rejected=FALSE THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Cross-tenant invoice payment was accepted';
  END IF;
END//


CREATE PROCEDURE assert_invoice_payment_ledger_is_append_only()
BEGIN
  DECLARE update_rejected BOOLEAN DEFAULT FALSE;
  DECLARE delete_rejected BOOLEAN DEFAULT FALSE;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET update_rejected=TRUE;
    UPDATE platform_invoice_payments SET note='tampered' WHERE id=(SELECT id FROM (SELECT id FROM platform_invoice_payments WHERE invoice_id=@billing_invoice LIMIT 1) AS chosen);
  END;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET delete_rejected=TRUE;
    DELETE FROM platform_invoice_payments WHERE id=(SELECT id FROM (SELECT id FROM platform_invoice_payments WHERE invoice_id=@billing_invoice LIMIT 1) AS chosen);
  END;
  IF update_rejected=FALSE OR delete_rejected=FALSE THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Platform payment ledger allowed an update or delete';
  END IF;
END//


CREATE PROCEDURE assert_invoice_financial_fields_are_immutable()
BEGIN
  DECLARE rejected BOOLEAN DEFAULT FALSE;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET rejected=TRUE;
    UPDATE platform_invoices SET amount_rial=amount_rial+1 WHERE id=@billing_invoice;
  END;
  IF rejected=FALSE THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Platform invoice amount was mutable';
  END IF;
END//
DELIMITER ;

CALL assert_platform_billing_ledger();
CALL assert_invoice_financial_fields_are_immutable();
CALL assert_invoice_payment_ledger_is_append_only();
CALL assert_cross_tenant_billing_payment_rejected();
DROP PROCEDURE assert_platform_billing_ledger;
DROP PROCEDURE assert_cross_tenant_billing_payment_rejected;
DROP PROCEDURE assert_invoice_payment_ledger_is_append_only;
DROP PROCEDURE assert_invoice_financial_fields_are_immutable;

SELECT 'platform billing integration checks passed' AS result;
