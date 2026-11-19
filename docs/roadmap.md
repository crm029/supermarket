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
- [x] Validate fresh schema, ordered migrations and key tenant/billing/POS SQL suites on MariaDB 10.11 in CI.
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
- [x] Secure tenant-scoped image upload with MIME, size, dimension and storage-key validation plus symlink-resistant tenant/type directory resolution and automated negative tests.
- [x] Store settings UI, branding controls, fulfillment availability, delivery fee, minimum order and reservation duration.
- [x] Tenant media library browsing with usage counts and reusable media IDs.
- [x] Safe unused media deletion with reference guards, tenant-safe paths and retryable cleanup queue.
- [x] Read-only media-storage reconciliation CLI reports unregistered files, missing registered files, queued cleanup files and unsafe filesystem entries; MySQL CI verifies each classification.
- [x] Bounded retention for completed media-cleanup queue records via `bin/prune-media-cleanup.php` (configurable 30–3650 days, 500-row batches and a 20-batch run cap; MySQL CI verifies old completed rows are pruned while recent and pending rows remain).
- [ ] Automated alert delivery and explicit operator-approved orphan cleanup; reconciliation remains read-only and never deletes or queues orphan files automatically.

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
- [x] POS manager reports: date-filtered in-person sales, shift/cash variance summaries, cashier performance, top in-person items and online-vs-in-person comparison (permission-guarded API and seller-admin report page; automated API permission checks).
- [x] POS sale receipt preview and browser print layout for 58mm/80mm paper, with cashier identity, item details, totals and refund history (sale-detail permission checks and JavaScript CI validation).
- [x] POS software release: cashier page/API, shift lifecycle, FEFO cash sales, idempotency, cash reconciliation, manager reporting, audited cash refunds, returned-item inspection/restock-or-reject, and 58/80mm browser thermal receipts. MySQL/API smoke tests cover the complete cash-only workflow, including closing-shift reconciliation and blocking sales after closure.
> Hardware-independent POS policy: the current release uses browser-native printing (`window.print`) and the operating system's installed printer drivers, with selectable 58mm/80mm thermal receipt layouts. This avoids binding the app to one printer brand or connection type; USB, network and Bluetooth printers are usable when installed and exposed to the device/browser by the OS. Barcode scanners that emulate keyboard input work through the focused barcode/search field. No physical device is required to install, configure or use the software-only release. Optional post-deployment acceptance against a store's actual printer, scanner and acquiring bank terminal is an operational rollout check, not a software-completion blocker. Direct ESC/POS, vendor SDK, bank-terminal and mixed-tender adapters remain future integrations when a merchant/provider is selected.
- [x] Seller dashboard with 30-day paid revenue, seven-day sales trend and low-stock count.
- [x] Tenant-scoped inventory movement ledger with warehouse/product/type/date filters and signed quantities.
- [x] Read-only inventory reconciliation compares aggregate stock and reservations with batch totals, reports missing/orphan batch groups and is covered by tenant-aware API smoke tests.
- [x] Concurrent manual stock receipts are integration-tested: both parallel requests must succeed without lost updates, and stock-level totals must equal batch totals.
- [x] Concurrent same-direction stock transfers are integration-tested for source serialization, destination increments, and per-warehouse batch reconciliation.
- [x] Opposite-direction concurrent transfers are covered to enforce deterministic balance-row lock ordering and prevent avoidable transfer deadlocks.
- [x] Checkout locks multi-warehouse stock rows in the same stable warehouse order as transfers while preserving stock-availability allocation priority.
- [x] Concurrent negative stock adjustments are integration-tested for reserved-stock safety and exact batch/aggregate synchronization.
- [x] FEFO allocation is integration-tested against distinct near- and later-expiry lots through a real customer checkout reservation.
- [x] Expired lots are excluded from normal stock consumption and transfers; the supplier-return path explicitly opts in to consume the original receipt lot, and normal exclusion is integration-tested.
- [x] Expired-stock write-off integration test verifies batch and warehouse balance reconciliation, audit movement creation, and duplicate-write-off rejection.
- [x] Supplier-return integration test follows a real purchase receipt and confirms an expired original lot can be returned only against its own receipt line with stock and movement-ledger reconciliation.
- [x] Tenant-scoped CSV exports for orders and inventory movements, with date/status/warehouse/product filters, formula-injection protection and permission checks.
- [x] Operational notifications.
- [x] Bounded notification-retention CLI with configurable 30–3650-day policy, unread preservation, batch limits, retention index and cross-tenant MySQL integration coverage.

## Phase 5 — customer growth and integrations
- [x] ZarinPal v4 request/verify adapter, payment-attempt persistence, callback verification, stock commit and duplicate-callback protection.
- [ ] Real-provider sandbox/live acceptance and settlement reconciliation; full-order wallet-credit refunds are implemented, but gateway/bank refunds remain unavailable.
- [x] Kavenegar Verify Lookup OTP adapter with configurable template.
- [ ] Live SMS account/template credentials, delivery reports and alternate-provider adapter.
- [x] Append-only wallet credit ledger, customer balance/history endpoint and idempotent first-paid-order referral rewards (merchant-configured rate).
- [x] Referral attribution on OTP-verified registration.
- [x] Qualifying-order referral reward: only the referred customer's first verified paid order; tenant-scoped attribution, self-referral rejection and idempotent wallet credit. Advanced fraud scoring remains future work.

## Phase 6 — SaaS launch
- [x] Platform plan catalog, configurable product/staff/media/monthly-order/storage-byte quotas, tenant subscriptions, 1/3/12-month invoices, scheduled renewal invoices, grace-period past-due state, owner in-app notices, partial/manual receipt ledger, finance CSV export, idempotency, tenant-safe foreign keys and platform audit events (MySQL/API CI coverage).
- [x] Platform-wide tenant usage dashboard for active products, staff, media count, storage bytes and combined online/POS monthly orders, with plan-cap progress and platform permission checks (MySQL/API CI coverage).
- [x] Idempotent in-app invoice reminders before due date and weekly while overdue, with bounded daily processing and MySQL worker integration tests.
- [x] Platform finance reconciliation totals and anomaly detection for overpayments, paid/open status mismatches, missing/unexpected paid timestamps and void invoices with receipts (platform permission checks and API CI coverage).
- [x] Store-owner self-service plan-change requests, platform approval/rejection, next-period invoice issuance and apply-only-after-full-payment workflow (MySQL/API CI coverage).
- [x] Store-owner tenant-scoped invoice history with paid, partial and outstanding balance calculations (API integration CI coverage).
- [x] Store-owner live quota usage dashboard for products, staff, media files, storage bytes and monthly online/POS orders, compared with the active plan limits (tenant-aware API integration CI coverage).
- [x] Owner-requested subscription cancellation at period end, reversible before expiry, renewal suppression, end-date execution and write blocking after cancellation (API/worker integration tests).
- [ ] Automated recurring SaaS payment collection through a separate provider and verified webhook.
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
