# Supermarket 360

A multi-tenant, white-label supermarket commerce platform with an immersive virtual-storefront experience.

## Product direction

- Each merchant gets an independent branded storefront and domain.
- All stores run on one centrally operated application and database with strict tenant scoping.
- The future discovery marketplace is optional; it does not replace dedicated store websites.
- Customers can choose guided category navigation, photo-tour navigation, or optional free-movement 3D.
- Store owners replace sample imagery with their own entrance, aisle, shelf and product photography.

## Current repository status

The repository contains a substantial tenant-aware PHP commerce platform and an immersive virtual-storefront experience. The repository includes PHP/JavaScript linting plus MySQL 8 and MariaDB 10.11 integration gates; check the Actions tab for the latest run status. This is still an active development build and is **not approved for production launch** until real hosting, provider acceptance and operational release gates are completed.

Implemented and covered by repository tests:
- Persian RTL responsive storefront with guided category navigation, photo-tour scenes, interactive hotspots and optional WebGL free movement through live category shelves, with a non-WebGL fallback.
- Central MySQL multi-tenant architecture, verified-host tenant resolution, composite tenant-scope constraints and cross-tenant negative tests.
- Platform-owner OTP access, tenant provisioning, one-time DNS TXT domain ownership challenges, tenant listing and audited platform actions.
- Tenant-aware product/category SEO, canonical URLs, structured data, paginated category pages, sitemap index/product shards, robots rules and private-source path protection.
- OTP registration/login with rate limiting, hashed challenges, database-backed sessions, HttpOnly cookies and CSRF protection; registration profile/referral data is only attached after OTP verification. Kavenegar and platform-configured 0098 VIP SMS adapters are available when real credentials are configured.
- Deny-by-default tenant permissions, seller dashboard, staff roles, permission management and audited catalogue operations.
- Tenant media library, scene/hotspot authoring, read-only storage reconciliation (orphan/missing-file detection with CI coverage), multi-warehouse inventory, transfers/adjustments, supplier purchasing and returns, FEFO batch/expiry tracking, low-stock alerts and stock reservation workers.
- Server-validated persistent carts, customer addresses, delivery zones, scheduled pickup, coupons, transactional checkout, order history/cancellation and guarded fulfilment transitions.
- Picker/courier/pickup-handoff assignments, recipient-confirmed delivery and in-person pickup, customer shipment timeline, operational dashboard KPIs and in-app notifications.
- ZarinPal v4 adapter with idempotent server-side verification, encrypted platform-owner-only merchant credentials, tenant-specific verified-domain callbacks, gateway-mode snapshots and a manual-review state for uncertain provider outcomes.
- Append-only wallet ledger, customer wallet checkout, idempotent settlement, transactional inventory commit, audited full-order wallet-credit refunds and first-paid-order referral rewards.
- GitHub Actions PHP/JavaScript validation and MySQL-backed schema, tenant isolation, authentication, commerce, wallet/referral, SEO and API integration checks.

Still in progress or requiring real-environment acceptance:
- POS software release is implemented and CI-tested: cashier page/API, shift opening/closing and cash reconciliation, barcode/SKU search, server-priced FEFO cash sales, idempotency, manager reports, audited cash refunds, returned-item inspection/restock-or-reject, and browser thermal receipt layouts for 58mm/80mm. Physical printer/scanner/acquirer acceptance is an optional merchant rollout check; direct ESC/POS, vendor SDK and bank-terminal integrations remain future work.
- Real 0098 VIP/Kavenegar account configuration, live-phone delivery and delivery-report acceptance; 0098 VIP credentials are sent in the provider-required HTTPS query parameters, so infrastructure URL logging must be disabled.
- Real ZarinPal sandbox/live transactions, settlement reconciliation and bank/gateway refund procedures.
- Advanced referral fraud controls and merchant reward-policy approval.
- Real-store photography, final media/CDN delivery and image-performance review; schedule `php scripts/reconcile-media.php` daily and alert on exit code 2 to detect storage/database drift.
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
- Apply every database migration in the dependency-verified order in `docs/local-setup.md`; do not sort migrations by filename.

## Documentation

