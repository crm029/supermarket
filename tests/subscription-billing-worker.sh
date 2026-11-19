#!/usr/bin/env bash
set -euo pipefail

PLAN_ID="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT id FROM platform_plans WHERE code='ci-billing' LIMIT 1")"
test -n "$PLAN_ID"
mysql --host=127.0.0.1 --user=root --password=root supermarket360 <<SQL
INSERT INTO users (tenant_id,full_name,mobile,status,referral_code)
VALUES (1,'CI Store Owner','09129999991','active','CIWORKEROWNER1')
ON DUPLICATE KEY UPDATE status='active';
SET @worker_owner=(SELECT id FROM users WHERE tenant_id=1 AND mobile='09129999991' LIMIT 1);
INSERT INTO roles (tenant_id,role_key,title)
VALUES (1,'store_owner','مالک فروشگاه')
ON DUPLICATE KEY UPDATE title=VALUES(title);
SET @worker_owner_role=(SELECT id FROM roles WHERE tenant_id=1 AND role_key='store_owner' LIMIT 1);
INSERT IGNORE INTO user_roles (tenant_id,user_id,role_id) VALUES (1,@worker_owner,@worker_owner_role);
SQL
mysql --host=127.0.0.1 --user=root --password=root supermarket360 <<SQL
INSERT INTO tenant_subscriptions
  (tenant_id,plan_id,status,billing_period_months,price_monthly_rial,starts_at,current_period_start,current_period_end)
VALUES
  (1,$PLAN_ID,'active',1,120000,UTC_TIMESTAMP(),UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 2 DAY));
SET @worker_subscription=LAST_INSERT_ID();
SQL
OWNER_ROLE_COUNT="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT COUNT(*) FROM users u JOIN user_roles ur ON ur.tenant_id=u.tenant_id AND ur.user_id=u.id JOIN roles r ON r.role_scope_id=ur.role_scope_id AND r.id=ur.role_id WHERE u.tenant_id=1 AND u.mobile='09129999991' AND r.role_key='store_owner'")"
echo "Billing worker owner recipients: $OWNER_ROLE_COUNT"
test "$OWNER_ROLE_COUNT" = "1"
SUB_ID="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT id FROM tenant_subscriptions WHERE tenant_id=1 AND plan_id=$PLAN_ID ORDER BY id DESC LIMIT 1")"

SUBSCRIPTION_BILLING_BATCH_SIZE=100 SUBSCRIPTION_BILLING_LEAD_DAYS=7 SUBSCRIPTION_GRACE_DAYS=7 SUBSCRIPTION_REMINDER_LEAD_DAYS=3 php bin/process-subscription-billing.php >/tmp/subscription-worker-first.json
php -r '$r=json_decode(file_get_contents("/tmp/subscription-worker-first.json"),true,512,JSON_THROW_ON_ERROR); if(($r["status"]??"")!=="ok"||($r["renewal_invoices_created"]??-1)!==1||($r["subscriptions_marked_past_due"]??-1)!==0){fwrite(STDERR,"Unexpected first subscription worker report\n");exit(1);}'
SUBSCRIPTION_BILLING_BATCH_SIZE=100 SUBSCRIPTION_BILLING_LEAD_DAYS=7 SUBSCRIPTION_GRACE_DAYS=7 php bin/process-subscription-billing.php >/tmp/subscription-worker-second.json
php -r '$r=json_decode(file_get_contents("/tmp/subscription-worker-second.json"),true,512,JSON_THROW_ON_ERROR); if(($r["status"]??"")!=="ok"||($r["renewal_invoices_created"]??-1)!==0){fwrite(STDERR,"Subscription worker duplicated a renewal invoice\n");exit(1);}'
INVOICE_COUNT="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT COUNT(*) FROM platform_invoices WHERE subscription_id=$SUB_ID")"
test "$INVOICE_COUNT" = "1"
RENEWAL_NOTICE_COUNT="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT COUNT(*) FROM notifications WHERE tenant_id=1 AND event_key=CONCAT('platform-renewal-invoice-',(SELECT id FROM platform_invoices WHERE subscription_id=$SUB_ID ORDER BY id DESC LIMIT 1))")"
echo "Renewal notifications: $RENEWAL_NOTICE_COUNT"
test "$RENEWAL_NOTICE_COUNT" -ge 1
UPCOMING_NOTICE_COUNT="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT COUNT(*) FROM notifications WHERE tenant_id=1 AND event_key=CONCAT('platform-invoice-due-',(SELECT id FROM platform_invoices WHERE subscription_id=$SUB_ID ORDER BY id DESC LIMIT 1))")"
test "$UPCOMING_NOTICE_COUNT" -ge 1


