# Hostinger/WordPress Staging Acceptance

Repository source and automated tests do not replace these external gates.

## Environment

- clone of current staging data with privacy-safe handling;
- WordPress 7.0.1 and PHP 8.3 target verification;
- HTTPS, cron, object/page cache and permalink configuration recorded;
- File 00, File 06, File 20, File 24, File 25 and File 26 integration versions recorded;
- database/files backup and verified restore before testing.

## Fresh install and upgrade

- activate on fresh staging; ten tables, caps, schedules and routes exist;
- repeat activation and schema upgrade without duplicates/data loss;
- rehearse approved historical 0.1.x migration separately, including dry run and rollback;
- deactivate/reactivate without exposing dead shortcodes/routes or destroying data;
- uninstall without purge preserves data; explicit purge only in disposable environment.

## Role and object matrix

Test guest, member, verified doctor, suspended doctor, founder, schema editor, source operator, analyst, editorial approver, publisher, correction authority and administrator. Include direct object-ID substitution, stale session/claim, REST, browser and CLI paths.

## Radar

- sixteen dimensions, aliases, definitions, source/version;
- AND/OR, empty, unknown, oversized and Unicode/RTL queries;
- File 06 published/eligible linkage and correction/retraction invalidation;
- exactly one, two and three remedies; fourth blocked server-side;
- references, licenses, review dates, limits and safety disclosures;
- bounded pagination/cache and no false treatment ranking.

## Private studies

- unverified/suspended access denied without existence leak;
- owner CRUD and cross-owner IDOR denial;
- version conflict/concurrent update;
- PII payloads including obfuscation attempts blocked;
- encryption at rest verified without exposing keys;
- noindex/noarchive/nofollow/no-store through browser, CDN and cache plugins;
- export, erase, user deletion and retention purge;
- private data absent from search, AI, feed, trends, logs and diagnostics.

## Trend sources and ingestion

- approved/unapproved licenses, review dates, restrictions and raw credential rejection;
- provider registration/capability failure;
- quota, rate limit, timeout, malformed rows, duplicates, aliases, geography, spam/bot/repost normalization;
- idempotency and concurrent duplicate command;
- daily previous-24-hour local-midnight behavior, rolling 7-day window, previous month and previous year across DST/time zones;
- UTC storage and correct display zone;
- source degradation, stale last-known report, manual fallback and no-data state.

## Reports

- deterministic score snapshot and threshold behavior;
- draft created but never auto-published;
- invalid state transitions denied;
- analyst → editorial → approved → published sequence;
- public method, sources, geography, confidence, freshness, caveats and reproducibility;
- correction/retraction public notice and event propagation;
- published report history not silently overwritten by late ingestion.

## Non-functional

- keyboard-only navigation, visible focus, semantic labels, zoom 200/400%, screen reader, contrast and forced colors;
- Urdu/Arabic RTL, English/number/URL LTR islands, dates/time zones and mobile layout;
- reduced motion and no manipulative infinite scroll;
- p75/p95 latency and database query budgets under representative data;
- security scan, dependency review, penetration test and log redaction;
- cron/queue recovery, dead-letter, backup restore and rollback rehearsal.

## Exit rule

Every critical/high defect is corrected and all affected tests rerun. Staging acceptance requires dated evidence and explicit Founder approval. Only then may a production deployment be proposed.
