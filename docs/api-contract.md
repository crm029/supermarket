# API contract — initial outline

Base path: `/api/v1`. JSON responses use UTF-8 and Persian user-facing messages. Every endpoint is tenant-scoped from the verified host.

## Public storefront
- `GET /store` — store profile, branding, opening hours and fulfillment options.
- `GET /categories` — active category tree.
- `GET /products?category=&q=&page=` — searchable, paginated catalog.
- `GET /products/{id}` — tenant-scoped product details, active stock availability and optional public image metadata.
- `GET /scenes` — published photo-tour nodes and hotspots, with tenant-safe media URLs when `MEDIA_BASE_URL` is configured.
- `POST /cart/quote` — server-side price and availability recalculation.

## Customer
- `POST /auth/register` — create a pending customer account; optional referral code; requires SMS delivery to be configured.\n- `POST /auth/request-otp` — request a rate-limited SMS code for an active or pending account.
- `POST /auth/verify-otp` — verify code and issue a database-backed session cookie plus CSRF token.
- `GET /me` — authenticated customer profile.\n- `GET /auth/csrf` — rotate and return the current session's CSRF token.
- `POST /auth/logout` — revoke the current session; requires `X-CSRF-Token`.
- `GET /me/orders` — tenant-scoped order history.
- `GET /me/addresses` — address book.
- `POST /checkout` — create pending order, stock reservations and payment attempt.
- `GET /payments/{id}` — payment state, never a substitute for provider verification.

## Store operations
- `GET /admin/categories` — tenant-scoped category list; requires `catalog.categories.read`.
- `POST /admin/categories` — create a category; requires `catalog.categories.manage`, session and CSRF token.
- `PATCH /admin/categories/{id}` — update a category; requires `catalog.categories.manage`, session and CSRF token.
- `DELETE /admin/categories/{id}` — soft-deactivate a category; requires `catalog.categories.manage`, session and CSRF token.
- `GET /admin/products` — tenant-scoped product list/search; requires `catalog.products.read`.
- `POST /admin/products` — create a product; requires `catalog.products.manage`, session and CSRF token.
- `PATCH /admin/products/{id}` — update a product; requires `catalog.products.manage`, session and CSRF token.
- `DELETE /admin/products/{id}` — soft-deactivate a product; requires `catalog.products.manage`, session and CSRF token.
- `GET /admin/dashboard`
- `POST /admin/media` — tenant media upload with MIME/size validation.
- `POST /admin/warehouses`
- `POST /admin/stock/receipts`, `POST /admin/stock/adjustments`, `POST /admin/stock/transfers`
- `POST /admin/orders/{id}/transition` — policy-checked workflow transition.
- `GET /admin/reports/sales`, `GET /admin/reports/inventory`

## Platform operations
- `POST /platform/tenants` — provision tenant and initial manager.
- `PATCH /platform/tenants/{id}/status`
- `POST /platform/domains/verify`
- `GET /platform/audit`

## Security contract
- Mutating browser requests require CSRF protection and authenticated sessions.
- OTP, login, search and payment endpoints are rate-limited.
- Payment webhooks are signature/authority verified and idempotent.
- Admin endpoints require explicit permissions; role names alone are not authorization.
- Never return provider secrets, internal stack traces or another tenant's data.

## Authentication implementation notes
- OTP codes are stored only as keyed hashes, expire after five minutes, and allow at most five verification attempts.
- Request and verification limits are tenant-scoped and bucketed by mobile/IP.
- Session tokens are random opaque values; only SHA-256 hashes are stored. Cookies are HttpOnly and SameSite=Lax, and Secure in HTTPS production.
- The Kavenegar Verify Lookup adapter is available when `SMS_PROVIDER=kavenegar`, the approved template and the private API key are configured. The included `SMS_PROVIDER=log` adapter is development-only and forbidden in production.

## Authentication implementation notes
- OTP codes are stored only as keyed hashes, expire after five minutes, and allow at most five verification attempts.
- Request and verification limits are tenant-scoped and bucketed by mobile/IP.
- Session tokens are random opaque values; only SHA-256 hashes are stored. Cookies are HttpOnly and SameSite=Lax, and Secure in HTTPS production.
- The included `SMS_PROVIDER=log` adapter is development-only. Production OTP delivery remains disabled until a real provider adapter and credentials are configured.

