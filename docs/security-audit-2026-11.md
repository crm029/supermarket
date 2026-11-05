# Security review — 2026-11-05

## Scope and confidence

This is a source-level review of the files available in the `crm029/supermarket` repository, focused on the PHP bootstrap, read-only API entry point, SQL schema and browser storefront. No production host, database, DNS, payment account, SMS account or deployed runtime was available. This document is **not** a penetration-test certificate and does not establish that a deployed site is secure.

## Changes made on this branch

- Production database configuration now fails closed when required `DB_*` values are missing; the convenient root/blank-password defaults are limited to development mode.
- Tenant host parsing now rejects malformed host values and normalizes the host before looking up a verified domain mapping.
- API responses now send additional restrictive browser security headers and explicit no-cache headers.
- Storefront product, description, brand and cart text is HTML-escaped before insertion into HTML templates. Product image URLs are restricted to HTTPS.
- Added GitHub Actions checks for PHP syntax and JavaScript syntax on pushes and pull requests.

## Findings that still block production

### Critical — no real authentication or authorization

The current API accepts only GET requests. There is no customer OTP/session flow, password reset, CSRF-protected write flow, admin authentication, permission middleware or role enforcement. The role tables in the schema are not an authorization system. Do not expose management functions or accept customer accounts until these are implemented and tested.

### Critical — tenant isolation is not fully enforced by the database

The schema has tenant IDs on tenant-owned tables, but many foreign keys reference a row ID alone rather than a composite key containing `tenant_id`. Application query scoping alone is not enough to prevent cross-tenant references or mistakes in future code. Add composite unique keys and composite foreign keys across all tenant-owned relationships, then run negative cross-tenant tests.

### Critical — no checkout, payment verification or stock transaction service

The current cart exists only in browser memory. There is no persistent cart, order creation, stock reservation, payment callback verification, idempotency service, refund workflow or oversell protection. Never accept money or represent an order as paid until these are implemented and verified server-to-server.

### High — upload and media security is not implemented

There is no production media upload service. Before enabling uploads, enforce allow-listed MIME and file signatures, byte and dimension limits, randomized storage names, image re-encoding, malware scanning where appropriate, and storage outside executable web paths. Enforce tenant ownership on every media read and write.

### High — operational security is unverified

HTTPS, secure session cookie flags, reverse-proxy configuration, database least-privilege credentials, firewall rules, backup encryption and restore tests, monitoring, alerting, log retention and production secret storage have not been verified. Configure these on the actual host before launch.

### High — no end-to-end or security regression tests

The added workflow checks syntax only. It does not prove tenant isolation, authentication safety, payment correctness, CSRF protection, rate limiting, concurrency safety or deployment security. Add automated unit, integration, authorization, tenant-boundary and payment-idempotency tests.

## Required release gates

1. Implement OTP/session authentication, session rotation, rate limiting and account recovery.
2. Implement permission-based policies for super-admin, store manager, cashier, warehouse operator, picker, courier and customer.
3. Add tenant-aware composite foreign keys and automated cross-tenant negative tests.
4. Implement CSRF protection for browser mutations and strict server-side validation.
5. Implement transactional checkout, stock reservations, provider-side payment verification and idempotent callbacks.
6. Implement secure media upload and tenant-scoped storage.
7. Configure production HTTPS, secrets, least-privilege DB user, backups, monitoring and restore drills.
8. Run PHP static analysis, dependency and secret scanning, integration tests, load tests and an independent penetration test against the deployed staging environment.
9. Verify Zarinpal and 0098.ir integrations with merchant-owned credentials and provider sandbox/live accounts.
10. Replace sample product and supermarket imagery with assets the merchant is authorized to use; verify actual delivery zones, pickup rules, prices and stock.

## Release decision

**NOT APPROVED FOR PRODUCTION.** The current repository is a visual prototype plus a read-only API foundation. The code changes in this branch reduce a few concrete risks, but they do not turn the prototype into a secure, complete commerce platform. Keep real customer data, credentials and payments out of this build until every critical release gate is implemented and verified.
