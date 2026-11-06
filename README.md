# Supermarket 360

A multi-tenant, white-label supermarket commerce platform with an immersive virtual-storefront experience.

## Product direction

- Each merchant gets an independent branded storefront and domain.
- All stores run on one centrally operated application and database with strict tenant scoping.
- The future discovery marketplace is optional; it does not replace dedicated store websites.
- Customers can choose guided category navigation, photo-tour navigation, or optional free-movement 3D.
- Store owners replace sample imagery with their own entrance, aisle, shelf and product photography.

## Current repository status

The repository contains a substantial tenant-aware PHP commerce platform and an immersive virtual-storefront experience. The latest main-branch CI runs for PHP syntax and quality/security checks are passing. This is still an active development build and is **not approved for production launch** until real hosting, provider acceptance and operational release gates are completed.

Implemented and covered by repository tests:
- Persian RTL responsive storefront with guided category navigation, photo-tour scenes, interactive hotspots and optional WebGL free movement through live category shelves, with a non-WebGL fallback.
- Central MySQL multi-tenant architecture, verified-host tenant resolution, composite tenant-scope constraints and cross-tenant negative tests.
- Platform-owner OTP access, tenant provisioning, one-time DNS TXT domain ownership challenges, tenant listing and audited platform actions.
- Tenant-aware product/category SEO, canonical URLs, structured data, paginated category pages, sitemap index/product shards, robots rules and private-source path protection.
- OTP registration/login foundation, rate limiting, hashed challenges, database-backed sessions, HttpOnly cookies and CSRF protection; Kavenegar Verify Lookup adapter is available when real credentials are configured.
- Deny-by-default tenant permissions, seller dashboard, staff roles, permission management and audited catalogue operations.
- Tenant media library, scene/hotspot authoring, multi-warehouse inventory, transfers/adjustments, supplier purchasing and returns, FEFO batch/expiry tracking, low-stock alerts and stock reservation workers.
- Server-validated persistent carts, customer addresses, delivery zones, scheduled pickup, coupons, transactional checkout, order history/cancellation and guarded fulfilment transitions.
- Picker/courier/pickup-handoff assignments, recipient-confirmed delivery and in-person pickup, customer shipment timeline, operational dashboard KPIs and in-app notifications.
- ZarinPal v4 adapter with idempotent server-side verification, encrypted platform-owner-only merchant credentials and Sandbox/Real controls.
- Append-only wallet ledger, customer wallet checkout, idempotent settlement, transactional inventory commit, audited full-order wallet-credit refunds and first-paid-order referral rewards.
- GitHub Actions PHP/JavaScript validation and MySQL-backed schema, tenant isolation, authentication, commerce, wallet/referral, SEO and API integration checks.

Still in progress or requiring real-environment acceptance:
- Cashier POS page and API: shift opening/closing, barcode/SKU/product search, server-priced cash sales, FEFO batch stock deduction, idempotent sale requests, change calculation and shift cash reconciliation; cashier role creation/assignment uses the staff permission panel. Payment terminals, refunds and thermal printing remain pending.
- Real SMS account/template configuration, live-phone delivery and delivery-report acceptance.
- Real ZarinPal sandbox/live transactions, settlement reconciliation and bank/gateway refund procedures.
- Advanced referral fraud controls and merchant reward-policy approval.
- Real-store photography, final media/CDN delivery and image-performance review.
- Google Search Console verification, sitemap submission, GA4 event receipt and production Core Web Vitals review.
- Production hosting, HTTPS, least-privilege database account, secret management, backups, restore drills, monitoring and alerting.
- Independent staging penetration test, load/concurrency, accessibility, responsive-device and WebGL fallback acceptance.


## Architecture principles

1. Resolve tenant identity from a verified domain mapping; never trust a tenant ID from the browser.
2. Scope every tenant-owned query and test cross-tenant isolation.
3. Store money as integer rial values.
4. Keep wallet entries and stock movements append-only; do not edit balances/history directly.
5. Reserve stock transactionally at checkout and commit only after server-side payment verification.
6. Make payment callbacks idempotent and reconcile provider results.
7. Keep all secrets outside Git.
8. Keep normal search/catalog shopping usable when 3D is unavailable.
9. Treat store photography and product imagery as tenant-managed media.
10. Audit sensitive operations and enforce permission-based access.