## Permission model
- Permission keys are stored in a global catalog and are explicitly scoped as `tenant` or `platform`.
- Tenant sessions can only satisfy tenant-scoped permissions; platform permissions are excluded from tenant authorization checks.
- Role grants are constrained by the role's scope key, and admin product operations are audited.
- Role/permission management UI and platform authorization endpoints are not implemented yet.

## Public media
- Public image URLs are built only from the configured `MEDIA_BASE_URL` and a tenant-owned media record. Storage keys are never returned to clients.
- Production media URLs must use HTTPS. Unsafe path segments are rejected.
- Authenticated tenant-scoped media upload and scene/hotspot authoring are implemented. Configure storage outside the public PHP document root and serve only validated static image files.


## Commerce endpoints (implemented)
- `POST /cart/quote` — accepts `items: [{product_id, quantity}]`; recalculates current prices and active-warehouse availability server-side. Client prices are ignored.
- `GET /me/addresses` — authenticated customer's own address book.
- `POST /me/addresses` — create a validated address; requires session and CSRF token.
- `POST /checkout` — authenticated, CSRF-protected checkout. Accepts line items, `fulfillment` (`delivery` or `pickup`), optional delivery `address_id`, and `customer_note`. Prices are recalculated inside a transaction, stock is reserved across active warehouses, and a pending-payment order is created.
- `GET /me/orders` — authenticated customer's latest 100 orders.

Checkout intentionally does not mark an order paid. Reservations expire after 20 minutes; run `bin/expire-reservations.php` from cron/Task Scheduler every minute. A verified payment provider is still required before production payment/order completion. Delivery is currently free by the original store brief; delivery zones and configurable fees remain future work.


## ZarinPal payment adapter
- POST /payments/zarinpal/request — authenticated and CSRF-protected; creates a payment request for an owned pending-payment order, returns a gateway redirect URL, and reuses an existing active redirect where possible.
- GET /payments/zarinpal/callback — provider callback; server-side verification is required before an order is marked paid. The amount sent to the provider is the stored Rial total, never a browser-supplied amount.
- Configure ZARINPAL_MERCHANT_ID, ZARINPAL_SANDBOX, and APP_BASE_URL. The callback origin must be registered with the payment provider; production requires HTTPS. PHP cURL must be enabled.
- Payment verification consumes active stock reservations and records stock movements. If payment arrives after reservations have expired, the payment is recorded and an audit event flags the order for manual fulfilment review.


## Order operations
- GET /admin/orders — tenant-scoped order queue; requires orders.read.
- POST /admin/orders/{id}/transition — permission-checked fulfilment workflow; requires orders.manage and CSRF.
- GET /me/orders/{id} — full order detail for its owning customer only.
- POST /me/orders/{id}/cancel — cancel a pending-payment order and release active stock reservations; requires CSRF.


## Inventory endpoints
- GET /admin/warehouses — list active and inactive tenant warehouses; requires inventory.read.
- POST /admin/warehouses — create a warehouse; requires inventory.manage and CSRF.
- GET /admin/stock — tenant-scoped stock levels and available quantities; requires inventory.read.
- POST /admin/stock/receipts — add stock and write a receipt movement; requires inventory.manage and CSRF.
- POST /admin/stock/adjustments — signed stock correction with mandatory reason and reserved-stock protection.
- POST /admin/stock/transfers — atomic transfer between two active warehouses; available stock only.


## Media and scene authoring
- POST /admin/media — authenticated, CSRF-protected image upload; requires media.manage.
- GET /admin/scenes, POST /admin/scenes, PATCH /admin/scenes/{id}, DELETE /admin/scenes/{id} — tenant-scoped scene management; DELETE unpublishes rather than deleting.
- POST /admin/scenes/{id}/hotspots — create a product, category or navigation hotspot with normalized x/y coordinates.
- DELETE /admin/hotspots/{id} — remove a hotspot.


## Reporting
- GET /admin/reports/overview — tenant-scoped 30-day revenue/order totals, pending-payment count, low-stock count and seven-day sales series; requires reports.read.


