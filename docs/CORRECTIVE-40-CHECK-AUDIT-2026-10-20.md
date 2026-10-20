# File 15 — 2026-10-20 Corrective Audit and 40-Check Ledger

## Evidence boundary

- Repository: `majidhussainqadri1-dot/15-radar-symptom-and-remedy-analysis-foundation`.
- Frozen baseline `main`: `4e5d768d637052063bf37a038592b8f7b27f120a`.
- Correction branch: `audit/file15-current-40-checks-2026-10-20`.
- Source baseline: plugin 1.2.0 / schema 1.2.0.
- Corrective candidate: plugin 1.2.1 / schema 1.2.0 (no DB migration intended).
- Source verification: 40 **different focused static checks**, not 40 full end-to-end executions and not a repeat of the historical 2026-08-06 forty-round review.
- Prepatch findings: 36 positive source signals, 3 reproducible repository gaps, 1 external unverified gate.

## Check-by-check evidence

A PASS-like source signal means the relevant declaration or code path is present in the frozen repository; it does **not** establish that live integrations, runtime behavior or security have passed dynamic acceptance.

| Check | Source/contract subject | Result |
|---:|---|---|
| 01 | Plugin runtime version | SOURCE SIGNAL PRESENT |
| 02 | Schema version | SOURCE SIGNAL PRESENT |
| 03 | Minimum PHP gate | SOURCE SIGNAL PRESENT |
| 04 | Twelve canonical DB tables | SOURCE SIGNAL PRESENT |
| 05 | Schema upgrade mutex | SOURCE SIGNAL PRESENT |
| 06 | Missing-table verification | SOURCE SIGNAL PRESENT |
| 07 | Deterministic ZIP builder | SOURCE SIGNAL PRESENT |
| 08 | CI PHP matrix | SOURCE SIGNAL PRESENT |
| 09 | Four-plan test runtime assertion | REPAIRED IN PR — pending CI |
| 10 | Four-plan test build inclusion | REPAIRED IN PR — pending CI |
| 11 | File 00 verified-doctor claim | SOURCE SIGNAL PRESENT |
| 12 | Current suspension/security hold checks | SOURCE SIGNAL PRESENT |
| 13 | Doctor-only REST access | SOURCE SIGNAL PRESENT |
| 14 | File 06 remedy eligibility adapter | SOURCE SIGNAL PRESENT |
| 15 | Encyclopedia correction invalidation | SOURCE SIGNAL PRESENT |
| 16 | Maximum three remedies | SOURCE SIGNAL PRESENT |
| 17 | Saved Study row ownership | SOURCE SIGNAL PRESENT |
| 18 | PII scanning of studies | SOURCE SIGNAL PRESENT |
| 19 | Authenticated note encryption | SOURCE SIGNAL PRESENT |
| 20 | Encryption previous-key rotation | SOURCE SIGNAL PRESENT |
| 21 | Private HTTP cache control | SOURCE SIGNAL PRESENT |
| 22 | No-referrer medical query policy | SOURCE SIGNAL PRESENT |
| 23 | POST medical research forms | SOURCE SIGNAL PRESENT |
| 24 | API rate limiter | SOURCE SIGNAL PRESENT |
| 25 | Source licence allowlist | SOURCE SIGNAL PRESENT |
| 26 | Aggregate-only provider contract | SOURCE SIGNAL PRESENT |
| 27 | Provider secret screening | SOURCE SIGNAL PRESENT |
| 28 | Four trend windows | SOURCE SIGNAL PRESENT |
| 29 | Ingestion idempotency | SOURCE SIGNAL PRESENT |
| 30 | Outbox row lease | SOURCE SIGNAL PRESENT |
| 31 | Editorial state machine | SOURCE SIGNAL PRESENT |
| 32 | Separation of report approval | SOURCE SIGNAL PRESENT |
| 33 | Correction and retraction | SOURCE SIGNAL PRESENT |
| 34 | Trend sort/cursor consistency | REPAIRED IN PR — pending CI |
| 35 | File 20 shell integration | SOURCE SIGNAL PRESENT |
| 36 | File 25 presentation boundary | SOURCE SIGNAL PRESENT |
| 37 | File 26 public discovery contract | SOURCE SIGNAL PRESENT |
| 38 | Emergency red-flag escalation | SOURCE SIGNAL PRESENT |
| 39 | Source provenance metadata | SOURCE SIGNAL PRESENT |
| 40 | Real companion/staging acceptance | EXTERNAL UNVERIFIED |
 