- `docs/architecture.md` — system topology and design decisions.
- `docs/data-model.md` — database and transaction rules.
- `docs/api-contract.md` — planned API surface.
- `docs/local-setup.md` — local setup and current limitations.
- `docs/tenant-onboarding.md` — merchant onboarding.
- `docs/quality-gates.md` — release acceptance gates.
- `docs/security.md` — security principles.
- `docs/security-audit-2026-11-20.md` — latest source-level security re-review and remaining production blockers.
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
- Configure the ZarinPal merchant ID and verify that every tenant has a verified primary HTTPS domain for its callback. Uncertain payment attempts enter `review` and must be reconciled with the provider before another attempt. See docs/local-setup.md.
- Schedule bin/expire-reservations.php every minute to release stock for unpaid orders.
- Apply database/migrations/2026_11_06_notifications.sql after tenant isolation to enable the in-app inbox. Order creation, payment verification, status changes, assignments, task completion and customer cancellations create idempotent notifications.
- Customers and staff can open notifications.php to view their own messages and mark them read.
- The seller can issue a full-order refund as wallet credit using the orders.refund permission; this is not a bank/gateway refund and returned goods are not automatically restocked.


For first-time tenant setup, apply database/bootstrap-store-owner.sql after all migrations (edit its tenant and owner variables first). See docs/local-setup.md.


Production deployment and external-service acceptance checklist: docs/production-readiness.md. Live launch still requires the store's real hosting, domain, SMS and payment credentials, and store-specific photography.


### POS management reports

The seller admin links to `pos-reports.php`, a tenant-scoped reporting view for date-filtered in-person sales, online-vs-in-person totals, shift counts and cash variance, cashier performance, and top-selling in-person items. Its API endpoint is `GET /api/v1/admin/pos/reports` and requires the existing `reports.read` permission. Amounts are stored in rial and displayed in toman.


### POS cash refunds

Cashier POS refunds are recorded in an immutable, tenant-scoped refund ledger through `POST /api/v1/admin/pos/sales/{sale_id}/refunds`. Managers can use `pos-refunds.php` from the seller dashboard to browse recent sales, inspect item-level refundable quantities, and submit cash refunds. Sale review is available through `GET /api/v1/admin/pos/sales/{sale_id}` and returns each original sale-item ID plus refunded and remaining quantities. The refund endpoint requires CSRF protection, the dedicated `pos.refund.create` permission, an open cashier shift, a reason, and an idempotency key. It locks the original sale and its sale items, rejects quantities already refunded, calculates the amount from the original price snapshot, and records the refund against the active shift. Original sale rows are never edited or deleted. Returned items remain `pending_inspection`; the refund endpoint intentionally does not put them back into sellable stock because the consumed FEFO batch identity is not currently persisted per sale item. The shift's expected cash is reduced by refunds.


### POS returned-item inspection

Returned POS items remain outside sellable stock until an authorized inspector completes the workflow at `pos-inspection.php`. The API requires the separate `pos.refund.inspect` permission. Restocking requires an inspection note, a new batch identifier and a non-expired expiry date; the system creates a traceable stock batch, increments the warehouse balance, writes a `return` stock movement and appends an inspection/audit record in one transaction. Rejected items are recorded without increasing sellable stock. Repeated inspection attempts are rejected.


Platform SaaS billing includes plan management, product/staff/media/monthly-order/storage-byte quotas, tenant subscriptions, partial-payment invoices, an append-only receipt ledger and a scheduled renewal, grace-period and idempotent in-app payment-reminder worker, plus owner-requested plan changes that require platform approval and full invoice payment. It records manual receipts; it does not automatically debit merchants or suspend overdue stores. See `docs/local-setup.md`.

Store owners can review their subscription invoices and live quota usage, request a plan change, or schedule/reverse cancellation at the end of the paid billing period.

The cashier screen includes a permission-checked receipt preview with 58mm and 80mm browser-print layouts for POS sales. Printing uses the operating system's installed printer drivers; device-specific direct protocols are not part of this software release.


Production acceptance: see [`docs/merchant-acceptance.md`](docs/merchant-acceptance.md) for the merchant SMS, payment, settlement and launch checklist.\n\nMedia operations: see [`docs/media-operations.md`](docs/media-operations.md) for reconciliation monitoring, cleanup scheduling and bounded retention.
