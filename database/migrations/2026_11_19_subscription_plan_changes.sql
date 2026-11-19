-- Store-owner requested plan changes are reviewed by platform finance.
-- Approved changes create a renewal-period invoice; the new plan is applied only after full receipt.
CREATE TABLE platform_subscription_change_requests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  subscription_id BIGINT UNSIGNED NOT NULL,
  requested_plan_id BIGINT UNSIGNED NOT NULL,
  requested_billing_period_months TINYINT UNSIGNED NOT NULL,
  approved_price_monthly_rial BIGINT UNSIGNED NULL,
  status ENUM('pending','invoiced','applied','rejected') NOT NULL DEFAULT 'pending',
  requested_by BIGINT UNSIGNED NOT NULL,
  decided_by BIGINT UNSIGNED NULL,
  decided_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_subscription_change_scope (tenant_id,id),
  KEY ix_subscription_change_queue (status,created_at,id),
  KEY ix_subscription_change_tenant (tenant_id,subscription_id,status),
  CONSTRAINT fk_subscription_change_subscription FOREIGN KEY (tenant_id,subscription_id)
    REFERENCES tenant_subscriptions(tenant_id,id),
  CONSTRAINT fk_subscription_change_plan FOREIGN KEY (requested_plan_id)
    REFERENCES platform_plans(id),
  CONSTRAINT fk_subscription_change_requester FOREIGN KEY (tenant_id,requested_by)
    REFERENCES users(tenant_id,id),
  CONSTRAINT fk_subscription_change_decider FOREIGN KEY (decided_by)
    REFERENCES users(id),
  CONSTRAINT chk_subscription_change_period CHECK (requested_billing_period_months IN (1,3,12)),
  CONSTRAINT chk_subscription_change_price CHECK (approved_price_monthly_rial IS NULL OR approved_price_monthly_rial>0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE platform_invoices
  ADD COLUMN subscription_change_request_id BIGINT UNSIGNED NULL AFTER subscription_id,
  ADD UNIQUE KEY uq_invoice_subscription_change (subscription_change_request_id),
  ADD CONSTRAINT fk_invoice_subscription_change FOREIGN KEY (subscription_change_request_id)
    REFERENCES platform_subscription_change_requests(id);
