# Security baseline

- HTTPS everywhere; secure, HttpOnly, SameSite cookies; session rotation after login.
- Password hashing via Argon2id/bcrypt and phone OTP throttling.
- CSRF protection, output escaping, parameterized queries and strict input validation.
- Tenant context resolved from host mapping; deny-by-default tenant authorization.
- Explicit permission checks on protected server actions, with tenant-scoped policies for catalogue, staff, stock, POS and platform operations; add route-level integration coverage whenever a protected action is introduced.
- Rate limits for login, OTP, referral validation, checkout and payment endpoints.
- Secrets only in environment/secret manager; never in Git or frontend JavaScript.
- Payment callback verification server-to-server; idempotency and reconciliation.
- Append-only wallet and stock ledgers with actor, timestamp and source references.
- Audit sensitive changes; production logs must not contain OTPs, tokens, full payment secrets or unnecessary personal data. The development-only OTP log driver is forbidden in production.
- Upload validation: MIME/content inspection, bounded file size and image dimensions, randomized names, symlink-resistant tenant/type directories and storage outside the public web root. Images are not currently re-encoded; deployment must serve media from a static-only alias or CDN.
- Backups encrypted, access-controlled and restore-tested.
- Dependency scanning, PHP static analysis, automated tests and security headers in CI.
- Production launch requires a security review and tests on the actual hosting environment.

Implemented controls include tenant-scoped OTP registration/login, hashed challenges, bounded attempts and phone/IP rate limits, opaque database-backed sessions, HttpOnly/SameSite cookies, CSRF protection on protected writes, deny-by-default catalogue permissions, tenant-isolation constraints, upload validation, transactional checkout, server-side payment verification and idempotent wallet/referral ledger posting. Pending registration records do not persist caller-supplied names or referral attribution before OTP ownership verification; profile completion and referral linking require the authenticated post-verification session. Payment callbacks use the tenant's verified primary domain and payment-attempt gateway snapshots. The development SMS log driver is forbidden in production. Production use is still blocked on real merchant credentials, deployment hardening, operational controls, end-to-end acceptance and an independent security review.


## Staff role administration
Tenant staff management is permission-guarded by `staff.manage`. Role creation only accepts tenant-scoped permissions; the `staff.manage` permission and `store_owner` role cannot be delegated through the staff panel. Assignments require an active user in the same tenant, writes require CSRF, and role changes are audited. The store-owner role and self-removal from the staff panel are protected.
