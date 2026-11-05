# Delivery roadmap

Status is intentionally conservative: a checklist item is complete only when implemented and verified.

## Phase 0 — product and architecture
- [x] Initialize repository and record product vision.
- [x] Document tenant model, data model, API outline, security principles and quality gates.
- [x] Create visual storefront prototype.
- [x] Add initial MySQL schema for commerce, multi-warehouse inventory, media scenes, orders, wallet/referral and audit records.
- [x] Add verified-host tenant resolver and read-only store/category/product API, including product detail and published scene/hotspot reads.
- [x] Add local sample tenant seed and PHP syntax-check workflow.
- [x] Run PHP syntax checks in GitHub Actions and resolve any failures.
- [ ] Validate schema against the selected MySQL/MariaDB versions.
- [ ] Verify XAMPP installation on a real local machine.

## Phase 1 — secure production foundation
- [x] Add tenant-aware composite foreign keys, scoped/global role protections and cross-tenant negative tests (MySQL 8 CI verified).
- [x] OTP login challenge storage, expiry, attempt lockout and phone/IP rate limiting.
- [x] Opaque database-backed sessions, HttpOnly cookies and CSRF-protected logout.
- [x] Customer registration with pending status, OTP activation and optional referral attribution.\n- [x] Kavenegar Verify Lookup production OTP adapter with server-side credentials and delivery response validation.\n- [ ] Merchant Kavenegar account/template credentials, password reset and delivery reports.
- [x] Tenant/platform permission catalog, role grants, deny-by-default product API checks and product-change audit events.
- [ ] Role/permission management UI, platform authorization, and CSRF/policy enforcement on all future write routes.
- [ ] Platform super-admin tenant provisioning and domain verification.
- [x] Secure tenant-scoped image upload with MIME, size, dimension and storage-key validation.\n- [ ] Store settings, branding and media library browsing/cleanup.

## Phase 2 — catalog and commerce
- [x] Basic tenant-scoped product list/create/update/deactivate API with permission checks.
- [x] Basic tenant-scoped category list/create/update/deactivate API with parent-cycle and media ownership checks.
- [ ] Product variants, barcode uniqueness, promotions and advanced search.
- [x] Server-side cart quote validation against live prices and available stock.\n- [ ] Persistent browser cart across devices and sessions.
- [x] Customer address book API (list/create, tenant and owner scoped).\n- [ ] Address editing/deletion, delivery zones, pickup scheduling and configurable fees.
- [x] Transactional pending-order creation with cross-warehouse stock reservations and customer order history API.\n- [x] CLI worker to release expired stock reservations and cancel unpaid orders.\n- [ ] Payment verification, order state transitions and customer order detail/tracking.

## Phase 3 — immersive storefront
- [ ] Merchant scene/media editor.
- [x] Tenant-scoped read API for published photo-tour nodes, hotspots and product-to-shelf links.\n- [x] Merchant scene create/update/unpublish and hotspot authoring APIs.\n- [ ] Merchant scene editor and hotspot authoring workflow.
- [ ] Guided category-to-aisle navigation.
- [ ] Optional WebGL free-movement mode with keyboard/touch and low-end fallback.
- [ ] Real-store media onboarding and responsive image delivery.

## Phase 4 — store operations
- [x] Multi-warehouse stock receipt, transfer and adjustment services with tenant checks, reserved-stock protection and audit trail.
- [ ] Supplier purchase orders, stock returns, expiry/batch tracking and low-stock alerts.
- [ ] Transactional reservations, expiry/release and paid-order stock commit.
- [x] Customer cancellation for pending-payment orders with transactional stock release.
- [x] Tenant-scoped order queue and guarded fulfilment transitions (accept, picking, packing, pickup/dispatch, delivery).
- [ ] Cashier assignment, picker workflow, courier tracking and operational dashboards.
- [ ] Operational dashboards, stock alerts and sales reports.

## Phase 5 — customer growth and integrations
- [x] ZarinPal v4 request/verify adapter, payment-attempt persistence, callback verification, stock commit and duplicate-callback protection.
- [ ] Live merchant credentials, sandbox acceptance, settlement reconciliation and refund handling.
- [ ] 0098.ir SMS adapter, OTP templates and delivery reports.
- [ ] Append-only wallet posting and customer transaction history.
- [ ] Referral attribution, qualifying-order rules and anti-abuse controls.

## Phase 6 — SaaS launch
- [ ] Plans, subscription billing, platform support and finance audit.
- [ ] Custom domains, HTTPS, backups, monitoring and disaster recovery.
- [ ] Security review, load testing, accessibility and device testing.
- [ ] Merchant acceptance, operational runbooks and production release.
