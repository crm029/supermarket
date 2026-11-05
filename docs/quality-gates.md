# Release quality gates

A feature is not considered complete until it passes its relevant gates.

## Tenant isolation
- Tenant A cannot read, modify, enumerate or infer Tenant B records through IDs, filters, search, exports, media URLs or background jobs.
- Tenant resolution uses verified host mappings only.
- Automated negative tests cover every tenant-owned resource and cross-tenant foreign-key attempt.

## Payments and orders
- Price, stock, delivery zone and store status are recalculated on the server at checkout.
- Duplicate callbacks do not duplicate orders, stock movements or wallet entries.
- Failed/expired payments release reservations.
- Browser return URLs never mark an order paid.
- Refund and cancellation paths reconcile inventory and wallet/payment ledgers.

## Inventory
- Concurrent checkouts cannot oversell.
- All stock changes have actor, reason and source document.
- Transfers are paired outbound/inbound movements.
- Manual adjustments require permission and audit history.

## Customer privacy and security
- OTP and login endpoints are rate-limited.
- Passwords use PHP password_hash/password_verify.
- Sessions use secure, HTTP-only, SameSite cookies over HTTPS.
- Production logs do not contain passwords, OTPs, tokens or payment secrets. The local-only development SMS driver is the sole exception and must never be enabled in production.
- Uploads validate MIME, dimensions and size; filenames are generated server-side.

## Experience
- Search and standard product listing work without WebGL.
- Keyboard and touch navigation are supported.
- Public media URLs are tenant-owned, derived from a trusted HTTPS base URL in production, and reject traversal/backslash storage keys.\n- Images have useful alt text, responsive sizes and lazy loading.
- Store scenes have a clear fallback when an image or 3D asset fails.
- Mobile performance and accessibility are tested on real devices.

## Database integrity
- CI creates a disposable MySQL 8 database, imports the base schema and sample seed, applies the tenant-isolation migration, and runs negative tests proving cross-tenant category, role and audit-actor references are rejected.
- The migration must be applied only after a backup; legacy cross-tenant references must be corrected, not silently reassigned.

## Authentication
- OTP values are never stored or returned in plaintext; challenges expire and lock after bounded attempts.
- Request/verification limits are applied per tenant, phone and IP.
- Session cookies are HttpOnly, SameSite=Lax and Secure in production; only token hashes are stored.
- Session rows enforce tenant/user composite ownership, and logout revokes the server-side session.

## Authorization and catalog administration
- Admin product reads and writes require an explicit tenant-scoped permission; missing grants deny access.
- A role grant cannot cross its tenant/global role scope. Platform permissions are not accepted by tenant-scoped checks.
- Product category and category parent/media references are validated against the active tenant. Category parent changes cannot create cycles; deactivation is blocked while child categories or active products remain.\n- Product/category changes write audit events in the same transaction.
