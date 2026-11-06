-- Staff-controlled proof of handoff for customer pickup orders.
-- Apply after 2026_11_06_order_assignments.sql.

INSERT INTO permissions (permission_key, scope, description) VALUES
('orders.pickup_handoff','tenant','ثبت تحویل حضوری سفارش به مشتری')
ON DUPLICATE KEY UPDATE description=VALUES(description);

ALTER TABLE order_assignments
  MODIFY assignment_type ENUM('picker','courier','pickup') NOT NULL;