## Customer wallet
- `GET /me/wallet` — authenticated tenant-scoped wallet summary with balance, the latest 50 append-only ledger entries and referral reward status. No client-facing balance mutation endpoint is exposed.
- Wallet balance is derived from ledger entries; credits and reversals add value, debits subtract value. Ledger entries are never edited or deleted by application routes.
- Referral reward is credited only after the referred customer's first server-verified paid order. Configure `REFERRAL_REWARD_BASIS_POINTS` (100 = 1%); zero disables rewards by default. Credits use a unique order idempotency key and are committed in the same database transaction as payment finalization.


## Persistent customer cart
- `GET /me/cart` — return the signed-in customer's tenant-scoped cart with current product names, prices and available stock.
- `PUT /me/cart` — replace the customer's cart with up to 50 active tenant products and quantities from 1 to 99; requires session and CSRF token. An empty `items` array clears the cart.
- Cart contents are not a price or stock reservation. Checkout always recalculates prices and reserves stock transactionally.


## Customer address book
- `GET /me/addresses` lists the authenticated customer's active addresses for the verified tenant.
- `POST /me/addresses` creates an address.
- `PATCH /me/addresses/{id}` updates an address owned by the authenticated customer; partial fields are accepted and the complete merged address is validated.
- `DELETE /me/addresses/{id}` retires an address from the active list without breaking historical order references.
- Address writes require the session CSRF token. Checkout accepts only an active address owned by the same customer and tenant.


## Store settings
- `GET /admin/settings` returns the tenant's validated profile and checkout settings; requires `store.settings.manage`.
- `PUT /admin/settings` replaces profile and checkout settings; requires the same permission and CSRF token.
- Profile fields: name, tagline, support phone, primary color and accent color.
- Checkout fields: delivery/pickup availability, delivery fee in IRR, minimum order in IRR and reservation duration (5–120 minutes).
- Public `GET /store` exposes the safe profile and checkout settings and fulfillment availability. Checkout independently enforces enabled fulfillment, minimum order, delivery fee and reservation expiry on the server.


## Staff and tenant roles
- `GET /admin/staff` lists tenant roles, the tenant permission catalog (excluding the non-delegable `staff.manage` permission), and staff users; requires `staff.manage`.
- `POST /admin/roles` creates a tenant role and grants selected tenant-scoped permissions; requires `staff.manage` and CSRF. `store_owner` and `staff.manage` cannot be delegated.
- `POST /admin/staff/assign` assigns a tenant role to an already active, OTP-verified user by mobile number; requires `staff.manage` and CSRF.
- `DELETE /admin/staff/{user_id}/roles/{role_id}` removes a tenant role assignment; requires `staff.manage` and CSRF. A user cannot remove their own role through this route, and the store-owner role is protected.


## Media library
- `GET /admin/media` lists up to 200 newest media assets in the verified tenant, including safe public URL, dimensions, media type, and usage count across product/category/scene references. Requires `media.manage`.
- `POST /admin/media` remains the CSRF-protected upload endpoint. Unused-file deletion and orphan reconciliation are not implemented yet.


## Product barcodes and low-stock thresholds
- Product barcodes are optional but unique within each tenant; the same barcode may be used by different tenants.
- `low_stock_threshold` is an integer from 0 to 1,000,000. A value of `0` disables the warning; the default is `5`.
- The seller inventory view shows per-warehouse available quantity against the product threshold. The dashboard low-stock count aggregates available stock across active warehouses and counts products at or below their threshold.
- Existing duplicate non-empty barcodes must be resolved before applying `2026_11_05_barcode_stock_thresholds.sql`; blank legacy barcodes are normalized to NULL by the migration.


## Advanced public product search
`GET /products` supports `q` (title/SKU/barcode), `brand` (partial match), `category` (tenant category ID), `min_price` and `max_price` (IRR), `in_stock=1|0`, `sort=title_asc|price_asc|price_desc|newest`, `page`, and `per_page` (1–48). The response includes available quantity and `in_stock`; all filters are tenant-scoped and numeric ranges are validated server-side.


## Safe media cleanup
- `DELETE /admin/media/{id}` requires `media.manage` and CSRF. The API refuses deletion while a product, category or store scene references the asset.
- Unused media records are removed and a tenant-scoped cleanup job is queued. The worker only deletes files whose storage key matches the upload naming convention and whose resolved path remains inside the configured `MEDIA_STORAGE_PATH` tenant/type directory; symlinks and unsafe paths are refused.
- Run `php scripts/cleanup-media.php` from cron/scheduler to retry pending file removals. The API also attempts immediate cleanup after queuing.


