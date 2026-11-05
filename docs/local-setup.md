# Local setup (XAMPP)

## Current status
The root `index.php` provides a local storefront preview; live tenant catalogue, customer accounts, checkout, wallet and seller operations require the API and database. The API requires PHP 8.2+ with PDO MySQL, mbstring, cURL and fileinfo. Customer registration/login uses OTP; checkout creates pending orders and stock reservations; payment completion requires server-side ZarinPal verification. The local `SMS_PROVIDER=log` driver is development-only.

## Prepare database
1. Create a database named `supermarket360` with utf8mb4.
2. Import `database/schema.sql` using phpMyAdmin.
3. For a disposable local demo only, import `database/demo-seed.sql`.
4. Back up the database, then apply `database/migrations/2026_11_05_tenant_isolation.sql`. It adds tenant-aware composite foreign keys and scope triggers; it intentionally fails if legacy records contain cross-tenant references.
5. Apply `database/migrations/2026_11_05_auth_sessions_otp.sql` to create OTP challenges, rate-limit buckets and database-backed sessions.
6. Apply `database/migrations/2026_11_05_permissions.sql` to create the permission catalog and role grants.
7. Apply `database/migrations/2026_11_05_customer_carts.sql` to create tenant-isolated persistent customer carts.
8. Apply `database/migrations/2026_11_05_customer_address_management.sql` to enable safe address retirement while preserving order history.
9. For an existing database, review and repair inconsistent tenant references before applying migrations. Never run schema or demo seed imports over a live production database.
10. Create one tenant and one verified domain matching the Host header, then set the tenant status to `active`. The demo seed configures `localhost`.
11. Configure `DB_HOST`, `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD` in the PHP server environment. Set a unique `APP_KEY` of at least 32 characters. Keep all secrets out of Git.
12. For local OTP testing, set `APP_ENV=local` and `SMS_PROVIDER=log`. The one-time code is written only to the PHP error log. This development driver is forbidden in production.
13. Optionally set `MEDIA_BASE_URL` to the HTTPS base URL of your public media storage. Without it, media metadata is returned but image URLs are `null`.
14. Route `/api/v1/*` to `api/v1/index.php` using the web server rewrite configuration, or call the PHP entry point directly while developing.

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

## Not production-ready yet
Before real customers or money are involved, configure and test the merchant's live SMS and ZarinPal credentials, HTTPS domain, least-privilege database user, media storage, scheduled reservation worker, backups and monitoring. Complete sandbox and low-value live payment acceptance, refund/reconciliation procedures, security review and desktop/mobile acceptance tests. Platform super-admin and role-management interfaces, wallet spending/refunds and optional WebGL free movement remain unfinished. Never expose database errors or provider secrets to clients.


## Reservation expiry worker

Unpaid checkout reservations expire after 20 minutes. Configure the host's cron or Windows Task Scheduler to run this command every minute:

```sh
php /path/to/supermarket/bin/expire-reservations.php
```

The worker releases reserved quantities and cancels pending-payment orders once all their active reservations have expired. It is safe to run repeatedly; it processes at most 100 orders with expired reservations per invocation. Configure the same database environment variables used by the API. Monitor its stderr/exit code; do not expose this script through the web server.


## ZarinPal setup

Set ZARINPAL_MERCHANT_ID to the merchant ID issued for the store, ZARINPAL_SANDBOX=true for sandbox testing, and APP_BASE_URL to the exact public store origin. The callback URL is generated as /api/v1/payments/zarinpal/callback; its domain must match the domain registered for the merchant terminal. Production requires HTTPS. Enable PHP's cURL extension.

Never commit a real merchant ID or other payment credentials. Test success, cancellation, duplicate callbacks, delayed callbacks after reservation expiry, and the exact registered callback domain before enabling live payments.


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

- `GET /api/v1/me/wallet` returns the signed-in customer's wallet balance, latest 50 ledger entries and referral statuses.
- Wallet balance is calculated from append-only ledger entries; no browser endpoint can directly set a balance.
- Set `REFERRAL_REWARD_BASIS_POINTS` to configure the referrer reward rate (100 = 1%). It defaults to `0`, which disables reward credits until the merchant deliberately configures a rate.
- A reward is posted only after the referred customer's first server-verified paid order. The wallet credit and referral status update share the payment transaction.


## Store branding and checkout settings
The store owner can configure the public store name, tagline, support phone, primary/accent colors, delivery and pickup availability, delivery fee, minimum order and payment reservation duration from `admin.php`. Settings are tenant-scoped and saved through the permission-guarded `/api/v1/admin/settings` endpoint. Delivery fee and minimum order are stored in IRR; the panel accepts toman and converts them. The checkout service revalidates all policies server-side.
