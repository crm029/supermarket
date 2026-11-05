# Supermarket 360

A multi-tenant, white-label supermarket commerce platform with an immersive virtual-storefront experience.

## Product direction

- Each merchant gets an independent branded storefront and domain.
- All stores run on one centrally operated application and database with strict tenant scoping.
- The future discovery marketplace is optional; it does not replace dedicated store websites.
- Customers can choose guided category navigation, photo-tour navigation, or optional free-movement 3D.
- Store owners replace sample imagery with their own entrance, aisle, shelf and product photography.

## Current repository status

The repository now contains a tenant-aware PHP commerce backend, customer authentication, catalogue and stock operations, a photo-tour storefront editor, checkout and a ZarinPal adapter. It remains an active development build, **not yet approved for production**.

Implemented and covered by repository tests:
- Persian RTL responsive storefront with guided category navigation, live tenant catalogue/product details, photo-tour scenes, interactive hotspots and procedural WebGL free movement through category-labelled aisles (with a non-WebGL fallback).
- Tenant-aware MySQL schema, verified-host tenant resolution, composite tenant-scope constraints and cross-tenant negative tests.
- OTP registration/login foundation, rate limiting, hashed challenges, database-backed sessions, HttpOnly cookies and CSRF protection; Kavenegar Verify Lookup adapter is available when real credentials are configured.
- Deny-by-default tenant permission checks and audited product/category administration.
- Seller dashboard, merchant branding and checkout settings, staff roles and permission management, tenant media library, media uploads, scene/hotspot authoring, multi-warehouse receipts/transfers/adjustments, stock reservation and expiry worker.
- Server-validated cart quotes, persistent cross-device customer carts, customer addresses, tenant postal-code delivery zones, capacity-controlled scheduled pickup, tenant-scoped percentage/fixed coupons with usage limits, transactional pending checkout, customer order history/cancellation and fulfilment transitions.
- ZarinPal v4 request/verification adapter with idempotent callbacks and stock commit after server-side verification.
- Append-only wallet credit ledger, customer balance/history API and configurable first-paid-order referral rewards.
- GitHub Actions PHP/JavaScript validation and MySQL-backed schema, tenant isolation and HTTP API integration checks.

Still in progress:
- Platform super-admin authorization and tenant/domain provisioning.
- Supplier purchase orders, stock returns and batch/expiry workflows.
- Wallet spending, refunds/reversals, advanced referral fraud controls and reward policy approval for each merchant.
- Cashier/picker/courier assignment, courier tracking, notifications, configurable stock alerts and report exports.
- Real-store photography/onboarding and responsive image delivery.
- Live merchant SMS/payment credentials, settlement reconciliation, refund acceptance, hosting, backups, monitoring, load/device/accessibility testing and production sign-off.

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
- API: requires PHP 8.1+ with PDO MySQL and MySQL/MariaDB. See `docs/local-setup.md`.
- Optional sample database: import `database/schema.sql`, then `database/demo-seed.sql` into a fresh local database. The sample domain is `localhost`.

## Documentation

- `docs/architecture.md` — system topology and design decisions.
- `docs/data-model.md` — database and transaction rules.
- `docs/api-contract.md` — planned API surface.
- `docs/local-setup.md` — local setup and current limitations.
- `docs/tenant-onboarding.md` — merchant onboarding.
- `docs/quality-gates.md` — release acceptance gates.
- `docs/security.md` — security principles.
- `docs/roadmap.md` — delivery status.

Real merchant photography, production domains, SMS credentials and payment-gateway credentials are supplied only for an actual merchant deployment.


## Customer checkout and seller dashboard

- Storefront: index.php
- Seller dashboard: admin.php (catalog, orders and inventory screens require the matching server-side permissions)
- Payment result: payment-result.php
- Customer registration uses mobile OTP; the development-only log provider is not a production SMS service.
- Checkout validates the cart on the server, saves the customer's delivery address, creates a pending-payment order and reserves stock for 20 minutes.
- Configure the ZarinPal merchant ID and exact public callback origin before testing payment. See docs/local-setup.md.
- Schedule bin/expire-reservations.php every minute to release stock for unpaid orders.


For first-time tenant setup, apply database/bootstrap-store-owner.sql after all migrations (edit its tenant and owner variables first). See docs/local-setup.md.


Production deployment and external-service acceptance checklist: docs/production-readiness.md. Live launch still requires the store's real hosting, domain, SMS and payment credentials, and store-specific photography.