## Three initial source defects and corrective actions

1. **Stale version assertion:** `tests/four-plan-compliance.php` required plugin `1.1.0` against actual `1.2.0`. Corrected to the unique new `1.2.1` release identity. Schema remains `1.2.0`.
2. **Unexecuted four-plan gate:** `scripts/build.sh` ran domain/static/forty-round checks but omitted `tests/four-plan-compliance.php`. Added it as a blocking build step, with a retained log in `dist/`.
3. **Inconsistent public report pagination:** `public_reports_page` ordered by `published_at DESC, id DESC` but used `id < cursor_id` alone. This can skip a report when publication sequence differs from row creation sequence. Replaced it with a complete time/ID keyset, consistent fallback for null published_at, strictly validated bounded cursors, and a legacy v1.2.0 cursor bridge. Added pure cursor tests and source regression guards.

Release integrity: v1.2.1 distinct plugin/build/CI/readme metadata; **no database version bump and no same-version ZIP substitution**.

## Additional defect discovered by actual CI

4. **Broken PHP assertions in the baseline static test:** the test contained interpolated strings like `"$state['nodes'] > 1000"`, `"$capabilities['privacy'] === 'aggregated_only'"` and `"$base['schema']..."`. This caused real PHP lint errors on 8.1, 8.3 and 8.4 and prevented any subsequent test from running. All identified invalid string literals in `tests/static-regression.php` were converted into literal-safe single-quoted PHP strings. The patch requires fresh CI confirmation.

**Defect total after executing CI: 4 repository defects** (3 identified by static audit, 1 discovered by runtime CI). None are reported resolved in staging/live.

## Cross-file dependency review and newly identified external blocker

- File 00 remains identity owner; File 15 consumes current identity/verification assertions rather than copying canonical account data.
- File 06 remains canonical remedy/encyclopedia owner. Its `main` root exposed only a README at audit time; the required `rsr_file06_get_remedies`/eligibility adapter cannot therefore be called integrated from this evidence. **The exact deployed File 06 runtime is unknown**. Import the approved File 06 baseline on its own governed branch and validate its published-remedy DTO contract independently; do not fabricate File 06 data in File 15.
- File 20 owns shell routes; File 25 owns shared public visual presentation; File 26 owns federated search/knowledge ranking. File 15 may publish public trend DTOs and events but must not duplicate canonical disease metrics or clinical ranks.
- The later Global Disease Intelligence Plan (2026-08-10) requires File 06 disease identity, File 15 verified trend updates, File 26 metric-specific ranking, and File 25 presentation. This is a **federated follow-up integration**; repository-only File 15 review cannot claim the full disease ranking experience is implemented.
- The WordPress site is not connected to WPVibe in the current integration. No deployed-plugin checksum, live DB/schema, staging, clinic workflows, restore/rollback rehearsal, full browser accessibility or clinical/provider integration could be verified.

## Quality and release gates

Before merging, require exact corrective-branch CI (all PHP matrix jobs), `bash scripts/build.sh`, new cursor regression assertions, version 1.2.1 deterministic package SHA and manifest, and PR diff review. A green CI covers source/automated checks only.

Before live deployment: obtain exact active deployed artifact/checksum and DB schema, compare with source package, inspect migration state, run File 00/06/20/24/25/26 integrated journeys on staging, then backup/restore and owner acceptance. No deploy or production status claim from this document.

## Incident-style status matrix

| Dimension | Verified status |
|---|---|
| Repository HEAD baseline | `4e5d768d637052063bf37a038592b8f7b27f120a` |
| Correction target | Plugin `1.2.1`, schema `1.2.0` |
| Deployed version | Unknown / unverified |
| Live database version | Unknown / unverified |
| Migration state | Unknown / unverified |
| Live verification | Not performed |
| Source defects found | 4 (3 static + 1 CI) |
| Source defects coded in corrective PR | 4 |
| Source corrections release-verified | Pending exact-head CI |
| Remaining external blockers | File 06 integration / staging-runtime and deployment evidence |

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
