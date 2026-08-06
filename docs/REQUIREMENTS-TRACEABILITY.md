# File 15 Requirements Traceability

Status means implemented in repository source and covered by automated/static evidence. Hostinger staging remains a separate acceptance layer.

## Functional requirements

| ID | Requirement | Status | Primary source evidence | Automated/static evidence |
|---|---|---|---|---|
| F15-FR-001 | Radar schema | Implemented | `RSR_Domain::dimensions`, `RSR_DB` schema table, `RSR_Radar_Service::schema/upsert_schema_value` | dimension count, schema/table/route checks |
| F15-FR-002 | Symptom search | Implemented | `validate_radar_query`, `RSR_Radar_Service::search`, `/radar/`, REST search | query validation/domain tests, bounded-search static checks |
| F15-FR-003 | Remedy linkage | Implemented | `rsr_file06_get_remedies`, `HE_Content` compatibility, published/eligible checks | File 06 contract and fail-closed static checks |
| F15-FR-004 | Three-remedy comparison | Implemented | `MAX_COMPARE_REMEDIES`, `compare`, public compare UI and server validation | one/three/four comparison tests |
| F15-FR-005 | Source/license registry | Implemented | mapping/source tables, license allowlists, review dates, territory/restrictions | source schema/license/review-date checks |
| F15-FR-006 | Safety framing | Implemented | `safety_notice`, public templates/readme, no treatment outputs | prohibited-output and disclosure checks |
| F15-FR-007 | Saved Studies | Implemented | `RSR_Study_Service`, verified claim, PII scanner, quotas/versioning | PII/domain/static private-study checks |
| F15-FR-008 | Study privacy | Implemented | encryption, owner lookup, private headers, export/delete/privacy callbacks | crypto tests, noindex/no-store/IDOR static checks |
| F15-FR-009 | Provider adapters | Implemented | provider interface/registry, credentials ref, quota/rate/health/cost fields | provider contract and raw-secret rejection checks |
| F15-FR-010 | Trend ingestion | Implemented | `run_ingestion`, jobs/observations, manual/scheduled commands, trace IDs | idempotency/job/cron/trace static checks |
| F15-FR-011 | Deduplication/normalization | Implemented | alias/geography normalization, unique keys, spam/bot rejection, repost factor | normalization token and deterministic-score tests |
| F15-FR-012 | Trend windows | Implemented | `trend_window`, UTC columns, display time zone | daily/weekly/monthly/yearly window tests |
| F15-FR-013 | Trend scoring | Implemented | `score_trend`, method version, source quality/confidence/thresholds | deterministic/bounds/threshold tests |
| F15-FR-014 | Trend report | Implemented | reports snapshot, sources/method/freshness/geography/caveats/corrections UI | report table/DTO/template static checks |
| F15-FR-015 | Editorial workflow | Implemented | report state machine and capability-separated transitions | valid/invalid transition tests; no auto-publish check |
| F15-FR-016 | Fallback | Implemented | degraded/quota states, stale public report label, manual provider/no-data UI | degraded event/stale/manual/no-data checks |
| F15-FR-017 | Correction/retraction | Implemented | corrections table, `correct_report`, public notices, corrected event | correction state/event/public notice checks |
| F15-FR-018 | Search/AI/feed contracts | Implemented | public-only filters/events; private study service never registered as public provider | public-contract/private-exclusion static checks |

## Non-functional release gates

| ID | Requirement | Source implementation | Remaining external evidence |
|---|---|---|---|
| F15-NFR-001 | Object/field authorization | separate capabilities, File 00 claims, owner/state/version checks, stable errors | integrated role/IDOR/claim-revocation staging tests |
| F15-NFR-002 | Privacy lifecycle | purpose audit, minimization, encryption, retention, export, erasure, non-destructive uninstall | jurisdiction/legal-hold review and staging export/erase evidence |
| F15-NFR-003 | Reliability | idempotency, locks, outbox retry/dead-letter, reconciliation, degraded states | cron/provider outage/queue recovery staging tests |
| F15-NFR-004 | Performance | hard limits, pagination/cursor, cache generation, background ingestion, report snapshots | measured p75/p95 and query/load evidence |
| F15-NFR-005 | Accessibility | semantic templates, labels, 44px controls, focus, contrast, reduced motion, RTL | browser/screen-reader/zoom/forced-colors acceptance |
| F15-NFR-006 | Observability | trace IDs, redacted logs, audit, diagnostics, row/cron/provider evidence | alert routing and operational monitoring evidence |
| F15-NFR-007 | Migration/rollback | schema version, idempotent install, non-destructive uninstall, runbook/checklist | old-data migration dry run, backup/restore and rollback rehearsal |
| F15-NFR-008 | Operability | admin diagnostics, CLI, outbox processing, safe mode, retention and runbook | operator rehearsal and support ownership |
| F15-NFR-009 | Compatibility | minimum metadata, syntax matrix, versioned contracts | WordPress 7.0.1/PHP 8.3 integrated verification |
| F15-NFR-010 | Localization | translation functions, text domain, logical CSS, RTL direction, time-zone model | Urdu/Arabic translation packs and visual/language QA |
