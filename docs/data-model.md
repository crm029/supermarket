# Data model and operating rules

This schema is the initial relational foundation for Supermarket 360. Apply `database/schema.sql` to a new MySQL 8 or compatible MariaDB database.

## Tenant boundary
Every store-owned row has `tenant_id`. Tenant identity must be resolved from a verified request host via `tenant_domains`; never accept a tenant ID from form fields, query strings, or JSON. Every query must include the resolved tenant ID. The initial schema uses foreign keys for integrity; production hardening should add composite tenant-aware foreign keys to every tenant-owned relation and automated cross-tenant isolation tests.

## Money and stock
- All amounts are integer rial.
- Product prices are snapshots on order items; changing a product price does not rewrite historical orders.
- `stock_movements` is the immutable history. `stock_levels` is a transactional projection.
- Checkout must lock the relevant stock rows, create reservations and pending order in one transaction.
- Only a verified server-to-server payment callback may commit reservations and create sale movements.
- Failed or expired payments release reservations. Never deduct stock from a browser redirect.

## Wallet and referral
Wallet balance is calculated from posted append-only `wallet_entries`; do not add an editable balance column. Idempotency keys prevent duplicate credits/debits. Referral rewards must follow a configurable qualifying paid-order and refund window.

## Workflow
The order status enum is a baseline. Production implementation should enforce allowed transitions in a service layer and log each transition. Cashier approval, picking, packing, pickup and dispatch are role-authorized actions.

## Media and virtual store
A tenant owns every media asset. Scene nodes point to tenant media, and hotspots link scenes/products. The same media library supports hero images, product cutouts, aisle photos, panoramas and future 3D assets. Stock photos in the prototype are placeholders and must never be labelled as a merchant's real premises.
