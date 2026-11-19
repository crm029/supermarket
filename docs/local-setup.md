# Local setup (XAMPP)

## Current status
The root `index.php` provides a local storefront preview; live tenant catalogue, customer accounts, checkout, wallet and seller operations require the API and database. The API requires PHP 8.2+ with PDO MySQL, mbstring, cURL, OpenSSL and fileinfo. Customer registration/login uses OTP; checkout creates pending orders and stock reservations; payment completion requires server-side ZarinPal verification. The local `SMS_PROVIDER=log` driver is development-only.

## Prepare database
1. Create a database named `supermarket360` with utf8mb4.
2. Import `database/schema.sql` using phpMyAdmin.
3. For a disposable local demo only, import `database/demo-seed.sql`.
4. Back up the database, then apply every migration below in the exact order shown. This is the same dependency-verified order used by the MySQL integration workflow; do not sort by filename because some later-named migrations must run before earlier-named feature migrations.
5. Apply `database/migrations/2026_11_05_tenant_isolation.sql`.
6. Apply `database/migrations/2026_11_05_auth_sessions_otp.sql`.
7. Apply `database/migrations/2026_11_05_permissions.sql`.
8. Apply `database/migrations/2026_11_06_platform_payment_settings.sql`.
9. Apply `database/migrations/2026_11_19_platform_subscriptions.sql`.
10. Apply `database/migrations/2026_11_19_subscription_plan_changes.sql`.
11. Apply `database/migrations/2026_11_06_order_assignments.sql`.
12. Apply `database/migrations/2026_11_06_pickup_handoff.sql`.
13. Apply `database/migrations/2026_11_06_notifications.sql`.
14. Apply `database/migrations/2026_11_19_notification_retention_index.sql`.
15. Apply `database/migrations/2026_11_06_order_delivery_tracking.sql`.
16. Apply `database/migrations/2026_11_06_order_wallet_refunds.sql`.
17. Apply `database/migrations/2026_11_06_domain_verification_challenge.sql`.
18. Apply `database/migrations/2026_11_05_customer_carts.sql`.
19. Apply `database/migrations/2026_11_05_customer_address_management.sql`.
20. Apply `database/migrations/2026_11_05_barcode_stock_thresholds.sql`.
21. Apply `database/migrations/2026_11_05_media_cleanup_queue.sql`.
22. Apply `database/migrations/2026_11_05_delivery_zones.sql`.
23. Apply `database/migrations/2026_11_05_pickup_slots.sql`.
24. Apply `database/migrations/2026_11_05_coupons.sql`.
25. Apply `database/migrations/2026_11_05_product_variants.sql`.
26. Apply `database/migrations/2026_11_05_supplier_purchase_orders.sql`.
27. Apply `database/migrations/2026_11_05_supplier_returns.sql`.
28. Apply `database/migrations/2026_11_05_stock_batches.sql`.
29. Apply `database/migrations/2026_11_06_pos_cash_register.sql`.
30. Apply `database/migrations/2026_11_06_pos_refunds.sql`.
31. Apply `database/migrations/2026_11_08_pos_return_inspection.sql`.
32. Apply `database/migrations/2026_11_08_pos_return_inspection_status.sql`.
33. Apply `database/migrations/2026_11_05_expired_stock_writeoff.sql`.
34. Apply `database/migrations/2026_11_05_seo_content_fields.sql`.
35. Apply `database/migrations/2026_11_05_product_seo_slugs.sql`.
36. Apply `database/migrations/2026_11_05_seo_url_redirects.sql`.
37. For an existing database, review and repair inconsistent tenant references before applying migrations. Never run schema or demo seed imports over a live production database.
38. The demo seed configures the `localhost` domain for a disposable local demo. For a new tenant, use the platform dashboard and publish its generated DNS TXT challenge before activation.
39. Configure `DB_HOST`, `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD` in the PHP server environment. Set a unique `APP_KEY` of at least 32 characters. Keep all secrets out of Git.
40. For local OTP testing, set `APP_ENV=local` and `SMS_PROVIDER=log`. The one-time code is written only to the PHP error log. This development driver is forbidden in production.
41. Optionally set `MEDIA_BASE_URL` to the HTTPS base URL of your public media storage. Without it, media metadata is returned but image URLs are `null`.
42. Route `/api/v1/*` to `api/v1/index.php` using the web server rewrite configuration, or call the PHP entry point directly while developing.
## Create a local test account
The sample seed contains no customer accounts. In a disposable local database, create one active test user with phpMyAdmin:

