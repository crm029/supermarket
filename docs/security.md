# Security baseline

- HTTPS everywhere; secure, HttpOnly, SameSite cookies; session rotation after login.
- Password hashing via Argon2id/bcrypt and phone OTP throttling.
- CSRF protection, output escaping, parameterized queries and strict input validation.
- Tenant context resolved from host mapping; deny-by-default tenant authorization.
- Permission checks on every server action; cashier and warehouse scopes are separate.
- Rate limits for login, OTP, referral validation, checkout and payment endpoints.
- Secrets only in environment/secret manager; never in Git or frontend JavaScript.
- Payment callback verification server-to-server; idempotency and reconciliation.
- Append-only wallet and stock ledgers with actor, timestamp and source references.
- Audit sensitive changes; production logs must not contain OTPs, tokens, full payment secrets or unnecessary personal data. The development-only OTP log driver is forbidden in production.
- Upload validation: MIME/content inspection, size limits, randomized names, image re-encoding and non-executable storage.
- Backups encrypted, access-controlled and restore-tested.
- Dependency scanning, PHP static analysis, automated tests and security headers in CI.
- Production launch requires a security review and tests on the actual hosting environment.

The API now has a first OTP login/session slice: OTP hashes, five-minute expiry, five-attempt lockout, phone/IP rate limits, opaque server-side sessions, HttpOnly/SameSite cookies and CSRF-protected logout. The development SMS driver writes codes only to the local PHP error log. Production SMS delivery is deliberately disabled until a provider adapter and credentials are configured. Authorization policies, customer registration, password reset, checkout, payment and inventory workflows are still incomplete; the project must not be used to accept real customer data or payments.