mysql --host=127.0.0.1 --user=root --password=root supermarket360 -e "INSERT INTO platform_invoices (tenant_id,subscription_id,invoice_number,period_start,period_end,amount_rial,status,due_at,created_by) VALUES (1,$SUB_ID,CONCAT('CI-OVERDUE-',$SUB_ID),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 DAY),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY),10000,'issued',DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY),NULL)"
OVERDUE_INVOICE_ID="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT id FROM platform_invoices WHERE subscription_id=$SUB_ID AND invoice_number=CONCAT('CI-OVERDUE-',$SUB_ID)")"
SUBSCRIPTION_BILLING_BATCH_SIZE=100 SUBSCRIPTION_BILLING_LEAD_DAYS=7 SUBSCRIPTION_GRACE_DAYS=7 SUBSCRIPTION_REMINDER_LEAD_DAYS=3 php bin/process-subscription-billing.php >/tmp/subscription-worker-reminder-overdue.json
OVERDUE_INVOICE_NOTICE_COUNT="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT COUNT(*) FROM notifications WHERE tenant_id=1 AND event_key=CONCAT('platform-invoice-overdue-',$OVERDUE_INVOICE_ID,'-',DATE_FORMAT(UTC_DATE(),'%x-%v'))")"
test "$OVERDUE_INVOICE_NOTICE_COUNT" -ge 1
SUBSCRIPTION_BILLING_BATCH_SIZE=100 SUBSCRIPTION_BILLING_LEAD_DAYS=7 SUBSCRIPTION_GRACE_DAYS=7 SUBSCRIPTION_REMINDER_LEAD_DAYS=3 php bin/process-subscription-billing.php >/tmp/subscription-worker-reminder-repeat.json
OVERDUE_INVOICE_NOTICE_COUNT="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT COUNT(*) FROM notifications WHERE tenant_id=1 AND event_key=CONCAT('platform-invoice-overdue-',$OVERDUE_INVOICE_ID,'-',DATE_FORMAT(UTC_DATE(),'%x-%v'))")"
test "$OVERDUE_INVOICE_NOTICE_COUNT" = "1"

mysql --host=127.0.0.1 --user=root --password=root supermarket360 -e "UPDATE tenant_subscriptions SET current_period_end=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 8 DAY) WHERE id=$SUB_ID AND tenant_id=1"
SUBSCRIPTION_BILLING_BATCH_SIZE=100 SUBSCRIPTION_BILLING_LEAD_DAYS=7 SUBSCRIPTION_GRACE_DAYS=7 php bin/process-subscription-billing.php >/tmp/subscription-worker-overdue.json
php -r '$r=json_decode(file_get_contents("/tmp/subscription-worker-overdue.json"),true,512,JSON_THROW_ON_ERROR); if(($r["status"]??"")!=="ok"||($r["subscriptions_marked_past_due"]??-1)!==1){fwrite(STDERR,"Subscription grace-period transition failed\n");exit(1);}'
STATUS="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT status FROM tenant_subscriptions WHERE id=$SUB_ID AND tenant_id=1")"
test "$STATUS" = "past_due"
PAST_DUE_NOTICE_COUNT="$(mysql --host=127.0.0.1 --user=root --password=root --skip-column-names supermarket360 -e "SELECT COUNT(*) FROM notifications WHERE tenant_id=1 AND event_key=CONCAT('platform-subscription-past-due-',$SUB_ID)")"
echo "Past-due notifications: $PAST_DUE_NOTICE_COUNT"
test "$PAST_DUE_NOTICE_COUNT" -ge 1

mysql --host=127.0.0.1 --user=root --password=root supermarket360 -e "DELETE FROM notifications WHERE tenant_id=1 AND event_key IN (CONCAT('platform-renewal-invoice-',(SELECT id FROM platform_invoices WHERE subscription_id=$SUB_ID ORDER BY id DESC LIMIT 1)),CONCAT('platform-invoice-due-',(SELECT id FROM platform_invoices WHERE subscription_id=$SUB_ID ORDER BY id DESC LIMIT 1)),CONCAT('platform-invoice-overdue-',$OVERDUE_INVOICE_ID,'-',DATE_FORMAT(UTC_DATE(),'%x-%v')),CONCAT('platform-subscription-past-due-',$SUB_ID)); DELETE FROM platform_invoices WHERE subscription_id=$SUB_ID; DELETE FROM tenant_subscriptions WHERE id=$SUB_ID AND tenant_id=1; DELETE FROM user_roles WHERE tenant_id=1 AND user_id=(SELECT id FROM users WHERE tenant_id=1 AND mobile='09129999991'); DELETE FROM users WHERE tenant_id=1 AND mobile='09129999991'"
echo "Subscription renewal and grace-period worker checks passed"
