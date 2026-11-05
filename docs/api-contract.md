# API contract — initial outline

Base path: `/api/v1`. JSON responses use UTF-8 and Persian user-facing messages. Every endpoint is tenant-scoped from the verified host.

## Public storefront
- `GET /store` — store profile, branding, opening hours and fulfillment options.
- `GET /categories` — active category tree.
- `GET /products?category=&q=&page=` — searchable, paginated catalog.
- `GET /products/{id}` — product details and available stock status.
- `GET /scenes` — published photo-tour nodes and hotspots.
- `POST /cart/quote` — server-side price and availability recalculation.

## Customer
- `POST /auth/request-otp` — request a rate-limited SMS code for an existing active account.
- `POST /auth/verify-otp` — verify code and issue a database-backed session cookie plus CSRF token.
- `GET /me` — authenticated customer profile.
- `POST /auth/logout` — revoke the current session; requires `X-CSRF-Token`.
- `GET /me/orders` — tenant-scoped order history.
- `GET /me/addresses` — address book.
- `POST /checkout` — create pending order, stock reservations and payment attempt.
- `GET /payments/{id}` — payment state, never a substitute for provider verification.

## Store operations
- `GET /admin/products` — tenant-scoped product list/search; requires `catalog.products.read`.
- `POST /admin/products` — create a product; requires `catalog.products.manage`, session and CSRF token.
- `PATCH /admin/products/{id}` — update a product; requires `catalog.products.manage`, session and CSRF token.
- `DELETE /admin/products/{id}` — soft-deactivate a product; requires `catalog.products.manage`, session and CSRF token.
- `GET /admin/dashboard`
- `POST /admin/products`, `PATCH /admin/products/{id}`
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
- The included `SMS_PROVIDER=log` adapter is development-only. Production OTP delivery remains disabled until a real provider adapter and credentials are configured.

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