```sql
INSERT INTO users (tenant_id, full_name, mobile, referral_code, status, mobile_verified_at)
VALUES (1, 'کاربر آزمایشی', '09123456789', 'LOCALTEST01', 'active', UTC_TIMESTAMP());
```

## Grant a local test user product permissions
The sample database has no staff roles. After creating a test user, assign a tenant role and the product permissions in phpMyAdmin (replace the IDs if your local records differ):

```sql
INSERT INTO roles (tenant_id, role_key, title) VALUES (1, 'store_manager', 'مدیر فروشگاه');
SET @role_id = LAST_INSERT_ID();
SELECT role_scope_id INTO @role_scope FROM roles WHERE id = @role_id;
INSERT INTO user_roles (tenant_id, user_id, role_id) VALUES (1, (SELECT id FROM users WHERE tenant_id = 1 AND mobile = '09123456789'), @role_id);
INSERT INTO role_permissions (role_scope_id, role_id, permission_id)
SELECT @role_scope, @role_id, id FROM permissions
WHERE permission_key IN ('catalog.products.read', 'catalog.products.manage', 'catalog.categories.read', 'catalog.categories.manage');
```

## Authentication endpoints
- `POST /api/v1/auth/request-otp` with JSON `{"mobile":"09123456789"}`
- `POST /api/v1/auth/verify-otp` with JSON `{"mobile":"09123456789","code":"123456"}`
- `GET /api/v1/me` with the session cookie
- `POST /api/v1/auth/logout` with the session cookie and `X-CSRF-Token` header

OTP login works only for an existing user whose status is `active`. The sample seed does not create customer accounts. Add a test account manually in a disposable local database. Production SMS delivery is disabled until a provider adapter and credentials are configured; never enable the log driver on a public server.

## Admin catalog endpoints
- `GET /api/v1/admin/categories` — requires `catalog.categories.read`.
- `POST /api/v1/admin/categories` — requires `catalog.categories.manage`, session cookie and `X-CSRF-Token`.
- `PATCH /api/v1/admin/categories/{id}` — update category; requires the same permission and CSRF token.
- `DELETE /api/v1/admin/categories/{id}` — soft-deactivate category; blocked while it has child categories or active products.

## Admin product endpoints
- `GET /api/v1/admin/products` — requires `catalog.products.read`.
- `POST /api/v1/admin/products` — requires `catalog.products.manage`, session cookie and `X-CSRF-Token`.
- `PATCH /api/v1/admin/products/{id}` — update product; requires the same permission and CSRF token.
- `DELETE /api/v1/admin/products/{id}` — soft-deactivate product; requires the same permission and CSRF token.

## Public catalog and scene endpoints
- `GET /api/v1/products/{id}` — product details and stock availability.
- `GET /api/v1/scenes` — published scenes and hotspots.

## Example catalog requests
- `GET /api/v1/store`
- `GET /api/v1/categories`
- `GET /api/v1/products?q=شیر&page=1`

The API resolves the tenant by the verified request host. A tenant identifier supplied by a browser is intentionally not accepted.

## Platform administration and tenant onboarding

After all migrations, create an active user and complete OTP verification on the dedicated platform operations tenant. Edit `database/bootstrap-platform-admin.sql` with that tenant ID and verified mobile number, then run it once. Open `platform.php` and create a tenant. The dashboard displays a one-time DNS TXT record at `_supermarket360-verification.<domain>`; publish its value in the domain's DNS zone. The challenge expires after 24 hours and can be reissued. The API activates the tenant only after the TXT record matches. The new store owner account remains pending until the tenant is active and the owner completes OTP login.

