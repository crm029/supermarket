# Supermarket 360

A multi-tenant, white-label supermarket commerce platform with an immersive virtual-storefront experience.

## Product direction

- Each merchant gets an independent branded storefront and domain.
- All merchants run on one centrally operated application and database.
- Tenant data is isolated at the application and database-query layers.
- A central super-admin can provision and manage stores.
- A future discovery marketplace can list participating stores without merging their operational data.

## Planned capabilities

- Immersive store navigation: free movement, point-to-point photo tour, and category-to-aisle navigation.
- Real store photography and product photography, replaceable per tenant.
- Catalog, variants, pricing, promotions, stock and product details.
- Customer accounts, short unique referral codes, wallet ledger and order history.
- Online delivery by store courier and in-store pickup.
- Multi-warehouse inventory, stock movements and audit history.
- Order workflow with configurable preparation, cashier review and dispatch.
- Roles: platform super-admin, store manager, cashier, warehouse operator, delivery operator and customer.
- SMS adapter for 0098.ir and payment adapter for Zarinpal.
- Store pages: about, contact, policies and operating information.

## Architecture principles

1. Tenant isolation is mandatory: every tenant-owned record carries a store/tenant identifier and every request is scoped server-side.
2. Money is stored as integer rial amounts; display conversion is explicit and never inferred.
3. Wallets use an append-only ledger. No direct balance edits.
4. Payment callbacks are signature/authority verified, idempotent and reconciled server-side.
5. Stock is reserved during checkout and deducted/committed only after verified payment; expired reservations are released.
6. Inventory changes are recorded as immutable stock movements.
7. Secrets and provider credentials are environment variables, never committed.
8. The virtual store is progressive enhancement: conventional category and search shopping remains fully usable on low-end devices.
9. Store media is configurable data, not hard-coded into the application.
10. Critical actions are audited and protected by role-based authorization.

## Delivery roadmap

- [ ] Phase 0 — product specification, domain model and threat model
- [ ] Phase 1 — runnable application shell, tenant foundation and design system
- [ ] Phase 2 — catalog, media and virtual storefront
- [ ] Phase 3 — customer identity, cart, checkout and orders
- [ ] Phase 4 — inventory, warehouses and fulfillment
- [ ] Phase 5 — wallet, referrals, SMS and payment integrations
- [ ] Phase 6 — administration, reporting, hardening and deployment

## Development

The first milestone is a runnable local prototype. Production credentials, merchant photography, SMS account and Zarinpal merchant credentials are supplied only when deploying a real store.

See `docs/architecture.md`, `docs/roadmap.md` and `docs/security.md`.
