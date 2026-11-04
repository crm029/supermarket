# Architecture — Supermarket 360

## Product topology

One centrally hosted application and one central relational database serve many independent merchant tenants. Each merchant receives a custom domain (or subdomain) routed to the same application. A future marketplace is a separate discovery surface, not a shared operational tenant.

## Recommended production stack

- Backend: Laravel (PHP) with a modular monolith structure.
- Database: MySQL 8 / MariaDB with InnoDB and strict foreign keys.
- Cache, queues and rate limits: Redis.
- Media: S3-compatible object storage; local disk for development only.
- Frontend: server-rendered storefront with progressive enhancement; Three.js/WebGL scene as an optional experience layer.
- Deployment: Linux VPS/container, HTTPS, automated backups and CI.

The first repository milestone is a dependency-free PHP visual prototype so the user can preview the concept on XAMPP. It is not yet the production backend.

## Tenant model

- Resolve tenant from verified host/domain mapping, never from an untrusted tenant ID supplied by the browser.
- Add tenant_id to every tenant-owned table and include it in unique indexes and foreign keys.
- Central platform tables: tenants, domains, platform_users, plans, platform_audit_logs.
- Tenant-owned tables: users, roles, permissions, addresses, categories, products, product_media, product_variants, prices, warehouses, stock_levels, stock_movements, carts, orders, order_items, payment_attempts, wallet_accounts, wallet_entries, referral_codes, referral_events, delivery_zones, store_pages, store_scenes, scene_hotspots, settings.
- Enforce tenant scoping in middleware, policies, repository/query layer and database constraints. Add automated cross-tenant access tests.

## Storefront experience

Three selectable modes:
1. Guided: category selection takes the customer to the relevant aisle.
2. Photo tour: connected real photographs and hotspots form a navigable panorama/tour.
3. Free movement: optimized 3D aisle scene, keyboard and touch controls.

A normal accessible catalog/search/list view is always available. 3D is optional and must not block shopping on slow devices. Product images and scene assets are tenant-managed media records, not hard-coded URLs.

## Order and stock lifecycle

1. Validate cart prices, tenant, store opening hours, delivery area and stock on the server.
2. Create a pending order and time-limited stock reservation in a database transaction.
3. Create a payment attempt with an idempotency key.
4. Verify payment server-to-server with the gateway; never trust the browser redirect.
5. On verified success, finalize order and stock movement atomically; on failure/expiry release reservations.
6. Fulfillment workflow: accepted → picking → packed → ready for pickup / dispatched → delivered; cancellation/refund paths are audited.

## Inventory

Multi-warehouse by tenant. A stock movement ledger is authoritative; stock levels are a transactionally maintained projection. Receipts, transfers, adjustments, reservations, dispatches and returns each have movement types, actor, reason and source document. Adjustments require permission and reason.

## Wallet and referrals

Wallet is a double-entry or balanced append-only ledger; balance is derived from posted entries. No editable balance field. Every entry has source, reference, idempotency key and status. Referral codes are short, unique, case-normalized and generated server-side; attribution rules, fraud limits and reward timing are configurable. Rewards are credited only after eligible paid orders pass refund/cancellation windows.

## Roles

Platform: super-admin, support operator, finance auditor.
Store: owner/manager, cashier, warehouse operator, picker, courier, customer.
Use permission-based access control; role names are presets, not hard-coded authorization checks.

## Integrations

Use provider interfaces for SMS and payments. 0098.ir and Zarinpal adapters must keep secrets server-side, verify provider responses, implement retries with bounded backoff and record delivery/payment events. Test/sandbox mode must be clearly separated from production.

## Store media onboarding

Each tenant can upload logo, storefront hero, aisle photographs, panorama/tour nodes, hotspots, category cover images and product images. Media records support alt text, ordering, focal point, dimensions and replacement history. Never present sample stock photography as a photograph of the merchant's actual shop.