Do not suspend the platform administrator's home tenant. The API prevents self-suspension.

## Staff tasks and notifications

Employees with `orders.pick` and/or `orders.deliver` permissions open `staff.php` to view only their own active assignments. Customers, staff and store managers can open `notifications.php` to view their own tenant-scoped messages. Notifications are in-app messages; production SMS still requires the configured provider.

## Full wallet-credit refunds

Managers with `orders.refund` can issue a full refund to the customer's internal wallet from the order detail panel. The API requires one verified payment attempt for the full order amount, records an append-only wallet credit, reverses any referral reward associated with the qualifying order and marks the order refunded. This is store credit, not a bank-card or ZarinPal refund. Returned goods are not automatically restocked and must be inspected and received separately.

## Not production-ready yet
Before real customers or money are involved, configure and test the merchant's live SMS and ZarinPal credentials, HTTPS domain, least-privilege database user, media storage, scheduled reservation worker, backups and monitoring. Complete sandbox and low-value live payment acceptance, refund/reconciliation procedures, security review and desktop/mobile acceptance tests. Platform super-admin tenant provisioning, DNS TXT verification, staff task center, notifications, full wallet-credit refunds and full-balance wallet checkout are implemented. Automated bank/gateway refunds remain unavailable until the selected provider's refund API is integrated and accepted. Returned POS stock inspection/restock and optional WebGL free movement are implemented; final merchant hardware and device acceptance remain rollout checks. Never expose database errors or provider secrets to clients.


## Reservation expiry worker

Unpaid checkout reservations expire after 20 minutes. Configure the host's cron or Windows Task Scheduler to run this command every minute:

```sh
php /path/to/supermarket/bin/expire-reservations.php
```

The worker releases reserved quantities and cancels pending-payment orders once all their active reservations have expired. It is safe to run repeatedly; it processes at most 100 orders with expired reservations per invocation. Configure the same database environment variables used by the API. Monitor its stderr/exit code; do not expose this script through the web server.


## ZarinPal setup

Set APP_BASE_URL to the exact public store origin and keep APP_KEY stable and private. The supplied sandbox UUID is prefilled in `.env.example`; copy it into your local `.env` and keep `ZARINPAL_SANDBOX=true` for sandbox testing. The platform owner can also configure the encrypted merchant ID and choose Sandbox or Real from platform.php; tenant store owners and staff cannot read or change these global credentials. The callback URL is generated as /api/v1/payments/zarinpal/callback. Production requires HTTPS and PHP's cURL/OpenSSL extensions.

Never commit a real merchant ID or other payment credentials. Payment attempts retain an encrypted snapshot of the merchant credential and selected gateway mode, so changing the global mode cannot redirect an in-flight sandbox payment to the live verification endpoint (or vice versa). Test success, cancellation, duplicate callbacks, delayed callbacks after reservation expiry, and the exact registered callback domain before enabling live payments.


## Production OTP delivery (Kavenegar)

For production, set SMS_PROVIDER=kavenegar, KAVENEGAR_API_KEY to the private API key from the Kavenegar account, and KAVENEGAR_VERIFY_TEMPLATE to the approved verification-template name. The API key is used only server-side. Enable PHP cURL and verify the approved template sends the numeric OTP in its first token slot. Keep SMS_PROVIDER=log strictly for local development.


## Media storage

Configure MEDIA_STORAGE_PATH as an absolute writable directory outside the public PHP document root, and MEDIA_BASE_URL as the HTTPS public base URL mapped to that directory (for example, a CDN or web-server alias). The upload endpoint accepts JPEG, PNG and WebP images up to 5 MB and 6000×6000 pixels. Do not make the storage directory executable; serve only static image content. On Windows/XAMPP use an absolute drive path for MEDIA_STORAGE_PATH.


