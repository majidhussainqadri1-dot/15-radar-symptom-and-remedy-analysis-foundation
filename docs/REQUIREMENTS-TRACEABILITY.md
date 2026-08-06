# File 15 Requirements Traceability

Status means implemented in repository source and covered by automated/static evidence. Hostinger staging remains a separate acceptance layer.

## Functional requirements

| ID | Requirement | Status | Primary source evidence | Automated/static evidence |
|---|---|---|---|---|
| F15-FR-001 | Radar schema | Implemented | `RSR_Domain::dimensions`, `RSR_DB` schema table, `RSR_Radar_Service::schema/upsert_schema_value`; canonical `value_key` submission | dimension count, schema/table/route and four-plan canonical-key checks |
| F15-FR-002 | Symptom search | Implemented | `validate_radar_query`, `RSR_Radar_Service::search`, `/radar/`, REST search, `RSR_Four_Plan_Compliance::enhance_search_result` | query validation, bounded-search, explanation, zero-result and no-clinical-rank checks |
| F15-FR-003 | Remedy linkage | Implemented | `rsr_file06_get_remedies`, `HE_Content` compatibility, published/eligible checks; current Saved Study reference validation | File 06 contract and fail-closed static checks |
| F15-FR-004 | Three-remedy comparison | Implemented | `MAX_COMPARE_REMEDIES`, `compare`, public compare UI and server validation | one/three/four comparison tests; licence/review presentation checks |
| F15-FR-005 | Source/license registry | Implemented | mapping/source tables, license allowlists, review dates, territory/restrictions | source schema/license/review-date checks |
| F15-FR-006 | Safety framing | Implemented | `safety_notice`, red-flag escalation, public templates/readme, no treatment outputs | prohibited-output, emergency-boundary and disclosure checks |
| F15-FR-007 | Saved Studies | Implemented | `RSR_Study_Service`, current verified claim, suspension/revocation rechecks, PII scanner, quotas/versioning | PII/domain/static private-study and four-plan claim checks |
| F15-FR-008 | Study privacy | Implemented | encryption fail-closed, owner lookup, private headers, pseudonymous audit, export/delete/privacy callbacks | crypto tests, noindex/no-store/no-referrer/IDOR/static checks |
| F15-FR-009 | Provider adapters | Implemented | provider interface/registry, credentials ref, quota/rate/health/cost fields | provider contract and raw-secret rejection checks |
| F15-FR-010 | Trend ingestion | Implemented | `run_ingestion`, jobs/observations, manual/scheduled commands, trace IDs | idempotency/job/cron/trace static checks |
| F15-FR-011 | Deduplication/normalization | Implemented | alias/geography normalization, unique keys, spam/bot rejection, repost factor | normalization token and deterministic-score tests |
| F15-FR-012 | Trend windows | Implemented | `trend_window`, UTC columns, display time zone | daily/weekly/monthly/yearly window tests |
| F15-FR-013 | Trend scoring | Implemented | `score_trend`, method version, source quality/confidence/thresholds; public non-prevalence semantics | deterministic/bounds/threshold and public-language checks |
| F15-FR-014 | Trend report | Implemented | reports snapshot, sources/method/freshness/geography/caveats/corrections UI | report table/DTO/template static checks |
| F15-FR-015 | Editorial workflow | Implemented | report state machine and capability-separated transitions | valid/invalid transition tests; no auto-publish check |
| F15-FR-016 | Fallback | Implemented | degraded/quota states, stale public report label, manual provider/no-data UI, deterministic zero-result recovery | degraded event/stale/manual/no-data/zero-result checks |
| F15-FR-017 | Correction/retraction | Implemented | corrections table, `correct_report`, public notices, corrected event | correction state/event/public notice checks |
| F15-FR-018 | Search/AI/feed contracts | Implemented | public-only filters/events; private study service never registered as public provider; File 26 wrapper-query handling and no donor bias | public-contract/private-exclusion/four-plan static checks |

## Non-functional release gates

| ID | Requirement | Source implementation | Remaining external evidence |
|---|---|---|---|
| F15-NFR-001 | Object/field authorization | separate capabilities, File 00 claims, immediate suspension/revocation/security-hold checks, owner/state/version checks, stable errors | integrated role/IDOR/claim-revocation staging tests |
| F15-NFR-002 | Privacy lifecycle | purpose audit, minimization, encryption, pseudonymous export/audit, retention, erasure, no-store/no-referrer query transport, non-destructive uninstall | jurisdiction/legal-hold review and staging export/erase evidence |
| F15-NFR-003 | Reliability | idempotency, locks, robust If-Match normalization, outbox retry/dead-letter, reconciliation, degraded states | cron/provider outage/queue recovery staging tests |
| F15-NFR-004 | Performance | hard limits, pagination/cursor, cache generation, background ingestion, report snapshots, bounded throttles | measured p75/p95 and query/load evidence |
| F15-NFR-005 | Accessibility | semantic templates, labels, 44px controls, focus, contrast, reduced motion, RTL; File 20 context-control consumption | browser/screen-reader/zoom/forced-colors acceptance |
| F15-NFR-006 | Observability | trace IDs, redacted logs, audit, diagnostics, row/cron/provider evidence, rate-limit audit | alert routing and operational monitoring evidence |
| F15-NFR-007 | Migration/rollback | schema version, idempotent install, non-destructive uninstall, runbook/checklist | old-data migration dry run, backup/restore and rollback rehearsal |
| F15-NFR-008 | Operability | admin diagnostics, CLI, outbox processing, safe mode, retention and runbook | operator rehearsal and support ownership |
| F15-NFR-009 | Compatibility | minimum metadata, syntax matrix, versioned contracts, v1.1.0 package identity | WordPress 7.0.1/PHP 8.3 integrated verification |
| F15-NFR-010 | Localization | translation functions, text domain, logical CSS, RTL direction, translated trend/client states, time-zone model | Urdu/Arabic translation packs and visual/language QA |

## Four-plan corrective traceability — v1.1.0

| Corrective ID | Governing concern | Implemented evidence | Regression evidence |
|---|---|---|---|
| F15-4P-001 | Current verified-entry claims | `RSR_Capabilities::is_access_restricted` | suspension/revocation/security-hold static assertions |
| F15-4P-002 | Health-query privacy | `RSR_Four_Plan_Compliance` page and REST headers | sensitive-route/no-store/no-referrer assertions |
| F15-4P-003 | Explainable Top-20 discovery | `why_this_result`, evidence counts, ranking metadata | misleading percentage negative regression |
| F15-4P-004 | Zero-result recovery | `zero_result_recovery` with no fabricated result | recovery and fabricated-result assertions |
| F15-4P-005 | Clinical/emergency boundary | `safety_escalation`; no prescription/potency/dose authority | red-flag and prohibited-output assertions |
| F15-4P-006 | Current File 06 references | `rsr_validate_study_remedy_refs` fail-closed contract | current eligibility/static assertions |
| F15-4P-007 | Private-study integrity | encryption exception handling, pseudonymous export/audit | encryption-failure and raw-owner-ID negative checks |
| F15-4P-008 | File 20 ownership | `sabri_file20_context_controls_markup_v1` and duplicate guard | context-owner assertion |
| F15-4P-009 | File 26/search boundary | wrapper-query normalization; File 26 remains projection owner | wrapper-query and no donor-bias assertions |
| F15-4P-010 | Release truth | `1.1.0`, audit register, deterministic build and separate external gates | build/CI/package evidence |