## Local development

- Visual demo: place the repository in XAMPP's `htdocs` and open the project root.
- API: requires PHP 8.2+ with PDO MySQL, mbstring, cURL, OpenSSL and fileinfo; production domain onboarding also requires PHP DNS TXT lookup. See `docs/local-setup.md`.
- Optional sample database: import `database/schema.sql`, then `database/demo-seed.sql` into a fresh local database. The sample domain is `localhost`.
- After the existing 2026_11_05 migrations, apply in order: `2026_11_06_order_assignments.sql`, `2026_11_06_pos_cash_register.sql`, `2026_11_06_pickup_handoff.sql`, `2026_11_06_notifications.sql`, `2026_11_06_order_wallet_refunds.sql`, `2026_11_06_domain_verification_challenge.sql`, and `2026_11_06_platform_payment_settings.sql`. See `docs/local-setup.md` for the full sequence.

## Documentation

- `docs/architecture.md` — system topology and design decisions.
- `docs/data-model.md` — database and transaction rules.
- `docs/api-contract.md` — planned API surface.
- `docs/local-setup.md` — local setup and current limitations.
- `docs/tenant-onboarding.md` — merchant onboarding.
- `docs/quality-gates.md` — release acceptance gates.
- `docs/security.md` — security principles.
- `docs/security-audit-2026-11-06.md` — current source-level security review and remaining production blockers.
- `docs/roadmap.md` — delivery status.
- `docs/seo.md` — tenant-aware SEO implementation and merchant launch checklist.

Real merchant photography, production domains, SMS credentials and payment-gateway credentials are supplied only for an actual merchant deployment.


## Customer checkout and seller dashboard

- Storefront: index.php
- Seller dashboard: admin.php (catalog, orders and inventory screens require the matching server-side permissions)
- Platform dashboard: platform.php; grant global access only to an active OTP-verified account using database/bootstrap-platform-admin.sql after all migrations.
- New tenants remain pending until the platform DNS TXT challenge at _supermarket360-verification.<domain> is resolved to the one-time token. Tokens expire after 24 hours and can be reissued; the backend verifies the TXT value before activation.
- Payment result: payment-result.php
- Customer registration uses mobile OTP; the development-only log provider is not a production SMS service.
- ZarinPal merchant credentials and Sandbox/Real selection are configured only by the platform owner in `platform.php`; the merchant ID is encrypted at rest and never returned in full by the API.
- Checkout validates the cart on the server, saves the customer's delivery address, creates a pending-payment order and reserves stock for 20 minutes.
- Configure the ZarinPal merchant ID and exact public callback origin before testing payment. See docs/local-setup.md.
- Schedule bin/expire-reservations.php every minute to release stock for unpaid orders.
- Apply database/migrations/2026_11_06_notifications.sql after tenant isolation to enable the in-app inbox. Order creation, payment verification, status changes, assignments, task completion and customer cancellations create idempotent notifications.
- Customers and staff can open notifications.php to view their own messages and mark them read.
- The seller can issue a full-order refund as wallet credit using the orders.refund permission; this is not a bank/gateway refund and returned goods are not automatically restocked.


For first-time tenant setup, apply database/bootstrap-store-owner.sql after all migrations (edit its tenant and owner variables first). See docs/local-setup.md.


Production deployment and external-service acceptance checklist: docs/production-readiness.md. Live launch still requires the store's real hosting, domain, SMS and payment credentials, and store-specific photography.


### POS management reports

The seller admin links to `pos-reports.php`, a tenant-scoped reporting view for date-filtered in-person sales, online-vs-in-person totals, shift counts and cash variance, cashier performance, and top-selling in-person items. Its API endpoint is `GET /api/v1/admin/pos/reports` and requires the existing `reports.read` permission. Amounts are stored in rial and displayed in toman.
