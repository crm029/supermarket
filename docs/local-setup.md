# Local setup (XAMPP)

## Current status
The root `index.php` is a visual demo and can be opened without a database. The API requires PHP 8.1+ with PDO MySQL and MySQL/MariaDB. The catalog routes are read-only; customer OTP login is available for existing active users. Registration, checkout and payments are not implemented.

## Prepare database
1. Create a database named `supermarket360` with utf8mb4.
2. Import `database/schema.sql` using phpMyAdmin.
3. For a disposable local demo only, import `database/demo-seed.sql`.
4. Back up the database, then apply `database/migrations/2026_11_05_tenant_isolation.sql`. It adds tenant-aware composite foreign keys and scope triggers; it intentionally fails if legacy records contain cross-tenant references.
5. Apply `database/migrations/2026_11_05_auth_sessions_otp.sql` to create OTP challenges, rate-limit buckets and database-backed sessions.
6. For an existing database, review and repair inconsistent tenant references before applying migrations. Never run schema or demo seed imports over a live production database.
7. Create one tenant and one verified domain matching the Host header, then set the tenant status to `active`. The demo seed configures `localhost`.
8. Configure `DB_HOST`, `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD` in the PHP server environment. Set a unique `APP_KEY` of at least 32 characters. Keep all secrets out of Git.
9. For local OTP testing, set `APP_ENV=local` and `SMS_PROVIDER=log`. The one-time code is written only to the PHP error log. This development driver is forbidden in production.
10. Route `/api/v1/*` to `api/v1/index.php` using the web server rewrite configuration, or call the PHP entry point directly while developing.

## Authentication endpoints
- `POST /api/v1/auth/request-otp` with JSON `{"mobile":"09123456789"}`
- `POST /api/v1/auth/verify-otp` with JSON `{"mobile":"09123456789","code":"123456"}`
- `GET /api/v1/me` with the session cookie
- `POST /api/v1/auth/logout` with the session cookie and `X-CSRF-Token` header

OTP login works only for an existing user whose status is `active`. The sample seed does not create customer accounts. Add a test account manually in a disposable local database. Production SMS delivery is disabled until a provider adapter and credentials are configured; never enable the log driver on a public server.

## Example catalog requests
- `GET /api/v1/store`
- `GET /api/v1/categories`
- `GET /api/v1/products?q=شیر&page=1`

The API resolves the tenant by the verified request host. A tenant identifier supplied by a browser is intentionally not accepted.

## Not production-ready yet
Before real customers or money are involved, implement a production SMS adapter, registration and account recovery, role-based authorization policies, CSRF on all write routes, transactional checkout, stock reservation, payment-provider verification, broader automated tests, HTTPS, backups and monitoring. Never expose database errors to clients.
