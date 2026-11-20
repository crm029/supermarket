# Source-level security re-review — 2026-11-20

## Scope and limits

This review covers the repository source and automated GitHub Actions checks as of the commit that introduced the changes below. It is not a penetration test, a production-host review, a provider acceptance test, or a guarantee that a deployed environment is secure. No production database, merchant account, SMS account, DNS zone, hosting control panel, or live customer data was accessed.

## Findings addressed

### Registration profile pre-claim

Previously, the public registration endpoint stored the caller-supplied full name and referral attribution before the phone number was verified. A third party could therefore pre-create a pending account for another person's mobile and influence the profile/referrer attached to the account later activated by the real phone owner.

The registration endpoint now creates a neutral pending profile without persisting the supplied name or referral. OTP verification activates the account, after which the authenticated session completes the profile through a CSRF-protected endpoint. Referral attribution is looked up and attached only in that authenticated step. Registration attempts are rate-limited by hashed mobile and IP buckets. A migration clears unverified name/referrer data from existing pending accounts.

### Duplicate payment attempts and environment snapshot

The payment-start endpoint now treats a recent `created` attempt as in-flight and returns HTTP 409 rather than issuing another provider request. A previously redirected attempt is resumed using the gateway mode and encrypted merchant snapshot recorded on that attempt, not the platform's potentially changed current mode.

### Uncertain payment outcome and retry safety

A provider request can be accepted even when the application loses the response. Such a request must not be silently marked failed and retried, because the external payment outcome may be unknown. Stale in-flight attempts and transport exceptions now enter a dedicated `review` state; new attempts are blocked until an operator reconciles the attempt with the provider dashboard. Explicit provider rejections remain failed.

### Multi-tenant payment callback host

Payment callback URLs are now built using the tenant's verified primary domain. This prevents a shared global application URL from routing callbacks to the wrong tenant resolver when multiple stores use separate verified domains. Host values are validated before constructing the callback URL.

### Regression coverage

Automated tests now cover payment attempt state decisions, preservation of sandbox URLs after a platform mode switch, tenant-specific callback URL construction, unsafe callback-host rejection, OTP-gated registration profile completion, and the absence of unverified profile/referral values on pending accounts. Existing PHP, JavaScript, MySQL and MariaDB CI suites remain release gates.

## Residual risks and production blockers

- A green CI run does not establish that the 0098 VIP or Kavenegar provider delivered a message to a handset. Complete real-phone OTP, expiry, resend, throttling and provider-delivery acceptance.
- The 0098 VIP contract supplied for this integration requires credentials in HTTPS query parameters. Hosting, proxy, outbound gateway and diagnostic URL logging must be reviewed and configured not to retain full request URLs. Provider acceptance is not handset delivery.
- ZarinPal sandbox and live acceptance, duplicate/late callback tests against the provider, bank settlement reconciliation, and gateway/bank refund handling still require merchant-owned credentials and actual provider transactions.
- Each tenant's verified primary domain must have correct DNS and valid HTTPS. Test callback routing for every production tenant.
- Production APP_KEY, database least privilege, HTTPS/reverse-proxy behavior, media storage aliasing, backups, restore drills, monitoring, log retention and cron schedules must be configured and verified on the actual host.
- Replace sample store/product/aisle imagery with merchant-authorized assets and complete desktop/mobile accessibility, performance and device acceptance.
- Independent staging penetration testing and load testing remain outstanding.

## Release decision

**NOT APPROVED FOR PUBLIC PRODUCTION.** The source-level fixes above reduce concrete risks, but external-provider acceptance, production infrastructure, merchant content and independent security testing remain mandatory before live customer data or payments are enabled.