## Initial store owner

After applying schema.sql and every migration, edit database/bootstrap-store-owner.sql with the tenant ID, owner's name and Iranian mobile number, then run it once. It creates the tenant's owner role, grants tenant-scoped permissions, creates a main warehouse and initializes store settings. The owner remains pending until mobile OTP verification. Do not use the example phone number unchanged.


## Persistent customer cart

- The storefront keeps a local guest cart and merges it into the signed-in customer's server cart on login.
- Signed-in cart changes are saved to `customer_carts` and `customer_cart_items`; the API is tenant-scoped and CSRF-protected for updates.
- Cart storage never reserves stock or locks prices. The checkout endpoint recalculates both before creating an order.


## Customer wallet and referral rewards
- Customers can pay a pending order in full from their wallet in order history; the API commits stock reservations, writes one debit ledger entry and marks the order paid in one transaction.
- The endpoint is `POST /api/v1/me/orders/{id}/pay-wallet`; it is owner-scoped, CSRF-protected and idempotent. A bank-payment attempt already in progress blocks wallet settlement to prevent double payment.

- `GET /api/v1/me/wallet` returns the signed-in customer's wallet balance, latest 50 ledger entries and referral statuses.
- Wallet balance is calculated from append-only ledger entries; no browser endpoint can directly set a balance.
- Set `REFERRAL_REWARD_BASIS_POINTS` to configure the referrer reward rate (100 = 1%). It defaults to `0`, which disables reward credits until the merchant deliberately configures a rate.
- A reward is posted only after the referred customer's first server-verified paid order. The wallet credit and referral status update share the payment transaction.


## Store branding and checkout settings
The store owner can configure the public store name, tagline, support phone, primary/accent colors, delivery and pickup availability, delivery fee, minimum order and payment reservation duration from `admin.php`. Settings are tenant-scoped and saved through the permission-guarded `/api/v1/admin/settings` endpoint. Delivery fee and minimum order are stored in IRR; the panel accepts toman and converts them. The checkout service revalidates all policies server-side.


## Staff role management
A store owner can create limited tenant roles and assign them to active OTP-verified accounts from the staff panel in `admin.php`. Create the employee's account first, then assign their mobile number a role. The `staff.manage` privilege is intentionally non-delegable in this panel; platform super-admin role management is not included.


## Media library
The seller panel includes a tenant-scoped media library under `admin.php`. It shows the latest 200 images, safe public URL when configured, dimensions, media type and reference count across products, categories and store scenes. Uploads and browsing require the `media.manage` permission. Unused file cleanup is intentionally not enabled yet.


## Barcodes and stock warnings
Product barcodes are optional and tenant-unique. The seller can set a low-stock threshold per product; zero disables warnings and five is the default. Inventory rows compare available units against the threshold, while the dashboard aggregates stock across active warehouses. The migration refuses to proceed if duplicate non-empty barcodes already exist in a tenant.


## Media cleanup worker
Configure `MEDIA_STORAGE_PATH` as an absolute directory outside the public PHP document root. The API queues deletion only for unused tenant media and immediately attempts a path-validated removal. Schedule `php scripts/cleanup-media.php` periodically to retry pending jobs. The worker refuses symlinks, path traversal and storage keys outside the tenant/type upload directory.


## Delivery zones
The seller panel lets the merchant define delivery coverage by province, city and optional postal prefix, with a per-zone delivery fee and minimum order. The most specific matching postal prefix is selected. If no zones are configured, checkout falls back to the store-wide delivery policy; after zones exist, addresses outside active zones cannot be delivered to.


## Pickup scheduling
Create future pickup windows in the seller panel with store-local start/end times and a capacity. Customers see only active future windows with remaining capacity. Once any pickup windows have been configured, a slot is mandatory for new pickup orders. Capacity is checked and reserved transactionally; cancelling or expiring an unpaid order frees the slot.


