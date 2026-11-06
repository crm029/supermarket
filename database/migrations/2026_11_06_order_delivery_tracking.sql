-- Courier delivery status timeline and recipient confirmation.
-- Apply after 2026_11_06_order_assignments.sql and notifications.sql.

ALTER TABLE orders
  MODIFY status ENUM(
    'pending_payment','paid','accepted','picking','packed','ready_pickup',
    'dispatched','out_for_delivery','delivered','cancelled','refunded'
  ) NOT NULL DEFAULT 'pending_payment';

ALTER TABLE order_assignments
  ADD UNIQUE KEY uq_order_assignments_tenant_id (tenant_id,id);

CREATE TABLE order_delivery_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NOT NULL,
  assignment_id BIGINT UNSIGNED NULL,
  actor_user_id BIGINT UNSIGNED NOT NULL,
  event_type ENUM('assigned','out_for_delivery','delivered') NOT NULL,
  note VARCHAR(500) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_order_delivery_event_assignment_type (tenant_id,assignment_id,event_type),
  KEY ix_order_delivery_events_order (tenant_id,order_id,created_at,id),
  KEY ix_order_delivery_events_type_date (tenant_id,event_type,created_at),
  CONSTRAINT fk_order_delivery_events_order
    FOREIGN KEY (tenant_id,order_id) REFERENCES orders(tenant_id,id),
  CONSTRAINT fk_order_delivery_events_assignment
    FOREIGN KEY (tenant_id,assignment_id) REFERENCES order_assignments(tenant_id,id),
  CONSTRAINT fk_order_delivery_events_actor
    FOREIGN KEY (tenant_id,actor_user_id) REFERENCES users(tenant_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
