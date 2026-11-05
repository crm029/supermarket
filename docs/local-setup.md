# Local setup (XAMPP)

## Current status
The root `index.php` is the visual demo and can be opened without a database. The new API foundation is separate and requires PHP 8.1+ with PDO MySQL and a MySQL/MariaDB database. The API is read-only at this stage; it does not accept orders, create accounts or process payments.

## Prepare database
1. Create a database named `supermarket360` with utf8mb4.
2. Import `database/schema.sql` using phpMyAdmin.\n3. Import `database/demo-seed.sql` only for a disposable local demo database.\n4. Back up the database, then apply `database/migrations/2026_11_05_tenant_isolation.sql` using phpMyAdmin or the MySQL client. This migration adds tenant-aware composite foreign keys and scope triggers; it intentionally fails if legacy records contain cross-tenant references.\n5. For an existing database, review and repair inconsistent tenant references before applying the migration. Never run schema or demo seed imports over a live production database.
3. Create one tenant, one verified domain matching the Host header, and set its status to `active`.
4. Configure `DB_HOST`, `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD` in the PHP server environment. Do not put production secrets in Git.
8. Route `/api/v1/*` to `api/v1/index.php` using the web server rewrite configuration, or call the PHP entry point directly while developing.

## Example requests
- `GET /api/v1/store`
- `GET /api/v1/categories`
- `GET /api/v1/products?q=شیر&page=1`

The API resolves the tenant by the verified request host. A tenant identifier supplied by a browser is intentionally not accepted.

## Not production-ready yet
Before real customers or money are involved, implement OTP/session authentication, CSRF protection for browser writes, permission policies, tenant-aware composite foreign keys, checkout transactions and stock reservation services, provider verification, automated tests, HTTPS, backups and monitoring. Never expose database errors to clients.