## Immersive 3D storefront
The optional free-movement mode loads the pinned Three.js 0.160.0 runtime from jsDelivr. It builds three connected procedural aisles with front/back cross-aisle passages and supports WASD/arrow keys, touch controls and drag-to-look. If the runtime cannot load or WebGL is unavailable, the storefront falls back to guided category navigation and the photo tour. Real-store photography and physical-device visual acceptance remain deployment tasks.


## Promotion coupons
Apply the coupon migration after the pickup-slot migration. The seller panel can create percentage or fixed-amount codes, set validity dates and usage limits, and deactivate codes. Customers enter an optional code in checkout; the final discount is calculated and revalidated server-side. A pending order reserves one use until paid, cancelled or expired.


## Product variants
Apply the variants migration after the coupons migration. Create a product as “محصول دارای تنوع” in the seller catalogue, then use “مدیریت تنوع‌ها” to add sellable options. Each option is a separate stock-tracked product with its own SKU, barcode, price, unit and low-stock threshold. Customers select an available option on the product detail screen; parent groups cannot be added directly to the cart.


## صندوق فروش حضوری (POS)

- پس از اجرای migration مربوط به موجودی بچ‌ها، فایل `database/migrations/2026_11_06_pos_cash_register.sql` را اجرا کنید.
- صفحه صندوق در `/pos.php` قرار دارد و از همان نشست ورود پنل فروشنده استفاده می‌کند.
- مدیر فروشگاه از «کارکنان و سطح دسترسی» یک نقش صندوق‌دار بسازد و مجوزهای `pos.read`، `pos.shift.open`، `pos.shift.close` و `pos.sale.create` را به آن بدهد؛ سپس نقش را به حساب فعال کارمند اختصاص دهد.
- APIها زیر مسیر `/api/v1/admin/pos` هستند: `GET warehouses`، `GET shift`، `POST shifts`، `POST shifts/{id}/close`، `GET products?q=...` و `GET/POST sales`.
- مبالغ در API به ریال هستند؛ صفحه صندوق مبالغ را به تومان نمایش می‌دهد و هنگام ارسال تبدیل می‌کند.
- فروش نقدی قیمت را از کاتالوگ سمت سرور می‌گیرد، موجودی آزاد انبار انتخاب‌شده را با بچ‌های FEFO کم می‌کند و حرکت موجودی و رویداد حسابرسی را در همان تراکنش ثبت می‌کند.
- کلید یکتای فروش به اثرانگشت اقلام، وجه دریافتی و توضیح فاکتور متصل است تا تکرار درخواست با محتوای متفاوت رد شود.
- انتظار وجه صندوق برابر موجودی اولیه به‌علاوه فروش خالص نقدی است. در بستن شیفت، وجه شمارش‌شده و مغایرت ثبت می‌شود.
- این نسخه فقط فروش نقدی ساده را پوشش می‌دهد؛ پرداخت ترکیبی، کارت‌خوان بانکی، برگشت فروش POS، چاپگر حرارتی، بارکدخوان سخت‌افزاری و گزارش حسابداری صندوق در مراحل بعدی هستند.


## Media storage reconciliation
Run `php scripts/reconcile-media.php` from the project root to compare generated tenant media files with `media_assets` and pending cleanup records. The command is read-only: it reports registered files, queued cleanup files, orphan files, missing registered files and unsafe filesystem entries as JSON. Exit codes: 0 = clean, 1 = configuration/runtime error, 2 = attention required. Schedule it daily and send non-zero exit codes and JSON output to monitoring; investigate before manually deleting any files. It scans only generated 40-character hexadecimal JPG/PNG/WebP filenames under recognized `tenant-{id}/{media-type}` directories.

## Notification retention
Run `php bin/prune-notifications.php` from CLI to delete only notifications that have already been read and are older than `NOTIFICATION_READ_RETENTION_DAYS` (default 180; allowed range 30–3650). Unread notifications are never removed. Each run deletes at most 20,000 rows in batches of 1,000 and prints a JSON report; `more_may_remain=true` means schedule another run. Configure a daily cron/Task Scheduler job and monitor its exit status and JSON output.