## Delivery zones
- `GET /admin/delivery-zones` lists tenant zones; `POST` creates; `PATCH /admin/delivery-zones/{id}` updates; `DELETE` deactivates. All routes require `store.settings.manage`, and mutations require CSRF.
- A zone matches an address by exact province/city plus an optional postal-code prefix. The longest matching prefix wins; an empty prefix covers the whole city.
- If no zones have ever been configured, checkout uses the store-wide delivery fee/minimum. Once any zone exists, delivery is allowed only for an active matching zone. Zone fee and minimum apply, with the store-wide minimum retained as a floor.
- The selected zone ID is stored on the order for traceability; delivery fee and total are snapshotted at checkout.


## Scheduled pickup
- `GET /pickup-slots` returns whether the tenant has configured slots and up to 100 active future slots with remaining capacity, using the store timezone.
- `GET /admin/pickup-slots` lists slots; `POST` creates a future slot; `DELETE /admin/pickup-slots/{id}` deactivates one. Seller routes require `store.settings.manage`; mutations require CSRF.
- If no pickup slots have ever been configured, immediate pickup remains available. Once slots exist, checkout requires a selected active slot.
- Checkout locks the selected slot row and checks capacity against paid/fulfilment orders plus pending-payment orders with unexpired stock reservations inside the same transaction. The selected slot is stored on the order. Cancellation/expiry releases capacity because cancelled orders and expired reservations no longer count.


## Promotion coupons
- `GET /admin/coupons` lists tenant coupons and active-use counts; `POST /admin/coupons` creates; `PATCH /admin/coupons/{id}` updates; `DELETE /admin/coupons/{id}` deactivates. Seller routes require `store.settings.manage`; mutations require CSRF.
- Coupon codes are normalized to uppercase and unique per tenant. Percentage discounts use whole percentage points (1–100); fixed discounts and all minimum/cap amounts are stored in rial.
- Checkout accepts optional `coupon_code`. The server locks the coupon row, checks active dates, minimum basket value, global usage and per-customer usage, then snapshots `coupon_id` and `discount_rial` on the order. Percentage caps are supported; discounts apply to merchandise subtotal, not delivery fees.
- Pending-payment orders reserve a coupon use while their stock reservation is active. Paid/fulfilled/refunded orders consume a use. Customer cancellation or reservation expiry releases the use; historical redemption rows remain for audit.


## Product variants
- Products may be simple items or variant parents. A variant parent is a non-sellable catalogue group; each sellable variant is a child product with its own SKU, barcode, unit, price, low-stock threshold and stock balance.
- `GET /admin/products/{id}/variants` lists a parent’s variants; `POST` creates one; `PATCH /admin/products/{id}/variants/{variantId}` updates; `DELETE` deactivates. All variant administration requires `catalog.products.manage` and CSRF on mutations.
- Public product lists show parent groups with the minimum active-variant price and aggregated available stock. Product detail returns active variants and each variant’s availability. Cart/checkout must use a sellable variant ID, never the parent ID.
- Inventory receipts and movements operate on the child variant product ID. Order-line title snapshots include the parent title and selected variant label. Deactivating a parent makes all its variants unavailable for sale without deleting their stock or history.

## Supplier returns
- GET /admin/supplier-returns — tenant-scoped return history; requires inventory.read.
- GET /admin/supplier-returns/eligible-lines?supplier_id=&warehouse_id= — lists unreturned quantities from actual receipt lines for the selected supplier and warehouse; requires inventory.read.
- POST /admin/supplier-returns — posts a supplier return; requires inventory.manage, authenticated session and CSRF.
- A return line references an actual purchase receipt line (receipt_item_id), and the server derives its supplier, warehouse, product and unit cost. The selected warehouse must match the original receipt.
- Cumulative returns cannot exceed the quantity originally received on that receipt line. The server locks receipt and stock rows, refuses to return reserved/unavailable stock, reduces on-hand quantity and writes a signed supplier_return stock movement plus an audit event in one transaction.
- The seller panel provides a return form populated only from eligible receipt lines, plus return history.
- Return records and stock movements are append-only through the application API; no edit/delete route is exposed.

