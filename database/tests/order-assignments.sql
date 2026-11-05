-- Order assignment tenant-boundary integration checks.
-- Run after 2026_11_06_order_assignments.sql on a disposable CI database.

INSERT INTO tenants (id,name,slug,status,timezone,currency)
VALUES (2,'فروشگاه دوم برای تست تخصیص','assignment-tenant-two','active','Asia/Tehran','IRR');

INSERT INTO users (tenant_id,full_name,mobile,referral_code,status)
VALUES (1,'کارمند تست تخصیص','09120000991','ASSIGNMENT001','active');
SET @assignment_user_one=LAST_INSERT_ID();

INSERT INTO users (tenant_id,full_name,mobile,referral_code,status)
VALUES (2,'کارمند مستأجر دوم','09120000992','ASSIGNMENT002','active');
SET @assignment_user_two=LAST_INSERT_ID();

INSERT INTO orders (tenant_id,user_id,order_number,status,fulfillment,subtotal_rial,discount_rial,delivery_fee_rial,total_rial)
VALUES (1,@assignment_user_one,'ASSIGNMENT-ORDER-ONE','paid','delivery',10000,0,0,10000);
SET @assignment_order_one=LAST_INSERT_ID();

INSERT INTO orders (tenant_id,user_id,order_number,status,fulfillment,subtotal_rial,discount_rial,delivery_fee_rial,total_rial)
VALUES (2,@assignment_user_two,'ASSIGNMENT-ORDER-TWO','paid','delivery',10000,0,0,10000);
SET @assignment_order_two=LAST_INSERT_ID();

INSERT INTO order_assignments (tenant_id,order_id,assignee_user_id,assignment_type,status,assigned_by_user_id)
VALUES (1,@assignment_order_one,@assignment_user_one,'picker','active',@assignment_user_one);

DELIMITER //
CREATE PROCEDURE assert_cross_tenant_assignee_rejected()
BEGIN
  DECLARE rejected BOOLEAN DEFAULT FALSE;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLSTATE '23000' SET rejected=TRUE;
    INSERT INTO order_assignments (tenant_id,order_id,assignee_user_id,assignment_type,status,assigned_by_user_id)
    VALUES (1,@assignment_order_one,@assignment_user_two,'courier','active',@assignment_user_one);
  END;
  IF rejected=FALSE THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Cross-tenant assignment user was accepted';
  END IF;
END//

CREATE PROCEDURE assert_cross_tenant_order_rejected()
BEGIN
  DECLARE rejected BOOLEAN DEFAULT FALSE;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLSTATE '23000' SET rejected=TRUE;
    INSERT INTO order_assignments (tenant_id,order_id,assignee_user_id,assignment_type,status,assigned_by_user_id)
    VALUES (1,@assignment_order_two,@assignment_user_one,'courier','active',@assignment_user_one);
  END;
  IF rejected=FALSE THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Cross-tenant order assignment was accepted';
  END IF;
END//
DELIMITER ;

CALL assert_cross_tenant_assignee_rejected();
CALL assert_cross_tenant_order_rejected();
DROP PROCEDURE assert_cross_tenant_assignee_rejected;
DROP PROCEDURE assert_cross_tenant_order_rejected;

SELECT 'order assignment tenant isolation checks passed' AS result;
