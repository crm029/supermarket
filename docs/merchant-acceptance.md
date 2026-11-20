# Merchant and production acceptance checklist

This checklist is for the first real merchant deployment. Passing CI or configuring credentials does not count as live-provider acceptance. Record the merchant, environment, date, operator and provider transaction references for every production test. Never put API keys, passwords, OTPs or full payment credentials in this document or Git.

## 1. Before staging

- [ ] Use a dedicated staging hostname with HTTPS and a verified tenant-domain mapping.
- [ ] Use a least-privilege MySQL account; confirm `APP_DEBUG=false` and a unique `APP_KEY`.
- [ ] Configure an external media directory outside both the application and public document roots; serve media only through a static-only alias or CDN.
- [ ] Configure scheduled reservation expiry, subscription billing and notification/media cleanup workers as appropriate.
- [ ] Take an encrypted database backup and verify that a restore can be completed.
- [ ] Run `APP_ENV=production php bin/production-preflight.php`; resolve every FAIL and review every WARN.
- [ ] Confirm that production uses a real SMS provider; the development `SMS_PROVIDER=log` driver must remain disabled.

## 2. SMS provider acceptance

- [ ] Configure the provider credentials and approved OTP template in the server secret store.
- [ ] Request an OTP for a test phone owned by the merchant/operator and confirm delivery. For 0098 VIP, use the platform-owner test-send control first, then exercise the OTP flow; the test-send control only targets the verified platform-owner mobile.
- [ ] Verify a correct code, an incorrect code, an expired code and the maximum-attempt lockout.
- [ ] Verify phone/IP throttling and that resending does not invalidate or bypass security limits.
- [ ] Confirm that provider failures return a safe user-facing error and do not activate an account.
- [ ] Check the provider delivery report separately; an accepted API request is not proof of handset delivery.
- [ ] Confirm logs contain no plaintext OTP, API key or password.

## 3. ZarinPal sandbox acceptance

The application sandbox adapter uses these v4 endpoints:

- Request: `https://sandbox.zarinpal.com/pg/v4/payment/request.json`
- Verify: `https://sandbox.zarinpal.com/pg/v4/payment/verify.json`
- Redirect: `https://sandbox.zarinpal.com/pg/StartPay/{authority}`

The repository's local `.env.example` is prefilled with the project-provided public sandbox merchant UUID `50cd6e57-81b9-4f2b-9aff-6ce699f4b56d`. Copy it into your local `.env` as a starting point, keep `ZARINPAL_SANDBOX=true`, or enter the same UUID in **Platform → تنظیمات درگاه پرداخت سراسری** and choose **Sandbox — آزمایشی**. The value is specifically a sandbox identifier supplied for this project; never substitute it for a live merchant credential. When saved through the platform UI, it is encrypted in the database. Do not log merchant settings. Sandbox authorities must begin with uppercase `S`; callback validation now checks this against the saved payment-attempt mode. A sandbox payment must never be treated as proof of live settlement.

- [ ] Select sandbox mode and use the merchant's sandbox credentials.
- [ ] Complete a successful low-value test order and verify the provider reference, order amount and currency.
- [ ] Verify that stock reservations are committed only after server-side payment verification.
- [ ] Test a cancelled/failed payment and confirm that the order remains unpaid and its reservation expires/releases correctly.
- [ ] Replay the same callback and verify idempotency: no duplicate payment, stock commit, wallet credit or referral reward.
- [ ] Attempt an amount mismatch and invalid authority/reference; both must be rejected.
- [ ] Verify the customer result page does not emit a purchase analytics event for an unverified payment.
- [ ] Reconcile application payment attempts against the provider's sandbox transaction report.

## 4. Live payment acceptance

- [ ] Obtain merchant approval and confirm the live merchant ID and callback URL with the provider.
- [ ] Switch to live mode only on production HTTPS after sandbox acceptance.
- [ ] Make one merchant-approved low-value real transaction using an authorized test customer.
- [ ] Confirm the server-side verify result, order status, paid amount, provider reference and inventory commit.
- [ ] Replay the callback and test a failed/invalid callback; confirm idempotent and fail-closed behavior.
- [ ] Compare the transaction with the provider dashboard and eventual bank settlement/payout statement.
- [ ] Record fees, gross amount, net settlement, settlement date and any discrepancy. Resolve discrepancies before opening checkout to the public.
- [ ] Confirm the operational refund procedure. Wallet-credit refunds are not bank/gateway refunds; never describe them as equivalent.
- [ ] Do not mark live acceptance complete until a real settlement statement has been reconciled.

## 5. Launch and rollback

- [ ] Complete desktop and mobile customer checkout, seller operations, POS, courier and pickup acceptance.
- [ ] Verify printer/scanner behavior with the merchant's actual devices; browser printing depends on OS-installed drivers.
- [ ] Review access roles, domain verification, HTTPS renewal, backup schedule, monitoring and incident contacts.
- [ ] Run a restore drill and document the rollback owner and decision point.
- [ ] Keep public registration/checkout disabled until all critical acceptance checks pass.
- [ ] Obtain merchant sign-off and archive the sanitized acceptance record outside the source repository.

## Current software boundaries

The application contains server-side ZarinPal request/verify logic, encrypted platform merchant settings, payment-attempt snapshots, duplicate-callback protection and wallet-credit refunds. Real-provider approval, a real handset SMS test, live bank settlement reconciliation, and gateway/bank refund acceptance require merchant accounts and external transactions; CI cannot substitute for them.
