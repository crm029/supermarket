# Local setup (XAMPP)

## Current status
The root `index.php` is the visual demo and can be opened without a database. The API foundation is separate and requires PHP 8.1+ with PDO MySQL and a MySQL/MariaDB database. The API is read-only at this stage; it does not accept orders, create accounts or process payments.

## Prepare database
1. Create a database named `supermarket360` with utf8mb4.
2. Import `database/schema.sql` using phpMyAdmin.
3. For a disposable local demo only, import `database/demo-seed.sql`.
4. Back up the database, then apply `database/migrations/2026_11_05_tenant_isolation.sql` using phpMyAdmin or the MySQL client. This migration adds tenant-aware composite foreign keys and scope triggers; it intentionally fails if legacy records contain cross-tenant references.
5. Apply `database/migrations/2026_11_05_auth_sessions_otp.sql` to create OTP challenges, rate-limit buckets and database-backed sessions.
5. For an existing database, review and repair inconsistent tenant references before applying the migration. Never run schema or demo seed imports over a live production database.
6. Create one tenant and one verified domain matching the Host header, then set the tenant status to `active`. If you imported the demo seed, this is already configured for `localhost`.
8. Configure `DB_HOST`, `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD` in the PHP server environment. Do not put production secrets in Git. Set a unique `APP_KEY` of at least 32 characters. For local OTP testing, use `SMS_PROVIDER=log`; the code appears only in the PHP error log. This driver is forbidden in production.
9. Route `/api/v1/*` to `api/v1/index.php` using the web server rewrite configuration, or call the PHP entry point directly while developing.

## Example requests
- `GET /api/v1/store`
- `GET /api/v1/categories`
- `GET /api/v1/products?q=شیر&page=1`

The API resolves the tenant by the verified request host. A tenant identifier supplied by a browser is intentionally not accepted.

## Not production-ready yet
Before real customers or money are involved, implement OTP/session authentication, CSRF protection for browser writes, permission policies, checkout transactions and stock reservation services, provider verification, broader automated tests, HTTPS, backups and monitoring. The tenant-isolation migration must be applied and verified first. Never expose database errors to clients.
