# Security baseline

- HTTPS everywhere; secure, HttpOnly, SameSite cookies; session rotation after login.
- Password hashing via Argon2id/bcrypt and phone OTP throttling.
- CSRF protection, output escaping, parameterized queries and strict input validation.
- Tenant context resolved from host mapping; deny-by-default tenant authorization.
- Permission checks on protected server actions; the current admin product API uses deny-by-default tenant-scoped permissions. Cashier and warehouse scopes still need their own policies.
- Rate limits for login, OTP, referral validation, checkout and payment endpoints.
- Secrets only in environment/secret manager; never in Git or frontend JavaScript.
- Payment callback verification server-to-server; idempotency and reconciliation.
- Append-only wallet and stock ledgers with actor, timestamp and source references.
- Audit sensitive changes; production logs must not contain OTPs, tokens, full payment secrets or unnecessary personal data. The development-only OTP log driver is forbidden in production.
- Upload validation: MIME/content inspection, size limits, randomized names, image re-encoding and non-executable storage.
- Backups encrypted, access-controlled and restore-tested.
- Dependency scanning, PHP static analysis, automated tests and security headers in CI.
- Production launch requires a security review and tests on the actual hosting environment.

Implemented controls include tenant-scoped OTP registration/login, hashed challenges, bounded attempts and phone/IP rate limits, opaque database-backed sessions, HttpOnly/SameSite cookies, CSRF protection on protected writes, deny-by-default catalogue permissions, tenant-isolation constraints, upload validation, transactional checkout, server-side payment verification and idempotent wallet/referral ledger posting. The development SMS log driver is forbidden in production. Production use is still blocked on real merchant credentials, deployment hardening, operational controls, end-to-end acceptance and an independent security review.


## Staff role administration
Tenant staff management is permission-guarded by `staff.manage`. Role creation only accepts tenant-scoped permissions; the `staff.manage` permission and `store_owner` role cannot be delegated through the staff panel. Assignments require an active user in the same tenant, writes require CSRF, and role changes are audited. The store-owner role and self-removal from the staff panel are protected.
