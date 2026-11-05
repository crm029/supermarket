# Security and production readiness review — 2026-11-06

## Scope and confidence

This is a source-level review of the current repository and its documented CI coverage. It is not a penetration test, a deployment audit or a certificate that a live store is secure. No merchant production host, live database, DNS zone, SMS account, payment terminal, backups or customer data were available during this review.

## Current implemented security controls

The repository currently includes the following controls and automated coverage:

- Verified-host tenant resolution; browser-supplied tenant IDs are not trusted.
- Tenant-aware composite foreign keys and negative cross-tenant database tests.
- OTP challenges stored as hashes, expiry/attempt limits, per-phone/IP rate limits and opaque database-backed sessions.
- HttpOnly/SameSite cookies, CSRF checks for protected browser mutations and server-side permission checks.
- Deny-by-default tenant permissions, staff-role management boundaries and audit events for sensitive operations.
- Tenant-scoped catalogue, customer carts, addresses, delivery zones, pickup slots, stock reservations, orders, media and wallet records.
- Server-side checkout price/stock validation, transactional reservations and an expiry worker.
- ZarinPal request/verification adapter, server-side callback verification and duplicate-callback handling.
- Append-only wallet/referral credit entries and idempotent qualifying referral rewards.
- Media URL/path validation and tenant-scoped media upload/cleanup services.
- Tenant-aware product/category canonical URLs, sitemap/robots output, structured data, Search Console verification and consent-gated GA4.
- CSV report export permission checks and spreadsheet-formula injection protection.

The GitHub Actions quality workflow applies the MySQL schema and migrations, runs cross-tenant negative tests, authentication/permission tests, HTTP commerce smoke tests, wallet/referral integration tests and SEO smoke tests. The workflow also checks PHP and JavaScript syntax.

## Remaining release blockers

The following items remain incomplete or require real-environment acceptance:

1. Platform super-admin authorization, tenant provisioning and verified custom-domain onboarding.
2. Cashier/picker/courier assignment, courier tracking, operational dashboards and notifications.
3. Wallet spending, refunds/reversals, merchant reward approval and stronger referral fraud controls.
4. Live Kavenegar credentials/template and real-phone delivery, resend and delivery-report acceptance.
5. ZarinPal merchant-owned credentials, sandbox/live acceptance, settlement reconciliation and refund procedures.
6. Real merchant product/aisle photography, final media CDN/static mapping and image-performance review.
7. Production hosting, HTTPS, least-privilege DB account, secret management, backups, restore drills, monitoring, alerting and log retention.
8. Real-device responsive, accessibility, WebGL fallback and load/concurrency tests on the deployed environment.
9. Production Search Console verification, sitemap submission, GA4 event receipt and Core Web Vitals review.
10. Independent security review/penetration testing of a staging deployment.

## Deployment preflight

Run:

`APP_ENV=production php bin/production-preflight.php`

The command is read-only. It checks PHP version/extensions, core database tables, verified active tenant domains, HTTPS origins, SMS/payment configuration and media storage. It does not send an SMS, contact the payment provider, prove callback delivery, test database backups or validate live hosting. A pass is necessary but not sufficient for launch.

## Release decision

**NOT APPROVED FOR LIVE CUSTOMER PAYMENTS YET.**

The repository has a substantial tested commerce foundation, but production launch still depends on platform-level administration, merchant credentials, operational workflows and real-host acceptance. Keep live customer data and payment credentials out of unverified environments. Complete every relevant release blocker and obtain a staging security review before enabling live checkout.
