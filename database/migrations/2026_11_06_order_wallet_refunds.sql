-- Full-order wallet-credit refunds with tenant-safe ledger references.
ALTER TABLE wallet_accounts
  ADD UNIQUE KEY uq_wallet_tenant_id_id (tenant_id,id);

ALTER TABLE wallet_entries
  ADD CONSTRAINT fk_wallet_entries_same_tenant
  FOREIGN KEY (tenant_id,wallet_id) REFERENCES wallet_accounts(tenant_id,id);

ALTER TABLE referral_events
  MODIFY status ENUM('pending','qualified','rewarded','rejected','reversed') NOT NULL DEFAULT 'pending';

INSERT INTO permissions (permission_key,scope,description)
VALUES ('orders.refund','tenant','ثبت بازپرداخت کامل سفارش به کیف پول مشتری')
ON DUPLICATE KEY UPDATE description=VALUES(description);

INSERT IGNORE INTO role_permissions (role_scope_id,role_id,permission_id)
SELECT r.role_scope_id,r.id,p.id
FROM roles r
JOIN permissions p ON p.permission_key='orders.refund' AND p.scope='tenant'
WHERE r.role_key='store_owner' AND r.tenant_id IS NOT NULL;

CREATE TABLE order_refunds (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NOT NULL,
  customer_user_id BIGINT UNSIGNED NOT NULL,
  refunded_by_user_id BIGINT UNSIGNED NOT NULL,
  wallet_id BIGINT UNSIGNED NOT NULL,
  refund_method ENUM('wallet_credit') NOT NULL DEFAULT 'wallet_credit',
  amount_rial BIGINT UNSIGNED NOT NULL,
  reason VARCHAR(500) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_order_refund_once (tenant_id,order_id),
  KEY ix_order_refunds_customer (tenant_id,customer_user_id,created_at),
  CONSTRAINT fk_order_refunds_order
    FOREIGN KEY (tenant_id,order_id) REFERENCES orders(tenant_id,id),
  CONSTRAINT fk_order_refunds_customer
    FOREIGN KEY (tenant_id,customer_user_id) REFERENCES users(tenant_id,id),
  CONSTRAINT fk_order_refunds_actor
    FOREIGN KEY (tenant_id,refunded_by_user_id) REFERENCES users(tenant_id,id),
  CONSTRAINT fk_order_refunds_wallet
    FOREIGN KEY (tenant_id,wallet_id) REFERENCES wallet_accounts(tenant_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
