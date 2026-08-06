# File 15 Four-Plan Audit and Corrective Register

Date: 2026-08-06 (Pakistan Standard Time)

Branch: `audit/four-plan-compliance-2026-08-06`

Baseline: `46054525b759638ca7cd1a413390a4f9ece49315`

Corrective release: `1.1.0`

## Governing corpus

This audit treats four approved plans as one precedence-controlled corpus:

1. Definitive Integrated Master Plan v3.0 — product constitution, numbered ownership, privacy/security and release status law.
2. Consolidated All-Chats Recovered Directive Register v2.1 — central green, RTL/right priority, File 20 Back/Home ownership, Islamic privacy, no duplicate shell, review/fix doctrine and no donor advantage.
3. Continuous Value / Global Top-20 Feature Superset Master Plan — explainable discovery, zero-result recovery, correction trails, safety/healthy-use guardrails and File 26 search ownership.
4. File 15 Dedicated Complete Master Plan v1.0 — Radar schema, File 06 remedies, maximum-three comparison, private Saved Studies, source/provider governance, daily/weekly/monthly/yearly trends, editorial approval, safety, QA and operations.

Precedence: definitive Islamic constraints and latest Founder directives → latest approved amendment/directive → dedicated File 15 plan → verified runtime evidence.

## Review round 1 — scope, ownership and traceability

| ID | Defect | Severity | Correction |
|---|---|---:|---|
| R1-D01 | Integration metadata did not explicitly declare File 20/24/25/26 boundaries or no paid/donor search bias. | Medium | Expanded `sabri_module_registered_v1` with owner/consumer boundaries, public contracts, clinical-authority false and donor-bias false. |
| R1-D02 | No single executable guard recorded the cross-plan corrective controls. | Medium | Added `RSR_Four_Plan_Compliance`; it owns no entity and creates no duplicate backend. |
| R1-D03 | Release identity still represented the pre-audit baseline. | Medium | Bumped plugin/package/readme/build to `1.1.0`; schema remains `1.0.0` because no table migration is required. |

Round result: corrected; File 15 remains the sole Radar/trend owner.

## Review round 2 — security, privacy, identity and clinical safety

| ID | Defect | Severity | Correction |
|---|---|---:|---|
| R2-D01 | Explicit File 00 suspension, verification-revocation or security-hold claims were not rechecked by every protected action. | High | Added current fail-closed `account.suspended`, `doctor.suspended`, `doctor.verification_revoked`, `account.security_hold` and `rsr_file00_access_restricted` checks. |
| R2-D02 | Radar search/compare or private REST responses could inherit public cache headers. | High | Added final-priority `no-store`, `no-referrer`, `noindex`, `Vary`, `nosniff` and restrictive Permissions-Policy for sensitive routes. |
| R2-D03 | GET health-research query variants could enter shared caches, referrers or indexes. | High | Added page-level no-store/no-referrer/X-Robots-Tag and `wp_robots` protection while retaining clean canonical URLs. |
| R2-D04 | Saved Study remedy references were bounded but not required to resolve to currently eligible File 06 remedies. | High | Added fail-closed current File 06 reference validation. |
| R2-D05 | Encryption exceptions could escape without a controlled response. | High | Encryption now fails closed; nothing is written when encryption is unavailable. |
| R2-D06 | Portable export exposed the internal numeric WordPress user ID. | Medium | Replaced it with current-account scope; audit object IDs are pseudonymous. |
| R2-D07 | Quoted or weak `If-Match` values could parse as zero. | Medium | Added robust entity-version parsing and client quoted `If-Match`. |
| R2-D08 | Trend reads and management mutations lacked a uniform audit-visible throttle. | Medium | Added subject-scoped limits with 429 and retry metadata. |
| R2-D09 | Urgent red-flag terms received only the generic research disclaimer. | High | Added conservative multilingual escalation away from Radar to appropriate local emergency care, without diagnosis. |

Round result: corrected; private studies remain excluded from public search, AI, feed and trends.

## Review round 3 — Top-20 discovery value, UX and integration

