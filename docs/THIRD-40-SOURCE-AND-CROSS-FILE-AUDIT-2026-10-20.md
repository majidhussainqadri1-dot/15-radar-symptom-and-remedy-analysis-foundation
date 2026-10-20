# File 15 — Third independent 40-pass code review (2026-10-20)

## Evidence discipline

Audit base: `main c9d6c6d8c4b5ee2a449882b2d78d14eb69afafef` (plugin 1.2.2 / target DB schema 1.2.0).
Candidate: `audit/file15-third40-input-governance-2026-10-20` (plugin 1.2.3 / schema 1.2.0 unchanged).
Primary governing documents examined: the uploaded consolidated Three-Central-Plans Governing Master Plan, standalone Traffic Analytics / Disease Intelligence plans, uploaded Files 14/16/17/25 companion plans, File 15 repository architecture and requirements-traceability, plus current native File 06 contract material. **The standalone authoritative File 15 Word master is not present in the uploaded file set. Full line-by-line certification against that missing document is not claimed.**

Exact source scope: all 40 named reviews below, a complete repeated PHP domain/static/clinical/compliance suite after each review, additional targeted human-readable source inspection, exact-HEAD GitHub Actions, package and hash.
**No live server or WordPress database access:** this is not deployment/staging/provider acceptance, and not 40 human-run end-to-end tests.

## New verified code defects found and corrected

| ID | Baseline defect | Implemented correction | Evidence |
|---|---|---|---|
| T3-01 | `validate_radar_query` coerced array keyword/mode and nested filter leaves to strings, risking warnings or misleading `Array` tokens; `validate_comparison_ids` likewise coerced malformed values | Type-check keyword/mode/filter container/leaves and comparison IDs, return machine-readable invalid-type errors, and reject Boolean remedy IDs | `tests/run.php`, 40-cycle regression |
| T3-02 | English `chest pain` correctly diverted, but common Urdu `سینے میں درد` and some breathing phrases were absent from red-flag signal list | Broaden explicit common Urdu emergency signal phrases; diversion still occurs before any remedy search | `tests/clinical-boundary-regression.php`, 40-cycle regression |
| T3-03 | File 06 `published` / `eligible` checks used `empty()` and could interpret nonempty `"false"` adapter strings as true | Require exact Boolean `true` for both source-owner eligibility assertions | clinical-boundary predicate gate, 40-cycle regression |

All changes are repository-only. Version 1.2.3 distinguishes the new code from earlier 1.2.2. Existing database target remains 1.2.0; no new table/column migration is presumed or claimed.

## Third forty-pass source/test lenses

Every check runs a distinct source-contract lens and *then* the entire existing domain, security, clinical-boundary, static, four-plan and historical regression suite. A source symbol/anchor PASS is not itself proof of a working cross-file integration.

