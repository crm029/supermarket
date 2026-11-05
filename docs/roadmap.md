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
- [x] Validate schema and tenant isolation against MySQL 8 CI.
- [ ] Validate MariaDB compatibility.
- [ ] Verify XAMPP installation on a real local machine.

## Phase 1 — secure production foundation
- [x] Add tenant-aware composite foreign keys, scoped/global role protections and cross-tenant negative tests (MySQL 8 CI verified).
- [x] OTP login challenge storage, expiry, attempt lockout and phone/IP rate limiting.
- [x] Opaque database-backed sessions, HttpOnly cookies and CSRF-protected logout.
- [x] Customer registration with pending status, OTP activation and optional referral attribution.
- [x] Kavenegar Verify Lookup production OTP adapter with server-side credentials and delivery response validation.
- [ ] Merchant Kavenegar account/template credentials, password reset and delivery reports.
- [x] Tenant/platform permission catalog, role grants, deny-by-default product API checks and product-change audit events.
- [ ] Role/permission management UI and platform super-admin authorization.
- [ ] Platform super-admin tenant provisioning and domain verification.
- [x] Secure tenant-scoped image upload with MIME, size, dimension and storage-key validation.
- [ ] Store settings UI, branding controls and media library browsing/cleanup.

## Phase 2 — catalog and commerce
- [x] Basic tenant-scoped product list/create/update/deactivate API with permission checks.
- [x] Basic tenant-scoped category list/create/update/deactivate API with parent-cycle and media ownership checks.
- [ ] Product variants, barcode uniqueness, promotions and advanced search.
- [x] Server-side cart quote validation against live prices and available stock.
- [x] Tenant-scoped persistent customer cart across devices, with guest-cart merge and server-side replacement.
- [x] Customer address book API (list/create, tenant and owner scoped).
- [ ] Address editing/deletion, delivery zones, pickup scheduling and configurable fees.
- [x] Transactional pending-order creation with cross-warehouse stock reservations and customer order history API.
- [x] CLI worker to release expired stock reservations and cancel unpaid orders.
- [x] ZarinPal server-side verification, order state transitions and customer order detail/cancellation.
- [ ] Live provider acceptance, refund/reconciliation and courier tracking.

## Phase 3 — immersive storefront
- [x] Merchant scene/media editor API and admin UI.
- [x] Tenant-scoped read API for published photo-tour nodes, hotspots and product-to-shelf links.
- [x] Merchant scene create/update/unpublish and hotspot authoring APIs.
- [x] Merchant scene editor and hotspot authoring workflow with image upload and click-to-place hotspots.
- [x] Category/product/navigation hotspots connect scenes to the live catalogue.
- [ ] Optional WebGL free-movement mode with keyboard/touch and low-end fallback.
- [ ] Real-store media onboarding and responsive image delivery.

## Phase 4 — store operations
- [x] Multi-warehouse stock receipt, transfer and adjustment services with tenant checks, reserved-stock protection and audit trail.
- [ ] Supplier purchase orders, stock returns, expiry/batch tracking and low-stock alerts.
- [x] Transactional reservations, expiry/release and paid-order stock commit.
- [x] Customer cancellation for pending-payment orders with transactional stock release.
- [x] Tenant-scoped order queue and guarded fulfilment transitions (accept, picking, packing, pickup/dispatch, delivery).
- [ ] Cashier assignment, picker workflow, courier tracking and operational dashboards.
- [x] Seller dashboard with 30-day paid revenue, seven-day sales trend and low-stock count.
- [ ] Configurable stock thresholds, notifications and report exports.

## Phase 5 — customer growth and integrations
- [x] ZarinPal v4 request/verify adapter, payment-attempt persistence, callback verification, stock commit and duplicate-callback protection.
- [ ] Live merchant credentials, sandbox acceptance, settlement reconciliation and refund handling.
- [x] Kavenegar Verify Lookup OTP adapter with configurable template.
- [ ] Live SMS account/template credentials, delivery reports and alternate-provider adapter.
- [x] Append-only wallet credit ledger, customer balance/history endpoint and idempotent first-paid-order referral rewards (merchant-configured rate).
- [x] Referral attribution on OTP-verified registration.
- [x] Qualifying-order referral reward: only the referred customer's first verified paid order; tenant-scoped attribution, self-referral rejection and idempotent wallet credit. Advanced fraud scoring remains future work.

## Phase 6 — SaaS launch
- [ ] Plans, subscription billing, platform support and finance audit.
- [ ] Custom domains, HTTPS, backups, monitoring and disaster recovery.
- [ ] Security review, load testing, accessibility and device testing.
- [ ] Merchant acceptance, operational runbooks and production release.
