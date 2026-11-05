# Production preflight command

Run this read-only check on the production server after setting the environment variables and applying the schema plus every migration:

```sh
php bin/production-preflight.php
```

The command is CLI-only. It does not write to the database, change store settings, call payment/SMS providers, or print secret values. It exits with code 1 when a required configuration or database check fails and code 0 only when its configuration checks pass. Set PUBLIC_DOCUMENT_ROOT to the absolute public web root when running it from CLI; MEDIA_STORAGE_PATH must be an absolute Linux path outside that directory. Use a dedicated non-root DB_USERNAME.

It verifies:
- Explicit production mode, disabled debug output, a sufficiently long application secret and an HTTPS public origin.
- Required PHP extensions: PDO MySQL, mbstring, cURL and fileinfo.
- Kavenegar production credentials and ZarinPal live-mode configuration.
- Existing writable media storage outside the public document root and an HTTPS media base URL.
- Database connectivity, core application tables and an active tenant mapped to the verified public host.
- Presence of the reservation-expiry worker.

A passing result is a configuration preflight, not production approval. It does not prove that live SMS delivery or payment settlement works, nor replace sandbox/live payment acceptance, refund/reconciliation testing, backup restoration, security review, load testing, accessibility checks, real-device testing, Search Console validation or Core Web Vitals measurement. Keep checkout disabled until the full `docs/production-readiness.md` checklist is complete.


## Regression test

Run `bash tests/production-preflight-fail-closed.sh` from the repository root to verify that incomplete production configuration is rejected and that a sample database secret is not printed. The test uses deliberately invalid local database settings and does not modify a database.
