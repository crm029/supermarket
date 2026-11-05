# Production readiness checklist

The application code includes customer registration, catalogue management, inventory, interactive scenes, order workflow and provider adapters. A live store is not production-ready until every external service and the real tenant data below are configured and tested.

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

## Media and operations

- [ ] Configure MEDIA_STORAGE_PATH outside the public PHP document root and MEDIA_BASE_URL through a static-only web-server alias or CDN.
- [ ] Upload the real store logo, product photography, aisle images and equirectangular panoramas; verify every public URL.
- [ ] Photograph and author every aisle scene and hotspot. Demo/sample images are not a substitute for the actual store.
- [ ] Schedule bin/expire-reservations.php every minute and monitor its exit status and logs.
- [ ] Configure automated database and media backups, restore tests, log rotation, uptime monitoring and alerting.
- [ ] Verify delivery coverage, opening hours, pickup rules, legal terms, privacy notice, refund policy and customer support contact.
- [ ] Run a full end-to-end acceptance test on desktop and mobile using the real domain, inventory, SMS and payment credentials.

Do not enable live checkout until all relevant boxes have been completed. No merchant credentials, live hosting, store photographs or customer contact details are included in this repository.
