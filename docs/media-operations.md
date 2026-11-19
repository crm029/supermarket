# Media and retention operations

Run these commands from the application repository root with the production environment loaded from the server's secret store. Do not put credentials in crontab or command arguments.

## Scheduled workers

Run the media cleanup worker frequently so files explicitly queued by an authenticated, reference-checked deletion are removed:

```cron
*/15 * * * * cd /srv/supermarket && php scripts/cleanup-media.php
```

Run the read-only reconciliation daily. It prints a JSON report and uses exit codes: `0` means healthy, `2` means review is required (orphan, missing or unsafe entries), and `1` means configuration/database failure. Configure the host's cron mail or external process monitor to alert on non-zero exit codes; alert delivery is an infrastructure integration and is not sent by the PHP scanner itself.

```cron
MAILTO=ops@example.invalid
17 2 * * * cd /srv/supermarket && php scripts/reconcile-media.php
```

Prune completed media-cleanup queue history and read notifications on separate schedules:

```cron
35 2 * * * cd /srv/supermarket && MEDIA_CLEANUP_RETENTION_DAYS=180 php bin/prune-media-cleanup.php
45 2 * * * cd /srv/supermarket && NOTIFICATION_READ_RETENTION_DAYS=180 php bin/prune-notifications.php
```

Both retention workers have bounded batches and configurable retention between 30 and 3650 days. The media retention worker deletes only queue rows whose status is `completed` and whose `processed_at` is older than the configured cutoff. It never deletes media files or pending queue entries.

## Orphan review

The reconciliation command is intentionally read-only. An orphan means a syntactically valid file under a tenant media directory has no matching media-library record and no pending cleanup record. Review the JSON report, tenant ownership, application logs and backups before any manual intervention. Do not run broad recursive deletion commands against the media root. A dedicated operator-approved orphan queueing workflow is not yet implemented.

## Monitoring

- Alert on reconciliation exit code `2` and any exit code `1`.
- Retain sanitized JSON reports with restricted filesystem permissions; they contain storage paths and tenant identifiers.
- Do not send report contents to public chat/webhook destinations.
- Monitor the cleanup worker's `pending` count and investigate persistent failures rather than repeatedly forcing deletion.
- Test database and media restores before launch.
