# Operations Runbook

## Daily checks

- diagnostics show no missing table and current schema/plugin versions;
- cron next-run timestamps exist;
- source health, quota, last success and stale state are visible;
- failed/partial jobs are reviewed with trace IDs;
- outbox has no unexplained dead events;
- published reports have correct freshness labels and correction notices.

## Source degradation

1. Do not expose credentials or raw provider errors.
2. Confirm source status, provider adapter availability, quota and rate limits.
3. Preserve the last approved report with its age/stale label.
4. Disable the source only when continued attempts are unsafe or unauthorized.
5. Use an approved manual aggregate source only with license/provenance and human review.
6. After recovery, run an idempotent replay for the exact window and reconcile the draft.

## Incorrect public report

1. Record the report ID, version, source window and impact.
2. Use correction or retraction; never silently overwrite public history.
3. Require internal reason and public notice.
4. Verify `RadarTrendReportCorrected.v1` delivery to discovery/feed/AI consumers.
5. purge/invalidate affected caches and confirm public UI.

## Private-study incident

1. Enable safe mode to stop writes if necessary.
2. Restrict incident access to authorized privacy/security staff.
3. Search only by stable IDs/trace IDs; do not copy study contents into tickets or logs.
4. rotate relevant WordPress secret material only under a tested key-migration/recovery plan; otherwise existing encrypted notes become unreadable.
5. notify affected users/regulators only under applicable approved incident policy.
6. test owner access, export, deletion and cache/index absence after remediation.

## Event outbox

- `pending/retry`: process through cron or `wp rsr outbox`.
- `dead`: inspect the consumer, preserve the envelope, fix the consumer, then use a reviewed replay tool/change record; do not edit history silently.

## CLI

```bash
wp rsr health
wp rsr sources --format=table
wp rsr ingest <source-public-id> --window=daily --timezone=Asia/Karachi --geography=pk
wp rsr outbox --limit=200
wp rsr upgrade
```

## Backup evidence

Before every staging/production change, record file/database backup IDs, checksums, restore test result, package checksum, source commit, migration dry run, rollback owner and monitoring window.