| # | Verification dimension | Source | Test type |
|---:|---|---|---|
| 1 | Canonical runtime identity | radar-symptom-remedy-analysis.php | CI source gate |
| 2 | Domain dimensions | includes/class-rsr-domain.php | CI source gate |
| 3 | Schema completeness | includes/class-rsr-db.php | CI source gate |
| 4 | Migration lock | includes/class-rsr-db.php | CI source gate |
| 5 | Atomic rate limits | includes/class-rsr-hardening.php | CI source gate |
| 6 | File 00 doctor assertion | includes/class-rsr-capabilities.php | CI source gate |
| 7 | File 00 suspension/hold | includes/class-rsr-capabilities.php | CI source gate |
| 8 | Study role gate | includes/class-rsr-study-service.php | CI source gate |
| 9 | Current remedy DTO contract | includes/trait-rsr-radar-retrieval.php | CI source gate |
| 10 | Legacy remedy bridge proof | includes/trait-rsr-radar-retrieval.php | CI source gate |
| 11 | Encrypted studies | includes/class-rsr-crypto.php | CI source gate |
| 12 | Study rotation/recovery | includes/class-rsr-crypto.php | CI source gate |
| 13 | PII scanner | includes/class-rsr-pii-scanner.php | CI source gate |
| 14 | Study versioned updates | includes/class-rsr-study-service.php | CI source gate |
| 15 | Data portability | includes/class-rsr-privacy.php | CI source gate |
| 16 | Radar AND/OR filters | includes/class-rsr-domain.php | CI source gate |
| 17 | Up-to-three comparison | includes/class-rsr-domain.php | CI source gate |
| 18 | Symptom/referrer privacy | includes/class-rsr-four-plan-compliance.php | CI source gate |
| 19 | Emergency short circuit | includes/trait-rsr-radar-public.php | CI source gate |
| 20 | Emergency adapter scrub | includes/class-rsr-four-plan-compliance.php | CI source gate |
| 21 | Source-linked Radar evidence | includes/trait-rsr-radar-retrieval.php | CI source gate |
| 22 | External evidence deduplication | includes/trait-rsr-radar-retrieval.php | CI source gate |
| 23 | Governed Radar mappings | includes/trait-rsr-radar-governance.php | CI source gate |
| 24 | Source licence governance | includes/trait-rsr-trend-serialization.php | CI source gate |
| 25 | Aggregated provider registration | includes/class-rsr-provider-registry.php | CI source gate |
| 26 | Manual provider safety limits | includes/providers/class-rsr-manual-provider.php | CI source gate |
| 27 | Daily/weekly/monthly/yearly | includes/class-rsr-trend-service.php | CI source gate |
| 28 | Ingestion source idempotency | includes/trait-rsr-trend-source-ingestion.php | CI source gate |
| 29 | Spam/repost normalization | includes/trait-rsr-trend-processing.php | CI source gate |
| 30 | Geo/time normalization | includes/trait-rsr-trend-processing.php | CI source gate |
| 31 | Stable public report paging | includes/trait-rsr-trend-reports.php | CI source gate |
| 32 | Editorial separation | includes/trait-rsr-trend-reports.php | CI source gate |
| 33 | Corrections and retractions | includes/trait-rsr-trend-reports.php | CI source gate |
| 34 | Durable inbound event dedupe | includes/class-rsr-events.php | CI source gate |
| 35 | Reliable outbound delivery | includes/class-rsr-events.php | CI source gate |
| 36 | File 20 shell delegation | includes/class-rsr-routes.php | CI source gate |
| 37 | File 26 public read boundary | includes/class-rsr-plugin.php | CI source gate |
| 38 | File 16 approved retrieval boundary | ../docs/ARCHITECTURE.md | CI source gate |
| 39 | File 24 assurance boundary | ../docs/ARCHITECTURE.md | CI source gate |
| 40 | Release and CI evidence | ../scripts/build.sh | CI source gate |

## Cross-file blockers (not certified as corrected)

1. **File 06 canonical identity/events:** Native File 06's `sabri_domain_event` envelope, current File 15's `sabri_platform_event_v1` reception, stable affected remedy IDs, actual `rsr_file06_get_remedies` owner implementation and review/correction/retraction reconciliation require an owner-approved adapter and real integration tests. File 06 issue #10 remains OPEN.
2. **Disease Intelligence:** The supplementary approved plan requires that epidemiological metrics, public search interest, surveillance and news signals be independently labeled; File 15 source-linked trend activity must never be passed off as disease prevalence. An explicit versioned canonical-disease-ID/source-type contract with File 06 and File 26 still requires approval and staging proof.
3. **File 16 / File 26 / File 20 / File 24 / File 25:** File 16 receives only approved public retrieval, File 26 owns search/rank projections, File 20 shell/routes, File 25 visual components, and File 24 audit/assurance. No native owner data/schema writes are authorized via File 15. Presently no jointly executed real WordPress staging contract test verifies all these interactions.
4. **Live Reality Freeze:** Exact deployed plugin files/checksum, active plugin version, wp_options schema version, database columns and rows, migration lock state, runtime errors/logs and active dependencies were not available. Do not label the live defect `Resolved`; repository testing cannot establish deployed parity.

## Release evidence

The CI-reported exact candidate commit, successful forty-cycle count, job matrix, artifact SHA-256, failed/skipped checks and regression totals must be independently copied from the final GitHub Actions run after this report is committed. Do not reuse the earlier 1.2.2 SHA for 1.2.3.
Release order: fix → test → deterministic package → staged integration/restore → Founder authorization → deploy → live re-test → parity confirmation.

**Reported counts:** 3 new confirmed product-source defects, 3 repository corrections, external integration/live blockers remain OPEN. `Repository HEAD` is distinct from `Deployed Version`, `DB Version`, `Migration State`, and `Live Verification Status`.
