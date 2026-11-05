# Supermarket 360

A multi-tenant, white-label supermarket commerce platform with an immersive virtual-storefront experience.

## Product direction

- Each merchant gets an independent branded storefront and domain.
- All stores run on one centrally operated application and database with strict tenant scoping.
- The future discovery marketplace is optional; it does not replace dedicated store websites.
- Customers can choose guided category navigation, photo-tour navigation, or optional free-movement 3D.
- Store owners replace sample imagery with their own entrance, aisle, shelf and product photography.

## Current repository status

This repository contains a visual PHP storefront prototype plus a tenant-aware catalog API and an initial customer OTP login/session API. It is an active development build, **not yet a production-ready commerce service**.

Implemented in this milestone:
- Persian RTL responsive storefront demo with category navigation, product cards, search and local demo cart.
- MySQL schema foundation for tenants/domains, users/roles, media, categories/products, virtual-store scenes/hotspots, multi-warehouse stock, stock movements/reservations, orders, payments, wallet ledger, referrals and audit logs.
- Verified-host tenant resolver and tenant-scoped catalog API routes for store profile, categories, product search/details and published photo-tour scenes/hotspots.
- Role/permission tables, deny-by-default tenant permission checks, audit logging and permission-guarded product/category administration endpoints.
- Rate-limited OTP login endpoints, hashed one-time challenges, opaque database-backed sessions, HttpOnly cookies and CSRF-protected logout. The development SMS driver logs codes only to the local PHP error log; no production SMS provider is enabled.
- Local sample tenant seed and API/setup documentation.
- GitHub Actions PHP/JavaScript checks plus MySQL 8 migration, tenant-isolation, authentication and permission-scope integration tests.
- Additive tenant-isolation migration with composite foreign keys for tenant-owned relations and scope guards for role assignments and audit actors.

Not implemented yet:
- Customer registration, account recovery and production SMS delivery (OTP login currently supports existing active accounts only).
- Persistent cart, server-side quote validation, transactional checkout and order tracking.
- Zarinpal integration, payment verification, refunds and reconciliation.
- Live stock reservation services, multi-warehouse workflows and courier operations.
- Wallet transactions and referral reward processing.
- Super-admin, store, cashier, warehouse and courier panels and a role/permission management UI.
- Product variants/barcode uniqueness and the scene editor, linked photo-tour hotspots and actual WebGL free-movement engine.
- Production deployment operations, backups, monitoring, load testing and security review.

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
