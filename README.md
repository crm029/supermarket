# Supermarket 360

A multi-tenant, white-label supermarket commerce platform with an immersive virtual-storefront experience.

## Product direction

- Each merchant gets an independent branded storefront and domain.
- All stores run on one centrally operated application and database with strict tenant scoping.
- The future discovery marketplace is optional; it does not replace dedicated store websites.
- Customers can choose guided category navigation, photo-tour navigation, or optional free-movement 3D.
- Store owners replace sample imagery with their own entrance, aisle, shelf and product photography.

## Current repository status

This repository contains a visual PHP storefront prototype plus the first database and tenant-aware read API foundation. It is an active development build, **not yet a production-ready commerce service**.

Implemented in this milestone:
- Persian RTL responsive storefront demo with category navigation, product cards, search and local demo cart.
- MySQL schema foundation for tenants/domains, users/roles, media, categories/products, virtual-store scenes/hotspots, multi-warehouse stock, stock movements/reservations, orders, payments, wallet ledger, referrals and audit logs.
- Verified-host tenant resolver and read-only API routes for store profile, categories and product search.
- Local sample tenant seed and API/setup documentation.
- GitHub Actions PHP syntax-check workflow.

Not implemented yet:
- Real customer registration/OTP, persistent cart and checkout.
- Live stock reservation services and order workflow.
- Zarinpal and 0098.ir integrations.
- Wallet transactions and referral reward processing.
- Super-admin/store/cashier/warehouse/courier panels.
- Scene editor, linked photo-tour hotspots and the actual WebGL free-movement engine.
- Production-grade composite tenant foreign keys, authorization policies, CSRF, automated security/integration tests and deployment operations.

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
