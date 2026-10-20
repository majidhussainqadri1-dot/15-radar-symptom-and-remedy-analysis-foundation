# File 15 — Second Forty Sequential Source/Regression Review, 20 October 2026

## Provenance, scope, limitation

- Reviewed exact pre-correction **main**: `c5490227904fa8c08f5d1451dc1a5e831ed9fe9f` (Plugin 1.2.1, schema 1.2.0).
- Corrective branch: `audit/file15-forty-round-2026-10-20-pass2` (Plugin 1.2.2; schema unchanged 1.2.0).
- Governing evidence: three consolidated central plans supplied to this Project; separately compiled Traffic Analytics and Disease Intelligence plans; File 15 architectural requirements and earlier four-plan traceability; native GitHub source read. **The independent full dedicated File 15 v1.0 Word master was not individually retrieved in this turn; full clause-by-clause independent parity is NOT claimed.**
- Method: initial substantive targeted human-readable source/plan review → identify and freeze product defects → correct in isolated branch → develop independent regression tests → execute forty sequential passes of a distinct source/ownership lens and the complete existing source/test suites. Failures in the new QA harness were resolved and the loop restarted; the last exact-branch CI completed rounds 01–40 consecutively.
- External limitation: this is static/source and PHP domain regression evidence, **not 40 independent WordPress staging, MySQL, provider or clinical end-to-end reviews**.

## Product defects (4), code corrections (4)

| ID | Verified baseline cause | Corrected code | Re-test gate |
|---|---|---|---|
| P01 | Emergency/red-flag search decorated results with a warning but still delivered remedy suggestions and next cursors | `trait-rsr-radar-public.php` returns empty urgent-care diversion before File 06 query; `class-rsr-four-plan-compliance.php` also strips third-party results/cursors and recovery suggestions | clinical-boundary regression and 40-cycle QA |
| P02 | `isset($_GET['keyword'], $_POST['keyword'])` required simultaneous GET+POST instead of one, leaving single-keyword health pages outside query no-store/no-referrer/noindex check | Corrected both HTML header and robots predicates | two occurrence checks and 40-cycle QA |
| P03 | External Radar adapter could claim arbitrary `match_count` or return remedy candidates with no verified source-linked mapping; review dates were only regex-checked | Validate source ID, licence, complete nonfuture calendar review date; reject empty mapping evidence, dedupe evidence and derive count from retained mappings | clinical-boundary/source regression and 40-cycle QA |
| P04 | Published legacy WordPress `HE_Content` remedy post bypassed File 06's current explicit `eligible` DTO assertion | Compatibility bridge defaults DENY unless File 06 owner supplies `rsr_file06_legacy_remedy_eligible` approval for each legacy remedy | source boundary gate and 40-cycle QA |

**Clinical risk:** these changes prioritize avoiding unsupported recommendations over false claims of available remedies. Real File 06 integration must explicitly implement/validate the bridge before claiming completed remedy search.

## Internal QA harness findings corrected during source release verification

- Q01: isolated test lacked a WordPress `esc_url_raw` stub.
- Q02: assertions used PHP null coalescing to test explicitly-null cursors, unintentionally treating null as absent.
- Q03: new forty-lens matrix initially escaped PHP variables and single quotes incorrectly; switched to literal JSON/nowdoc parsing.
- Q04: root-level architecture documents were incorrectly addressed as plugin-local paths for rounds 38/39.
- Q05: a pre-existing static regression test interpolated an undefined `$route` variable in an assertion; converted to a literal check.
- Q06 (post-merge, exact-main CI): the tamper test replaced the last two Base64 characters with `AA`, which sometimes did not alter authenticated binary bytes. A real decoded ciphertext byte is now flipped deterministically before re-encoding; prevents spurious crypto-test failures across repeated runs.

These are **six test/harness defects**, not five additional production vulnerabilities. Failures were corrected before the next successful complete 40-cycle run.

## Final forty sequential source-review cycles

Each numbered round ran its distinct plan/source-ownership presence check **followed by** domain/security, red-flag/privacy/provenance, static regression, four-plan regression and the historical forty-round regression suite. Each cycle is logged in CI `dist/second-forty-rounds.log`. Every assertion is reproducible from the exact source; label PASS does not mean live integration passed.

