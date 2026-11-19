#!/usr/bin/env bash
set -euo pipefail

PLAN_ID="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT id FROM platform_plans WHERE code='ci-billing' LIMIT 1")"
test -n "$PLAN_ID"
mysql --host=127.0.0.1 --user=root --password=root supermarket360 <<SQL
INSERT INTO tenant_subscriptions
  (tenant_id,plan_id,status,billing_period_months,price_monthly_rial,starts_at,current_period_start,current_period_end)
VALUES
  (1,$PLAN_ID,'active',1,120000,UTC_TIMESTAMP(),UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 2 DAY));
SET @worker_subscription=LAST_INSERT_ID();
SQL
SUB_ID="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT id FROM tenant_subscriptions WHERE tenant_id=1 AND plan_id=$PLAN_ID ORDER BY id DESC LIMIT 1")"

SUBSCRIPTION_BILLING_BATCH_SIZE=100 SUBSCRIPTION_BILLING_LEAD_DAYS=7 SUBSCRIPTION_GRACE_DAYS=7 php bin/process-subscription-billing.php >/tmp/subscription-worker-first.json
php -r '$r=json_decode(file_get_contents("/tmp/subscription-worker-first.json"),true,512,JSON_THROW_ON_ERROR); if(($r["status"]??"")!=="ok"||($r["renewal_invoices_created"]??-1)!==1||($r["subscriptions_marked_past_due"]??-1)!==0){fwrite(STDERR,"Unexpected first subscription worker report\n");exit(1);}'
SUBSCRIPTION_BILLING_BATCH_SIZE=100 SUBSCRIPTION_BILLING_LEAD_DAYS=7 SUBSCRIPTION_GRACE_DAYS=7 php bin/process-subscription-billing.php >/tmp/subscription-worker-second.json
php -r '$r=json_decode(file_get_contents("/tmp/subscription-worker-second.json"),true,512,JSON_THROW_ON_ERROR); if(($r["status"]??"")!=="ok"||($r["renewal_invoices_created"]??-1)!==0){fwrite(STDERR,"Subscription worker duplicated a renewal invoice\n");exit(1);}'
INVOICE_COUNT="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT COUNT(*) FROM platform_invoices WHERE subscription_id=$SUB_ID")"
test "$INVOICE_COUNT" = "1"
RENEWAL_NOTICE_COUNT="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT COUNT(*) FROM notifications WHERE tenant_id=1 AND event_key LIKE 'platform-renewal-invoice-%'")"
test "$RENEWAL_NOTICE_COUNT" = "1"

mysql --host=127.0.0.1 --user=root --password=root supermarket360 -e "UPDATE tenant_subscriptions SET current_period_end=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 8 DAY) WHERE id=$SUB_ID AND tenant_id=1"
SUBSCRIPTION_BILLING_BATCH_SIZE=100 SUBSCRIPTION_BILLING_LEAD_DAYS=7 SUBSCRIPTION_GRACE_DAYS=7 php bin/process-subscription-billing.php >/tmp/subscription-worker-overdue.json
php -r '$r=json_decode(file_get_contents("/tmp/subscription-worker-overdue.json"),true,512,JSON_THROW_ON_ERROR); if(($r["status"]??"")!=="ok"||($r["subscriptions_marked_past_due"]??-1)!==1){fwrite(STDERR,"Subscription grace-period transition failed\n");exit(1);}'
STATUS="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT status FROM tenant_subscriptions WHERE id=$SUB_ID AND tenant_id=1")"
test "$STATUS" = "past_due"
PAST_DUE_NOTICE_COUNT="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT COUNT(*) FROM notifications WHERE tenant_id=1 AND event_key LIKE 'platform-subscription-past-due-%'")"
test "$PAST_DUE_NOTICE_COUNT" = "1"

mysql --host=127.0.0.1 --user=root --password=root supermarket360 -e "DELETE FROM notifications WHERE tenant_id=1 AND (event_key LIKE 'platform-renewal-invoice-%' OR event_key LIKE 'platform-subscription-past-due-%'); DELETE FROM platform_invoices WHERE subscription_id=$SUB_ID; DELETE FROM tenant_subscriptions WHERE id=$SUB_ID AND tenant_id=1"
echo "Subscription renewal and grace-period worker checks passed"
