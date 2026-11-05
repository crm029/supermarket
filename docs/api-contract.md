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
- `POST /auth/request-otp` — request a rate-limited SMS code.
- `POST /auth/verify-otp` — verify code and issue secure session.
- `GET /me` — profile, referral code and wallet summary.
- `GET /me/orders` — tenant-scoped order history.
- `GET /me/addresses` — address book.
- `POST /checkout` — create pending order, stock reservations and payment attempt.
- `GET /payments/{id}` — payment state, never a substitute for provider verification.

## Store operations
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