| ID | Defect | Severity | Correction |
|---|---|---:|---|
| R3-D01 | A mapping-count percentage could be mistaken for remedy probability or treatment rank. | High | Removed the percentage and public field; show evidence-match count plus explicit non-clinical meaning. |
| R3-D02 | Per-result explanation was incomplete. | Medium | Added `why_this_result`: query, rubrics, sources, review date and non-prescribing interpretation. |
| R3-D03 | Zero-result handling did not meet the Top-20 recovery requirement. | Medium | Added deterministic recovery, optional AND→OR broadening, alias guidance and governed source-gap link; fabricated results remain false. |
| R3-D04 | Filter selects submitted localized labels rather than canonical keys. | High | Submit canonical `value_key`; legacy labels remain selectable during migration. |
| R3-D05 | File 15 always rendered Back/Home instead of consuming File 20 first. | Medium | Added `sabri_file20_context_controls_markup_v1` with safe duplicate-guarded fallback. |
| R3-D06 | Client status strings were hard-coded and empty keyword edits did not clear stored query keyword. | Medium | Localized client states and always synchronize keyword, including empty values. |
| R3-D07 | Client fetch did not explicitly request no-store/no-referrer. | Medium | Added `cache: no-store` and `referrerPolicy: no-referrer`; server remains authoritative. |
| R3-D08 | Compare references omitted licence in one view and displayed opaque IDs in another. | Low | Display names, references, licence and review date consistently. |
| R3-D09 | Trend window labels were not translatable. | Low | Added translated labels and clearer source-activity/non-prevalence language. |

Round result: corrected; no addictive feed, paid influence, popularity ranking or second search owner was introduced.

## Review round 4 — adversarial regression and release evidence

| ID | Defect | Severity | Correction |
|---|---|---:|---|
| R4-D01 | Existing tests did not freeze the four-plan corrections. | High | Added `tests/four-plan-compliance.php` with positive and negative regression assertions. |
| R4-D02 | Build did not execute the new gate and still packaged `1.0.0`. | High | Build now runs the four-plan gate and produces deterministic `1.1.0` ZIP/SHA/manifest evidence. |
| R4-D03 | Documentation did not distinguish original baseline from corrective release. | Medium | Updated README, plugin readme, status, traceability and this register. |
| R4-D04 | “100% complete” could be misread as staging/live/operational completion. | High | Retained separate statuses: specified, coded, packaged, automated-QA, staging-accepted, live-deployed and operational. |
| R4-D05 | Initial throttle guard could return `true` from `rest_pre_dispatch`, short-circuiting allowed REST requests; sensitive prefix checks omitted the namespace. | Critical | Allowed requests now return the original `null` result; only errors short-circuit. All sensitive prefixes use `/rsr/v1/...`, and regression checks freeze both fixes. |
| R4-D06 | File 26 wrapper queries could reach explanation logic without unwrapping `radar_query`. | Medium | Normalize wrapper queries before explainable-result decoration. |

Round result: corrected at source/repository level. Release criterion is zero known unresolved source blocker/critical defect under the current four-plan corpus; absolute infallibility is not claimed.

## Corrected files

- main plugin, plugin bootstrap and new compliance guard;
- capabilities, Saved Studies and routes;
- Radar, comparison and trend templates;
- public JavaScript and plugin readme;
- root README, status and requirements traceability;
- new four-plan regression test and updated static/build gates;
- this audit register.

## Source-level verdict

| Layer | Verdict |
|---|---|
| Four governing plans traced | Complete |
| File 15 functional source | Complete after corrections |
| Source security/privacy/safety | Complete after corrections |
| Automated/static/four-plan QA | Complete when CI/build is green |
| Deterministic package | Complete when branch build emits ZIP/SHA/manifest |
| Hostinger-equivalent staging | External gate; not claimed by repository audit |
| Live deployment | External gate; not claimed |
| Operational service | External gate; not claimed |

## Residual external gates

- WordPress 7.0.1/PHP 8.3 fresh install and upgrade rehearsal;
- real File 00 immediate revocation and File 06 current-eligibility contract tests;
- File 20/24/25/26 integrated contract tests;
- licensed provider sandbox, quota, cost, outage and deletion tests;
- browser/device/Urdu/Arabic/RTL/screen-reader/zoom/forced-colors matrix;
- measured p75/p95 performance and database/load evidence;
- backup/restore, key decrypt, rollback and cache/index rebuild rehearsal;
- Founder staging acceptance and controlled production authorization.
