# Media and retention operations

Run these commands from the application repository root with the production environment loaded from the server's secret store. Do not put credentials in crontab or command arguments.

## Scheduled workers

Run the combined media operations check on a schedule. It processes up to 100 explicitly queued cleanup items, then runs the read-only reconciliation scan. It emits a sanitized JSON summary (counts and alert codes only; no tenant storage keys or absolute paths) and returns `0` when healthy, `2` when operator attention is required, or `1` when a worker/configuration/database error occurs.

```cron
MAILTO=ops@example.invalid
17 * * * * cd /srv/supermarket && php scripts/media-operations-check.php
```

The cron host must have a working local mail transport for `MAILTO` delivery. If it does not, forward non-zero exit codes and the JSON output to an authenticated external process monitor. Do not send reports containing storage keys or absolute paths to public destinations. The standalone `scripts/cleanup-media.php` and `scripts/reconcile-media.php` commands remain available for manual diagnosis.

Prune completed media-cleanup queue history and read notifications on separate schedules:

```cron
35 2 * * * cd /srv/supermarket && MEDIA_CLEANUP_RETENTION_DAYS=180 php bin/prune-media-cleanup.php
45 2 * * * cd /srv/supermarket && NOTIFICATION_READ_RETENTION_DAYS=180 php bin/prune-notifications.php
```

Both retention workers have bounded batches and configurable retention between 30 and 3650 days. The media retention worker deletes only queue rows whose status is `completed` and whose `processed_at` is older than the configured cutoff. It never deletes media files or pending queue entries.

## Orphan review

The reconciliation command is intentionally read-only. An orphan means a syntactically valid file under a tenant media directory has no matching media-library record and no pending cleanup record. Review the JSON report, tenant ownership, application logs and backups before any manual intervention. Do not run broad recursive deletion commands against the media root. Orphan cleanup is never automatic. Use the restricted operator-review workflow below; reconciliation itself remains read-only.

## Operator-approved orphan cleanup

The platform dashboard provides a restricted **بررسی فایل‌های رسانه‌ای یتیم** panel for platform operators with the `platform.media.cleanup.manage` permission.

1. Run the read-only reconciliation command and inspect the orphan key, tenant, application logs and backup status.
2. Submit the exact tenant ID and storage key with a reason of at least five characters. The API accepts only the tenant's expected media-key shape and an existing regular file beneath the configured media root; symlinks and unsafe paths are refused.
3. A different authorized operator (not the requester) must approve or reject the pending request; self-approval is rejected. Approval rechecks the file path and symlink status, confirms the key is still absent from `media_assets`, and confirms it is not already queued.
4. Approval records the reviewer and timestamp and inserts a pending row into `media_cleanup_queue`. It does **not** delete the file in the request transaction. The existing cleanup worker performs deletion later and rechecks that the storage key has not been registered again.
5. Rejected requests leave both the file and cleanup queue untouched. Requests are single-use; a storage key cannot be submitted again after a decision.

All request and decision events are written to the platform audit log. Keep the reconciliation report and audit log access restricted because they include tenant identifiers and storage keys. The cleanup worker should be monitored; do not treat an approved request as proof that the file has already been deleted.

## Monitoring

- Alert on reconciliation exit code `2` and any exit code `1`.
- Retain sanitized JSON reports with restricted filesystem permissions; they contain storage paths and tenant identifiers.
- Do not send report contents to public chat/webhook destinations.
- Monitor the cleanup worker's `pending` count and investigate persistent failures rather than repeatedly forcing deletion.
- Test database and media restores before launch.