## SaaS plans, subscriptions and finance

Apply `database/migrations/2026_11_19_platform_subscriptions.sql` after the platform payment settings and permission migrations. Then apply `database/migrations/2026_11_19_subscription_plan_changes.sql` to enable store-owner change requests, platform approval and invoice linkage. In `platform.php`, a platform billing administrator can create monthly plans (prices are entered in toman and stored as integer rial), assign 1-, 3- or 12-month subscriptions, issue renewal invoices and record bank-transfer/cash/other receipts. A subscription remains pending until its invoice is fully paid. Partial receipts are append-only, idempotency-key protected and cannot exceed the invoice balance. Plan price and subscription price are snapshots; changing a plan does not rewrite an existing contract. The plan limits JSON supports `products`, `staff`, `media_assets`, `storage_bytes` and `monthly_orders`; caps are checked at product/variant creation and reactivation, first staff assignment, media upload (count and bytes) and both online checkout and POS sale creation. Missing keys are unlimited. Existing/demo tenants without an active or past-due subscription remain unrestricted for backward compatibility. Monthly order quotas include both online checkouts and POS sales; media storage limits count the stored file bytes. Configure a real online SaaS payment gateway separately before automating customer self-service payments.


## SaaS recurring billing worker
Run `php bin/process-subscription-billing.php` daily. It issues one renewal invoice `SUBSCRIPTION_BILLING_LEAD_DAYS` (default 7) before an active subscription ends, then marks it `past_due` only after `SUBSCRIPTION_GRACE_DAYS` (default 7). `SUBSCRIPTION_BILLING_BATCH_SIZE` bounds each pass. `SUBSCRIPTION_REMINDER_LEAD_DAYS` (default 3) sends one in-app reminder before the due date and a deduplicated weekly reminder while an invoice remains overdue. `SUBSCRIPTION_REMINDER_LEAD_DAYS` (default 3) sends one in-app reminder before the due date and a deduplicated weekly reminder while an invoice remains overdue. The worker is idempotent and prints JSON; platform invoice issuance, full payment and this worker send in-app system notifications to active store owners; no SMS/email is sent, money is not collected automatically and stores are not suspended. A paid renewal extends the subscription period when the platform records the invoice receipt. The billing panel can export the 50,000 most recent invoices as a UTF-8 CSV; tenant names and invoice labels are protected against spreadsheet formula injection. The platform usage dashboard aggregates product, staff, media count, media bytes and online/POS monthly order consumption against each tenant plan.

The platform billing panel includes a read-only financial reconciliation view: invoice totals, recorded receipts, outstanding balances and exception detection for overpayments, inconsistent paid states and void invoices with receipts. It is a control report, not a substitute for bank-statement reconciliation.

The CI pipeline also checks the fresh schema, ordered migrations and key tenant/billing/POS SQL tests on MariaDB 10.11. This is a compatibility smoke test, not a substitute for a real XAMPP installation test.

Store owners can open `subscription.php` to view their current plan and request a change. Platform finance administrators review the request, approve or reject it, and an approved change creates an invoice for the next billing period. The new plan is applied only after the invoice is fully paid; the existing plan remains in force while the request is pending or unpaid.

Store owners can also review up to 50 tenant-scoped subscription invoices on `subscription.php`. The page displays live product, staff, media, storage and monthly order usage against plan limits, plus invoice totals, received amounts, remaining balances and payment state. Invoice history is read-only and does not accept or fabricate receipts.

Store owners can schedule cancellation at the end of the current billing period and reverse it before expiry. Access continues until the period end. The daily subscription worker suppresses renewal invoices for scheduled cancellations and marks them cancelled at expiry; new writes are blocked after cancellation.

After a successful POS sale, use the receipt link to open `pos-receipt.php`. The page fetches the sale through the cashier/manager-authorized API and provides 58mm or 80mm browser-print layouts. Actual printer-driver and device acceptance still needs testing on the target hardware.
