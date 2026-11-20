# Local test guide (XAMPP)

This guide is for exercising the application locally before production acceptance. Use a disposable database and test credentials only. Never use live customer data or a live payment merchant ID on a local machine.

## 1. Start XAMPP

1. Install a PHP 8.2+ XAMPP build.
2. Start Apache and MySQL from the XAMPP Control Panel.
3. Put the repository folder under `C:\\xampp\\htdocs\\supermarket`.
4. Open `http://localhost/supermarket/` and confirm the storefront loads.
5. Confirm Apache rewrite support is enabled; the clean product/category URLs and API routing depend on the supplied `.htaccess`.

## 2. Prepare a disposable database

1. Open phpMyAdmin at `http://localhost/phpmyadmin`.
2. Create a database named `supermarket360` using `utf8mb4`.
3. Import `database/schema.sql`, then `database/demo-seed.sql`.
4. Apply every migration in the exact dependency order in `docs/local-setup.md`; do not sort by filename.
5. Configure the database variables and a unique local `APP_KEY` in the PHP environment or local `.env`. Never commit `.env`.
6. For local OTP only, use `APP_ENV=local` and `SMS_PROVIDER=log`; read the one-time code from the PHP error log.

## 3. Verify PHP prerequisites

The API requires PHP 8.2+ and these extensions: `pdo_mysql`, `mbstring`, `curl`, `openssl`, and `fileinfo`. Check the active Apache PHP configuration through `http://localhost/dashboard/phpinfo.php` or the XAMPP PHP information page. If an extension is missing, enable it in the Apache-loaded `php.ini` and restart Apache.

## 4. Exercise the main flows

- Browse the storefront, categories and product detail pages.
- Register a disposable customer, request OTP, and verify the account.
- Create a disposable staff/manager account and grant only the permissions needed for catalog and order tests.
- Add products and stock, add items to cart, quote the cart, and place a sandbox checkout.
- Confirm payment completion only after the ZarinPal sandbox callback and server-side verification. Do not mark a payment successful by editing the database.
- Test order acceptance, picking, courier assignment, dispatch, recipient-confirmed delivery and customer order timeline.
- Test POS shift opening, cash sale, receipt print preview, reconciliation and shift closing.
- Test subscription invoice issuance and manual partial/full receipt in the platform billing panel. This is manual receipt recording, not automatic card charging.
- Check `/robots.txt`, `/sitemap.xml`, `/sitemap-core.xml`, product/category canonical URLs and structured data.

## 5. What a local pass does not prove

A local pass does not verify production DNS/TLS, real SMS delivery, provider acceptance, bank settlement, automated recurring SaaS collection, CDN/image performance, Google Search Console indexing, production backups/restore, load, penetration or accessibility testing. Keep these as separate acceptance gates.

## 6. Evidence to record

For each test, record the date, XAMPP/PHP version, browser, scenario, expected result, actual result and any error-log excerpt with secrets and personal data removed. Do not paste OTPs, merchant IDs, API keys, session cookies or customer details into issue reports.
