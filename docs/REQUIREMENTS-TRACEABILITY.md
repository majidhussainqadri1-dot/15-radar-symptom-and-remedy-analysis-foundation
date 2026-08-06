# File 15 Requirements Traceability — v1.2.0

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
