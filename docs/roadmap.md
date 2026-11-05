# Delivery roadmap

Status is intentionally conservative: a checklist item is complete only when implemented and verified.

## Phase 0 — product and architecture
- [x] Initialize repository and record product vision.
- [x] Document tenant model, data model, API outline, security principles and quality gates.
- [x] Create visual storefront prototype.
- [x] Add initial MySQL schema for commerce, multi-warehouse inventory, media scenes, orders, wallet/referral and audit records.
- [x] Add verified-host tenant resolver and read-only store/category/product API.
- [x] Add local sample tenant seed and PHP syntax-check workflow.
- [ ] Run PHP syntax checks in GitHub Actions and resolve any failures.
- [ ] Validate schema against the selected MySQL/MariaDB versions.
- [ ] Verify XAMPP installation on a real local machine.

## Phase 1 — secure production foundation
- [x] Add tenant-aware composite foreign keys, scoped/global role protections and cross-tenant negative tests (MySQL 8 CI verified).
- [x] OTP login challenge storage, expiry, attempt lockout and phone/IP rate limiting.
- [x] Opaque database-backed sessions, HttpOnly cookies and CSRF-protected logout.
- [ ] Production SMS provider adapter, customer registration and password reset.
- [ ] Permission matrix, policies, CSRF protection and audit service.
- [ ] Platform super-admin tenant provisioning and domain verification.
- [ ] Store settings, branding and secure media library.

## Phase 2 — catalog and commerce
- [ ] Complete product/category CRUD, variants, barcode, pricing, promotions and search.
- [ ] Persistent cart and server-side quote validation.
- [ ] Address book, delivery zones, pickup scheduling and fees.
- [ ] Transactional checkout, order state machine and customer order tracking.

## Phase 3 — immersive storefront
- [ ] Merchant scene/media editor.
- [ ] Photo-tour nodes, hotspots and product-to-shelf links.
- [ ] Guided category-to-aisle navigation.
- [ ] Optional WebGL free-movement mode with keyboard/touch and low-end fallback.
- [ ] Real-store media onboarding and responsive image delivery.

## Phase 4 — store operations
- [ ] Multi-warehouse stock receipt, transfer, adjustment and return services.
- [ ] Transactional reservations, expiry/release and paid-order stock commit.
- [ ] Cashier review, picking, packing, pickup and courier dispatch.
- [ ] Operational dashboards, stock alerts and sales reports.

## Phase 5 — customer growth and integrations
- [ ] Zarinpal adapter, verified callbacks, idempotency and reconciliation.
- [ ] 0098.ir SMS adapter, OTP templates and delivery reports.
- [ ] Append-only wallet posting and customer transaction history.
- [ ] Referral attribution, qualifying-order rules and anti-abuse controls.

## Phase 6 — SaaS launch
- [ ] Plans, subscription billing, platform support and finance audit.
- [ ] Custom domains, HTTPS, backups, monitoring and disaster recovery.
- [ ] Security review, load testing, accessibility and device testing.
- [ ] Merchant acceptance, operational runbooks and production release.