| Round | Review lens | Final source/QA result |
|---:|---|---|
| 01 | Canonical runtime identity | PASS — code anchors + entire test suite |
| 02 | Domain dimensions | PASS — code anchors + entire test suite |
| 03 | Schema completeness | PASS — code anchors + entire test suite |
| 04 | Migration lock | PASS — code anchors + entire test suite |
| 05 | Atomic rate limits | PASS — code anchors + entire test suite |
| 06 | File 00 doctor assertion | PASS — code anchors + entire test suite |
| 07 | File 00 suspension/hold | PASS — code anchors + entire test suite |
| 08 | Study role gate | PASS — code anchors + entire test suite |
| 09 | Current remedy DTO contract | PASS — code anchors + entire test suite |
| 10 | Legacy remedy bridge proof | PASS — code anchors + entire test suite |
| 11 | Encrypted studies | PASS — code anchors + entire test suite |
| 12 | Study rotation/recovery | PASS — code anchors + entire test suite |
| 13 | PII scanner | PASS — code anchors + entire test suite |
| 14 | Study versioned updates | PASS — code anchors + entire test suite |
| 15 | Data portability | PASS — code anchors + entire test suite |
| 16 | Radar AND/OR filters | PASS — code anchors + entire test suite |
| 17 | Up-to-three comparison | PASS — code anchors + entire test suite |
| 18 | Symptom/referrer privacy | PASS — code anchors + entire test suite |
| 19 | Emergency short circuit | PASS — code anchors + entire test suite |
| 20 | Emergency adapter scrub | PASS — code anchors + entire test suite |
| 21 | Source-linked Radar evidence | PASS — code anchors + entire test suite |
| 22 | External evidence deduplication | PASS — code anchors + entire test suite |
| 23 | Governed Radar mappings | PASS — code anchors + entire test suite |
| 24 | Source licence governance | PASS — code anchors + entire test suite |
| 25 | Aggregated provider registration | PASS — code anchors + entire test suite |
| 26 | Manual provider safety limits | PASS — code anchors + entire test suite |
| 27 | Daily/weekly/monthly/yearly | PASS — code anchors + entire test suite |
| 28 | Ingestion source idempotency | PASS — code anchors + entire test suite |
| 29 | Spam/repost normalization | PASS — code anchors + entire test suite |
| 30 | Geo/time normalization | PASS — code anchors + entire test suite |
| 31 | Stable public report paging | PASS — code anchors + entire test suite |
| 32 | Editorial separation | PASS — code anchors + entire test suite |
| 33 | Corrections and retractions | PASS — code anchors + entire test suite |
| 34 | Durable inbound event dedupe | PASS — code anchors + entire test suite |
| 35 | Reliable outbound delivery | PASS — code anchors + entire test suite |
| 36 | File 20 shell delegation | PASS — code anchors + entire test suite |
| 37 | File 26 public read boundary | PASS — code anchors + entire test suite |
| 38 | File 16 approved retrieval boundary | PASS — code anchors + entire test suite |
| 39 | File 24 assurance boundary | PASS — code anchors + entire test suite |
| 40 | Release and CI evidence | PASS — code anchors + entire test suite |

## Final exact corrective branch evidence

- GitHub Actions run: `39454928827`, exact branch HEAD `2306eda3e5d2a729a69731249181322769e7a3aa` (before this documentation commit).
- Three PHP matrix jobs: **PHP 8.1 / 8.3 / 8.4 all SUCCESS**.
- Domain/security: **45 assertions expected after deterministic byte-flip test; exact-HEAD CI pending**.
- Static regression: **167 checks PASS**.
- Historical 40-round controls: **26 PASS**.
- Four-plan: **24 positive + 2 negative PASS**.
- New clinical safety/privacy/provenance: **16 checks PASS**.
- Second 40 sequential source/test cycles: **40/40 PASS**.
- Deterministic installable package: `15-radar-symptom-and-remedy-analysis-foundation-1.2.2.zip`.
- ZIP SHA-256: `b812eb5853458eb72ba013fd41bb0e79f29bf14b70d3863b21ccdc2e6cbbdd19`. This is **the inner plugin package**, not the distinct GitHub Actions artifact wrapper ZIP.

## Cross-file reconciliation and remaining blockers

| Related owner | Known evidence | Acceptance still required |
|---|---|---|
| File 00 | File 15 checks verified-doctor claims, revocations and holds via integration adapters | Live File 00 actual identity assertion contract, provider runtime and fail-closed negative journeys |
| File 06 | Its reviewed development branch contains canonical Encyclopedia code and events; current `main` was only initialization README. File 06 `sabri_domain_event` envelope and current File 15 `sabri_platform_event_v1` consumer do **not** establish an interoperable ID-bearing production contract | Owner-authenticated v1 remedy DTO/eligible lookup, canonical current disease IDs, corrected/retracted/merged event payload and staged end-to-end tests |
| File 16 | May read only public approved File 15 results | Public-only AI retrieval and source cite acceptance; no private Saved Studies |
| File 20 | Owns shell/nav, File 15 offers context-control contract | Actual deployed shared shell route, safety and navigation smoke tests |
| Files 24/25/26 | Assurance, public visual presentation and federated search/ranks remain native owners | Complete connector manifest, click-time public eligibility, disease metric-specific ranks, canonical route and correction reconciliation |
| Global Disease Intelligence | File 15 owns governed trend windows/aggregate observations | Verified signal-type taxonomy (epidemiology/search/news distinct), disease ID reconciliation and separate File 26 rank snapshot |
| Hostinger Live/Staging | No WordPress/DB management connection was available in the current audit | Freeze active plugin/deployed build, DB version/tables/migration state, checksums and runtime logs; parity audit, approved staging and live re-test |

**No deployment, integration activation, migration, host credential or live modification is claimed.**
If a live incident is raised: **Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**

## Required incident/release state

- Repository pre-audit HEAD: `c5490227904fa8c08f5d1451dc1a5e831ed9fe9f`.
- Corrective source plugin: `1.2.2`.
- Repository schema target: `1.2.0` (unchanged).
- Deployed Version: **Unknown**.
- Live DB version: **Unknown**.
- Migration State: **Unknown**.
- Live Verification Status: **Not done**.

**Known source corrections: 4/4; QA/harness corrections: 6/6; final 40 automated sequential source cycles PASS; staging/live external gates OPEN.**
