# Merchant onboarding checklist

1. Create a tenant record with a unique slug and status `pending`.
2. Add the merchant's requested domain to `tenant_domains`; verify DNS/HTTP ownership before activation.
3. Create the store manager account and assign the least-privilege owner/manager permission set.
4. Configure store name, logo, colors, phone, address, opening hours and delivery/pickup rules.
5. Create warehouses before importing stock. Record opening inventory as receipt movements; do not silently seed stock balances.
6. Import categories and products with SKU/barcode, price in rial, unit, variants and product photos.
7. Upload real store entrance, aisle and shelf photographs. Add scene nodes and hotspots; keep guided catalog browsing available.
8. Configure delivery zones, courier roles and cashier/picking workflow.
9. Connect SMS and Zarinpal credentials through environment secrets; test OTP and payment callbacks in sandbox/staging.
10. Place test orders covering success, failure, duplicate callback, expiry, cancellation, refund and low-stock races.
11. Confirm the domain, HTTPS, backups, monitoring, privacy notice and merchant sign-off before switching tenant status to active.

A merchant's storefront is a separate branded domain, while the application and database remain centrally operated. Tenant isolation must be verified before onboarding real customer data.
