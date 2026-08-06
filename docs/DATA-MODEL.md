# Data Model and Classification

All timestamps are stored in UTC. User-facing windows retain a declared display time zone.

| Table | Canonical entity | Classification | Key invariants |
|---|---|---|---|
| `rsr_schema_values` | Dimension/value/alias definition | Public governed | Stable UUID; unique dimension/value; versioned |
| `rsr_mappings` | Rubric-to-remedy source mapping | Public governed | File 06 remedy ID; source/license/review date; hash dedupe |
| `rsr_studies` | Verified-doctor Saved Study | Private sensitive research | Owner ID; encrypted notes; no patient PII; soft-delete/version |
| `rsr_sources` | Trend source/provider contract | Restricted operational | No raw credentials; approved license; review date; quality, quota, rate and health |
| `rsr_observations` | Aggregate normalized trend row | Restricted aggregate | Unique source/topic/geography/window; trace and method metadata |
| `rsr_reports` | Reproducible trend report snapshot | Draft restricted / published public | Explicit state/version; method, sources, confidence, freshness |
| `rsr_corrections` | Public correction/retraction record | Public notice + restricted reason | Immutable action history linked to report version |
| `rsr_jobs` | Ingestion execution | Restricted operational | Unique idempotency key; trace; bounded status/result/error |
| `rsr_outbox` | Integration event envelope | Restricted operational | Unique event ID; retry/dead-letter; past-tense versioned event |
| `rsr_audit` | Security/governance decision evidence | Restricted audit | Actor, purpose, result, reason, redacted metadata and salted IP hash |

## Retention defaults

- Deleted private studies: 30 days, then physical purge.
- Aggregate observations: 1095 days.
- Completed jobs: 180 days.
- Delivered/dead outbox: 180 days.
- Audit: 2555 days.
- Published/corrected/retracted reports and public correction history: retained as governance history unless a lawful change record says otherwise.

Retention is configurable within guarded minimum/maximum ranges. Legal-hold or jurisdictional extensions require a dated Change-Control record; File 15 does not silently shorten them.

## Data minimization

Private studies contain title, tags, validated Radar query, up to three remedy references and encrypted notes. Names, phone numbers, email addresses, identity numbers, addresses, birth dates, medical-record labels and patient-name labels are conservatively blocked. Private studies never feed public trends, general analytics, public search, AI retrieval or feeds.
