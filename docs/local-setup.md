# Local setup (XAMPP)

## Current status
The root `index.php` is a visual demo and can be opened without a database. The API requires PHP 8.1+ with PDO MySQL and MySQL/MariaDB. Public catalog routes are read-only; protected admin product management supports list, create, update and soft-deactivate. Customer OTP login is available for existing active users. Registration, checkout and payments are not implemented.

## Prepare database
1. Create a database named `supermarket360` with utf8mb4.
2. Import `database/schema.sql` using phpMyAdmin.
3. For a disposable local demo only, import `database/demo-seed.sql`.
4. Back up the database, then apply `database/migrations/2026_11_05_tenant_isolation.sql`. It adds tenant-aware composite foreign keys and scope triggers; it intentionally fails if legacy records contain cross-tenant references.
5. Apply `database/migrations/2026_11_05_auth_sessions_otp.sql` to create OTP challenges, rate-limit buckets and database-backed sessions.
6. Apply `database/migrations/2026_11_05_permissions.sql` to create the permission catalog and role grants.
7. For an existing database, review and repair inconsistent tenant references before applying migrations. Never run schema or demo seed imports over a live production database.
8. Create one tenant and one verified domain matching the Host header, then set the tenant status to `active`. The demo seed configures `localhost`.
9. Configure `DB_HOST`, `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD` in the PHP server environment. Set a unique `APP_KEY` of at least 32 characters. Keep all secrets out of Git.
10. For local OTP testing, set `APP_ENV=local` and `SMS_PROVIDER=log`. The one-time code is written only to the PHP error log. This development driver is forbidden in production.
11. Optionally set `MEDIA_BASE_URL` to the HTTPS base URL of your public media storage. Without it, media metadata is returned but image URLs are `null`.\n12. Route `/api/v1/*` to `api/v1/index.php` using the web server rewrite configuration, or call the PHP entry point directly while developing.

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

## Admin catalog endpoints\n- `GET /api/v1/admin/categories` — requires `catalog.categories.read`.\n- `POST /api/v1/admin/categories` — requires `catalog.categories.manage`, session cookie and `X-CSRF-Token`.\n- `PATCH /api/v1/admin/categories/{id}` — update category; requires the same permission and CSRF token.\n- `DELETE /api/v1/admin/categories/{id}` — soft-deactivate category; blocked while it has child categories or active products.\n\n## Admin product endpoints
- `GET /api/v1/admin/products` — requires `catalog.products.read`.
- `POST /api/v1/admin/products` — requires `catalog.products.manage`, session cookie and `X-CSRF-Token`.
- `PATCH /api/v1/admin/products/{id}` — update product; requires the same permission and CSRF token.
- `DELETE /api/v1/admin/products/{id}` — soft-deactivate product; requires the same permission and CSRF token.

## Public catalog and scene endpoints\n- `GET /api/v1/products/{id}` — product details and stock availability.\n- `GET /api/v1/scenes` — published scenes and hotspots.\n\n## Example catalog requests
- `GET /api/v1/store`
- `GET /api/v1/categories`
- `GET /api/v1/products?q=شیر&page=1`

The API resolves the tenant by the verified request host. A tenant identifier supplied by a browser is intentionally not accepted.

## Not production-ready yet
Before real customers or money are involved, implement a production SMS adapter, registration and account recovery, role-based authorization policies, CSRF on all write routes, transactional checkout, stock reservation, payment-provider verification, broader automated tests, HTTPS, backups and monitoring. Never expose database errors to clients.
