# File 15 Architecture

## 1. Canonical ownership

File 15 is the canonical owner of:

- the Radar research schema and reviewed rubric-to-remedy mappings;
- private, non-patient Saved Studies for verified doctors;
- trend source configuration, aggregate observations, ingestion jobs, report workflow, corrections and release events.

External ownership remains:

- **File 00:** identity, account state, verified-doctor/founder claims and suspensions;
- **File 06:** canonical remedy identity, publication eligibility, versions and knowledge content;
- **File 20:** global application shell, primary navigation and shared contextual controls;
- **File 21:** public feed/post truth; it may consume only published File 15 report events;
- **File 24:** platform assurance and global security/privacy evidence;
- **File 25:** shared visual tokens and cross-platform presentation;
- **File 26:** general search/discovery/indexing; it consumes public File 15 contracts only;
- **File 16:** educational AI retrieval; it may retrieve only approved public results.

No external module writes directly to File 15 tables. Commands, read contracts and versioned events are the integration boundary.

## 2. Runtime layers

1. `RSR_Domain`: pure limits, validation, report state machine, windows, scoring and canonical hashing.
2. `RSR_DB`: ten canonical tables, idempotent install/upgrade, audit and row evidence.
3. `RSR_Capabilities`: File 00 claim bridge plus native capabilities and object ownership.
4. `RSR_Radar_Service`: schema, bounded search, File 06 linkage, comparison and mapping governance.
5. `RSR_Study_Service`: owner-only encrypted CRUD, PII prevention, export, deletion and retention.
6. `RSR_Provider_Registry`: versioned adapter interface and safe built-in aggregate provider.
7. `RSR_Trend_Service`: source lifecycle, idempotent ingestion, normalization, scoring, report workflow, fallback, correction and retention.
8. `RSR_Events`: transactional-style outbox, bounded retry/dead-letter and versioned consumers.
9. `RSR_API`: stable REST DTOs, permissions, rate limits and cache/privacy headers.
10. `RSR_Routes`: six canonical routes, theme-shell integration, SEO/privacy and accessible presentation.
11. `RSR_Admin`, `RSR_CLI`, `RSR_Privacy`: operations, diagnostics, data rights and automation.

## 3. Canonical routes

| Route | Access | Cache/index | Purpose |
|---|---|---|---|
| `/radar/` | Public | Public, bounded cache | Symptom/rubric search and safety framing |
| `/radar/compare/` | Public | Public, bounded cache | One-to-three File 06 remedy comparison |
| `/radar/studies/` | Verified doctor | `private, no-store`, `noindex` | Owner-only encrypted studies |
| `/trends/` | Public | Public, bounded cache | Approved daily/weekly/monthly/yearly reports |
| `/trends/{report_id}/` | Public | Public, bounded cache | Method, source, window, geography, corrections |
| `/radar/manage/` | Restricted | `private, no-store`, `noindex` | Sources, ingestion, workflow and diagnostics |

## 4. State machines

### Study

`draft(client) → saved ↔ archived → deleted → retention purge`

The database stores only `saved`, `archived`, or `deleted`; client draft state is not canonical.

### Ingestion job

`scheduled → running → succeeded | partial | failed`

The idempotency key prevents duplicate canonical jobs for the same source/window command. Bounded retries are provider/queue policy; failed source health becomes degraded.

### Report

`draft → analyst_review → editorial_review → approved → published → corrected | retracted`

Backward review transitions are permitted only as encoded in `RSR_Domain::report_transitions()`. Ingestion cannot publish.

### Source

`configured → healthy ↔ degraded → quota_exhausted | disabled`

Provider quota events may restore `healthy`; only a governed source command changes persistent configuration.

## 5. Failure semantics

- Unknown or unavailable File 06 remedies fail closed.
- Missing File 00 verification fails private-study access closed.
- Provider failure records a failed job, marks the source degraded, publishes `RadarSourceDegraded.v1`, and leaves the last public approved report available with a freshness/stale label.
- An invalid, duplicate, oversized or PII-bearing study command returns a stable error without mutation.
- An optimistic version mismatch returns `409` and never performs a silent overwrite.
- A dead event remains visible in the outbox for repair; it does not silently disappear.

## 6. Performance design

- Search limits and candidate scans are bounded and cursor-based.
- Public query results use generation-aware object cache.
- Private routes are never publicly cached.
- Ingestion is scheduled/background-capable and idempotent.
- Observations are aggregated; public reports store a reproducibility snapshot instead of re-running heavy joins on every request.
- REST and UI lists have hard maximum page sizes.
