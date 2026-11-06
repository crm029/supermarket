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
- [ ] Merchant SMS credentials/templates, password recovery and delivery reports; platform OTP adapter is implemented.
- [x] Tenant/platform permission catalog, role grants, deny-by-default product API checks and product-change audit events.
- [x] Tenant staff role creation, permission assignment, employee role assignment/removal UI and API; staff-management privilege cannot be delegated.
- [x] Platform super-admin authorization with OTP-verified accounts, global permission scope and owner-only payment configuration.
- [x] Platform super-admin tenant provisioning, one-time DNS TXT domain challenges, expiry/attempt controls and verified-host activation.
- [x] Secure tenant-scoped image upload with MIME, size, dimension and storage-key validation.
- [x] Store settings UI, branding controls, fulfillment availability, delivery fee, minimum order and reservation duration.
- [x] Tenant media library browsing with usage counts and reusable media IDs.
- [x] Safe unused media deletion with reference guards, tenant-safe paths and retryable cleanup queue.
- [ ] Full orphan reconciliation and storage lifecycle monitoring.

## Phase 2 — catalog and commerce
- [x] Basic tenant-scoped product list/create/update/deactivate API with permission checks.
- [x] Basic tenant-scoped category list/create/update/deactivate API with parent-cycle and media ownership checks.
- [x] Tenant-local unique product barcodes and product-level low-stock thresholds.
- [x] Advanced tenant-scoped product search with brand, category, price range, availability, sorting and pagination.
- [x] Product variant groups with per-option SKU, barcode, price, stock, availability selector and order snapshots (MySQL-backed API tests).
- [x] Tenant-scoped percentage/fixed coupons with limits, date windows, order snapshots and cancellation/expiry release (MySQL-backed checkout tests).
- [x] Server-side cart quote validation against live prices and available stock.
- [x] Tenant-scoped persistent customer cart across devices, with guest-cart merge and server-side replacement.
- [x] Customer address book API (list/create, tenant and owner scoped).
- [x] Customer address editing and soft deletion with owner/tenant scope and CSRF protection.
- [x] Tenant delivery zones with postal-prefix matching and zone-specific fees/minimums.
- [x] Capacity-controlled pickup scheduling with public availability, transactional slot reservations and order linkage.
- [x] Transactional pending-order creation with cross-warehouse stock reservations and customer order history API.
- [x] CLI worker to release expired stock reservations and cancel unpaid orders.
- [x] ZarinPal server-side verification, order state transitions and customer order detail/cancellation.
- [ ] Live provider acceptance and settlement reconciliation; courier assignment, customer delivery tracking and operational delivery workflow are implemented.

## Phase 3 — immersive storefront
- [x] Merchant scene/media editor API and admin UI.
- [x] Tenant-scoped read API for published photo-tour nodes, hotspots and product-to-shelf links.
- [x] Merchant scene create/update/unpublish and hotspot authoring APIs.
- [x] Merchant scene editor and hotspot authoring workflow with image upload and click-to-place hotspots.
- [x] Category/product/navigation hotspots connect scenes to the live catalogue.
- [x] Procedural WebGL supermarket with three connected aisles, cross-aisle movement, keyboard/touch controls, clickable category shelves, drag-to-look and guided/photo-tour fallback (PHP/JS and integration CI passed; physical-device visual acceptance remains a launch check).
- [x] Build 3D shelf positions from live category slugs and show clickable live catalogue products with product-detail handoff.
- [ ] Real-store media onboarding and responsive image delivery.

