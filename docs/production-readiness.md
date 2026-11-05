# Production readiness checklist

The application code includes customer registration, catalogue management, inventory, interactive scenes, order workflow and provider adapters. A live store is not production-ready until every external service and the real tenant data below are configured and tested. Run the read-only deployment preflight with `APP_ENV=production php bin/production-preflight.php` after configuring the server environment. It checks PHP extensions/version, core database tables, a verified active tenant domain, production HTTPS origins, SMS/payment configuration and media storage. A passing result is necessary but does not replace live provider, restore, security or device acceptance.

## Platform administration

- [ ] Apply all migrations, then grant platform access only to a dedicated active OTP-verified account using database/bootstrap-platform-admin.sql.
- [ ] Use platform.php to provision tenants; each new store starts pending with an unverified primary domain and a pending owner account.
- [ ] Publish the generated TXT record at _supermarket360-verification.<domain> and confirm the backend verifies the exact one-time token before activating the tenant. Tokens expire after 24 hours and can be reissued.
- [ ] Keep the platform administrator's home tenant active and protected from suspension; the API blocks self-suspension.

## Server

- [ ] PHP 8.2 or newer with pdo_mysql, mbstring, cURL and fileinfo.
- [ ] MySQL 8.0.16 or newer; run schema.sql and every migration in documented order.
- [ ] Configure Apache mod_rewrite and permit the supplied API .htaccess rules.
- [ ] Set APP_ENV=production, APP_DEBUG=false, APP_KEY to a unique random secret of at least 32 characters, and APP_BASE_URL to the exact HTTPS public origin.
- [ ] Use a dedicated least-privilege database account; do not use MySQL root in production.
- [ ] Configure a valid tenant domain/host mapping and run database/bootstrap-store-owner.sql after editing its owner and tenant values.

## SMS and payment

- [ ] Set SMS_PROVIDER=kavenegar and configure the real KAVENEGAR_API_KEY and approved KAVENEGAR_VERIFY_TEMPLATE.
- [ ] Confirm delivery, resend, expiry and rate-limit behaviour using a real phone before launch.
- [ ] Configure ZARINPAL_MERCHANT_ID and ZARINPAL_SANDBOX=false.
- [ ] Register the exact HTTPS callback domain with the payment provider.
- [ ] Complete sandbox and low-value live tests for success, cancellation, duplicate callbacks, delayed callbacks and provider outages.
- [ ] Configure settlement reconciliation and a manual refund procedure. Refund API integration is not included.

## Refunds and wallet ledger

- [ ] Grant orders.refund only to trusted managers; the current refund flow credits the full verified order amount to the customer's wallet.
- [ ] Reconcile the credit with the original payment and communicate that this is store credit, not a reversal to the bank card or gateway.
- [ ] Inspect and receive returned goods through the stock-return workflow separately; wallet refunds do not automatically increase inventory.
- [ ] Verify referral reward reversals and review any negative referrer wallet balances after refund.

## Notifications

- [ ] Apply database/migrations/2026_11_06_notifications.sql after tenant isolation and verify customer, staff and manager inbox permissions on the real tenant.
- [ ] Configure and test the production SMS provider; in-app notifications do not replace SMS delivery or operational escalation.

## Media and operations

- [ ] Configure MEDIA_STORAGE_PATH outside the public PHP document root and MEDIA_BASE_URL through a static-only web-server alias or CDN.
- [ ] Upload the real store logo, product photography, aisle images and equirectangular panoramas; verify every public URL.
- [ ] Photograph and author every aisle scene and hotspot. Demo/sample images are not a substitute for the actual store.
- [ ] Schedule bin/expire-reservations.php every minute and monitor its exit status and logs.
- [ ] Configure automated database and media backups, restore tests, log rotation, uptime monitoring and alerting.
- [ ] Verify delivery coverage, opening hours, pickup rules, legal terms, privacy notice, refund policy and customer support contact.
- [ ] Run a full end-to-end acceptance test on desktop and mobile using the real domain, inventory, SMS and payment credentials.

Do not enable live checkout until all relevant boxes have been completed. No merchant credentials, live hosting, store photographs or customer contact details are included in this repository.
