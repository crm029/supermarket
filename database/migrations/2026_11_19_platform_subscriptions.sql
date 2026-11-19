-- SaaS plans, tenant subscriptions and append-only invoice receipts.
-- Apply after tenant isolation, permissions and platform payment settings migrations.

CREATE TABLE platform_plans (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NOT NULL,
  name VARCHAR(120) NOT NULL,
  description VARCHAR(500) NOT NULL DEFAULT '',
  monthly_price_rial BIGINT UNSIGNED NOT NULL,
  limits_json JSON NOT NULL,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_platform_plan_code (code),
  KEY ix_platform_plan_active (is_active,sort_order,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tenant_subscriptions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  plan_id BIGINT UNSIGNED NOT NULL,
  status ENUM('pending_payment','active','past_due','cancelled','expired') NOT NULL DEFAULT 'pending_payment',
  billing_period_months TINYINT UNSIGNED NOT NULL DEFAULT 1,
  price_monthly_rial BIGINT UNSIGNED NOT NULL,
  starts_at DATETIME NULL,
  current_period_start DATETIME NULL,
  current_period_end DATETIME NULL,
  cancel_at_period_end BOOLEAN NOT NULL DEFAULT FALSE,
  cancelled_at DATETIME NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tenant_subscription_scope (tenant_id,id),
  KEY ix_subscription_tenant_status (tenant_id,status,id),
  KEY ix_subscription_period_end (status,current_period_end),
  CONSTRAINT fk_subscription_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
  CONSTRAINT fk_subscription_plan FOREIGN KEY (plan_id) REFERENCES platform_plans(id),
  CONSTRAINT fk_subscription_actor FOREIGN KEY (created_by) REFERENCES users(id),
  CONSTRAINT chk_subscription_period CHECK (billing_period_months IN (1,3,12)),
  CONSTRAINT chk_subscription_price CHECK (price_monthly_rial > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE platform_invoices (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  subscription_id BIGINT UNSIGNED NOT NULL,
  invoice_number VARCHAR(40) NOT NULL,
  period_start DATETIME NOT NULL,
  period_end DATETIME NOT NULL,
  amount_rial BIGINT UNSIGNED NOT NULL,
  status ENUM('issued','paid','void') NOT NULL DEFAULT 'issued',
  due_at DATETIME NOT NULL,
  paid_at DATETIME NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_platform_invoice_number (invoice_number),
  UNIQUE KEY uq_platform_invoice_period (subscription_id,period_start),
  UNIQUE KEY uq_platform_invoice_scope (tenant_id,id),
  KEY ix_platform_invoice_status (status,due_at,id),
  KEY ix_platform_invoice_tenant (tenant_id,created_at),
  CONSTRAINT fk_platform_invoice_subscription FOREIGN KEY (tenant_id,subscription_id)
    REFERENCES tenant_subscriptions(tenant_id,id),
  CONSTRAINT fk_platform_invoice_actor FOREIGN KEY (created_by) REFERENCES users(id),
  CONSTRAINT chk_platform_invoice_period CHECK (period_end > period_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE platform_invoice_payments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  invoice_id BIGINT UNSIGNED NOT NULL,
  amount_rial BIGINT UNSIGNED NOT NULL,
  method ENUM('bank_transfer','cash','other') NOT NULL,
  reference VARCHAR(120) NOT NULL DEFAULT '',
  idempotency_key VARCHAR(100) NOT NULL,
  note VARCHAR(500) NOT NULL DEFAULT '',
  received_by BIGINT UNSIGNED NOT NULL,
  received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_platform_payment_idempotency (idempotency_key),
  KEY ix_platform_payment_invoice (tenant_id,invoice_id,received_at),
  CONSTRAINT fk_platform_payment_invoice FOREIGN KEY (tenant_id,invoice_id)
    REFERENCES platform_invoices(tenant_id,id),
  CONSTRAINT fk_platform_payment_actor FOREIGN KEY (received_by) REFERENCES users(id),
  CONSTRAINT chk_platform_payment_amount CHECK (amount_rial > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELIMITER //
CREATE TRIGGER trg_platform_invoice_payment_no_update
BEFORE UPDATE ON platform_invoice_payments
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Platform invoice payment ledger is append-only';
END//

CREATE TRIGGER trg_platform_invoice_payment_no_delete
BEFORE DELETE ON platform_invoice_payments
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Platform invoice payment ledger is append-only';
END//
DELIMITER ;

INSERT INTO permissions (permission_key,scope,description)
VALUES ('platform.billing.manage','platform','مدیریت پلن‌ها، اشتراک‌ها و دریافت‌های مالی پلتفرم')
ON DUPLICATE KEY UPDATE description=VALUES(description);

INSERT IGNORE INTO role_permissions (role_scope_id,role_id,permission_id)
SELECT 0,r.id,p.id
FROM roles r JOIN permissions p ON p.permission_key='platform.billing.manage' AND p.scope='platform'
WHERE r.tenant_id IS NULL AND r.role_key='platform_admin';
