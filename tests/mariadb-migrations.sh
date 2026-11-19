#!/usr/bin/env bash
set -euo pipefail
DB=(mysql --ssl=0 --host=127.0.0.1 --user=root --password=root supermarket360)
"${DB[@]}" < database/schema.sql
"${DB[@]}" < database/demo-seed.sql

migrations=(
  2026_11_05_tenant_isolation.sql
  2026_11_05_auth_sessions_otp.sql
  2026_11_05_permissions.sql
  2026_11_06_platform_payment_settings.sql
  2026_11_20_platform_sms_settings.sql
  2026_11_19_platform_subscriptions.sql
  2026_11_19_subscription_plan_changes.sql
  2026_11_06_order_assignments.sql
  2026_11_06_pickup_handoff.sql
  2026_11_06_notifications.sql
  2026_11_19_notification_retention_index.sql
  2026_11_06_order_delivery_tracking.sql
  2026_11_06_order_wallet_refunds.sql
  2026_11_06_domain_verification_challenge.sql
  2026_11_05_customer_carts.sql
  2026_11_05_customer_address_management.sql
  2026_11_05_barcode_stock_thresholds.sql
  2026_11_05_media_cleanup_queue.sql
  2026_11_05_delivery_zones.sql
  2026_11_05_pickup_slots.sql
  2026_11_05_coupons.sql
  2026_11_05_product_variants.sql
  2026_11_05_supplier_purchase_orders.sql
  2026_11_05_supplier_returns.sql
  2026_11_05_stock_batches.sql
  2026_11_06_pos_cash_register.sql
  2026_11_06_pos_refunds.sql
  2026_11_08_pos_return_inspection.sql
  2026_11_08_pos_return_inspection_status.sql
  2026_11_05_expired_stock_writeoff.sql
  2026_11_05_seo_content_fields.sql
  2026_11_05_product_seo_slugs.sql
  2026_11_05_seo_url_redirects.sql
)
for migration in "${migrations[@]}"; do
  echo "Applying MariaDB migration: $migration"
  "${DB[@]}" < "database/migrations/$migration"
done

"${DB[@]}" < database/tests/tenant-isolation.sql
"${DB[@]}" < database/tests/platform-payment-settings.sql
"${DB[@]}" < database/tests/platform-sms-settings.sql
"${DB[@]}" < database/tests/platform-billing.sql
"${DB[@]}" < database/tests/pos-cash-register.sql
echo "MariaDB 10.11 schema and migration compatibility checks passed"
