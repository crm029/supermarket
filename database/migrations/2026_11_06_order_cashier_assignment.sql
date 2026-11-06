-- Add a cashier task type for customer pickup handover.
ALTER TABLE order_assignments
  MODIFY assignment_type ENUM('picker','courier','cashier') NOT NULL;

INSERT INTO permissions (permission_key,scope,description)
VALUES ('orders.cashier','tenant','تحویل حضوری سفارش‌های آماده‌شده به مشتری')
ON DUPLICATE KEY UPDATE description=VALUES(description);
