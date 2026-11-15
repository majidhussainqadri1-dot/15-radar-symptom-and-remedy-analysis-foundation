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


## Source signal classification checks (proposed 1.2.5)

- Confirm plugin runtime 1.2.5 and database version 1.2.1 match the accepted package **after** verified staging upgrade.
- Read the canonical `rsr_sources.signal_type` and ensure each source is one of source_activity, search_interest or news_interest. Unreviewed epidemiology/surveillance must not enter these ranked activity reports.
- If `rsr_signal_type_lookup_unavailable` or `rsr_signal_type_check_unavailable` occurs, treat it as database-integrity evidence; **do not** bypass the check or reclassify the source. Freeze DB state, check schema and logs and repair only with tested migration steps.
- Never relabel a source that has already generated observations without a scoped migration, audit record and correction/retraction of affected reports.
- A successful repository CI result does not establish deployed column presence or operational acceptance.
