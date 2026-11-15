# File 15 Requirements Traceability — v1.2.5 (source proposal)

All F15 functional requirements 001–018 and non-functional requirements 001–010 remain represented in source. Version 1.2.0 extends evidence with the forty-round corrective controls.

| Area | Primary implementation | Evidence |
|---|---|---|
| Schema/search/comparison | `RSR_Domain`, `RSR_Radar_Service` | domain + static tests |
| Private studies | `RSR_Study_Service`, `RSR_Crypto`, `RSR_PII_Scanner` | crypto/PII/domain tests |
| Sources/providers | `RSR_Provider_Registry`, manual provider, trend service | static/forty-round tests |
| Ingestion/scoring/reports | `RSR_Trend_Service` | domain/static tests |
| Editorial/correction | capabilities, report state machine, transactions | static/forty-round tests |
| Reliability | DB migration lock, inbox/outbox leases, atomic limiter | static/forty-round tests |
| Privacy/security | hardening, redaction, no-store/no-referrer, audit hashes | static/forty-round tests |
| UX/integration | routes/templates/JS/File 20 contract | static/forty-round tests |

External evidence remains required for staging roles, provider outage/retry, measured performance, browser/accessibility, restore/rollback and live monitoring.


## V12.3 classification gap response

| Contract | Source work | Still requires |
|---|---|---|
| F15-FR-009/010/011 | Versioned source signal classification in schema, validation, editor, reports | Real approved provider and source test fixtures |
| F15-FR-013/014/015 | Typed topic groups and source disclosures; correction checks | Scientific metric-domain owner approval and staged editorial exercises |
| F15-NFR-007/009 | Schema guard 1.2.1 and regression assertions | Real MariaDB upgrade/downgrade restore |
| F15-FR-018 | Public type label in source/result contract | File 26 adapter approval, tombstones and live indexing |
| AT-DI-01–14 | Research activity types cannot silently claim prevalence | Canonical disease IDs, denominator, source-specific case definition and complete independent Disease Intelligence module |

Draft source coverage is not complete end-to-end coverage. The File 06 native eligibility bridge, File 26 public connector and true clinical disease taxonomy are independent open acceptance gates.


## Typed report allocation correctness

The 1.2.5 candidate uses deterministic round-robin selection (up to 100 visible topics total), so a high-volume generic activity source cannot entirely crowd out smaller search/news groups. Within each group, score/volume/label tie-breaking remains deterministic. This is a *presentation fairness bound*, not an epidemiological comparison, and is covered by pure domain assertions. Real dataset performance and interpretability still need staging review.