## Phase 4 — store operations
- [x] Multi-warehouse stock receipt, transfer and adjustment services with tenant checks, reserved-stock protection and audit trail.
- [x] Per-product low-stock thresholds, warehouse-aware alerts (including zero-stock rows) and purchase-form shortcuts.
- [x] Tenant-scoped supplier directory, purchase orders, partial receiving, inventory posting, audit trail and seller management UI (PHP/JavaScript validation, MySQL schema, tenant isolation and tenant-aware HTTP API integration CI verified).
- [x] Supplier returns with receipt-line eligibility, quantity validation, stock reduction and audit trail.
- [x] Batch/expiry tracking with FEFO allocation, expiry reports and reservation safety.
- [x] Audited expired-stock write-off with tenant-scoped stock reduction and movement history.
- [x] Transactional reservations, expiry/release and paid-order stock commit.
- [x] Customer cancellation for pending-payment orders with transactional stock release.
- [x] Tenant-scoped order queue and guarded fulfilment transitions (accept, picking, packing, pickup/dispatch, delivery).
- [x] Picker/courier assignments, staff task controls, guarded out-for-delivery transition, recipient confirmation and customer/admin delivery timelines (MySQL/API integration CI verified).
- [x] Tenant-scoped operations KPIs: picker queue, packing queue, courier queue, in-transit orders, pickup-ready orders, pickup-handoff backlog, daily deliveries and average route-to-delivery time.
- [x] Dedicated pickup-handoff staff permission and assignment, recipient-name confirmation, staff-only completion and customer notification; manager bypass is blocked (MySQL-backed API integration coverage).
- [ ] In-store POS cash register, cashier shifts, cash collection/reconciliation and counter-sale inventory posting.
- [x] Seller dashboard with 30-day paid revenue, seven-day sales trend and low-stock count.
- [x] Tenant-scoped inventory movement ledger with warehouse/product/type/date filters and signed quantities.
- [x] Tenant-scoped CSV exports for orders and inventory movements, with date/status/warehouse/product filters, formula-injection protection and permission checks.
- [x] Operational notifications.

## Phase 5 — customer growth and integrations
- [x] ZarinPal v4 request/verify adapter, payment-attempt persistence, callback verification, stock commit and duplicate-callback protection.
- [ ] Real-provider sandbox/live acceptance and settlement reconciliation; full-order wallet-credit refunds are implemented, but gateway/bank refunds remain unavailable.
- [x] Kavenegar Verify Lookup OTP adapter with configurable template.
- [ ] Live SMS account/template credentials, delivery reports and alternate-provider adapter.
- [x] Append-only wallet credit ledger, customer balance/history endpoint and idempotent first-paid-order referral rewards (merchant-configured rate).
- [x] Referral attribution on OTP-verified registration.
- [x] Qualifying-order referral reward: only the referred customer's first verified paid order; tenant-scoped attribution, self-referral rejection and idempotent wallet credit. Advanced fraud scoring remains future work.

## Phase 6 — SaaS launch
- [ ] Plans, subscription billing, platform support and finance audit.
- [x] Custom-domain ownership verification and tenant provisioning workflow.
- [ ] Production DNS/HTTPS deployment, backups, monitoring and disaster recovery.
- [x] Source-level security review and automated PHP/JavaScript/MySQL/API/tenant-isolation checks.
- [ ] Independent staging penetration test, load testing, accessibility and physical-device acceptance.
- [ ] Merchant acceptance, operational runbooks and production release.

## Phase 7 — search visibility and SEO
- [x] Tenant-aware SEO context with verified primary-domain canonical URLs.
- [x] Per-tenant robots.txt and XML sitemap generation.
- [x] Crawlable product and category landing pages with Product, Offer, CollectionPage and ItemList structured data.
- [x] Homepage title, description, canonical, social metadata, GroceryStore schema and crawlable category links.
- [x] Merchant-editable store address, postal address, coordinates and opening hours in the GroceryStore structured data, with input validation and CI coverage.
- [x] CI smoke tests for SEO pages, sitemap and robots output.
- [x] Sitemap index with product shards below 50,000 URLs, stable ordering, category pagination, empty-category noindex and canonical legacy redirects.
- [x] BreadcrumbList structured data, social-card metadata, variant-aware product offers and private-source path protection.
- [x] Merchant-managed product/category SEO titles and descriptions in the seller dashboard.
- [x] Merchant-managed unique product slugs, canonical slug URLs, legacy ID-only redirects and permanent redirects from outdated slug variants.
- [x] Permanent tenant-scoped redirect history when a category slug changes, including redirect-chain updates.
- [x] Merchant-configurable Search Console HTML verification and consent-gated GA4 with product/list, add-to-cart, begin-checkout and server-verified purchase events.
- [ ] Verify the production domain in Search Console, submit the tenant sitemap and confirm live GA4 event receipt.
- [ ] Production Core Web Vitals, image delivery, mobile and index coverage review.
