# File 15 — Fourth 40-Focus Code/Contract Audit (2026-10-20)

## Source truth

- Audited baseline exact GitHub `main`: `a3b18b684d2529070972f02fa93fc3886d056ed6`, plugin `1.2.3`.
- Correction candidate: `audit/file15-fourth-forty-2026-10-20`; plugin `1.2.4`; database schema **unchanged `1.2.0`**.
- Plan baseline: consolidated three governing plans, previously reviewed File 15 architecture/requirements traceability, companion boundaries File 00/06/16/20/24/26, and this exact source. The separate full dedicated File 15 DOCX was **not available for independent clause-by-clause verification** this turn.
- Forty passes are sequential **source-anchor + full PHP regression-suite** cycles, NOT forty independent adversarial human audits, WordPress integration sessions, staging sessions or live checks.
- Every numbered check must finish and its entire regression suite pass before proceeding to the next; `scripts/forty-round-source-review.sh` produces `dist/second-forty-rounds.log`.

## Frozen newly demonstrated defect and correction

**P05: malformed nested public HTML query input was coerced to string or partially accepted.** The HTML routing path performed `(string)$_POST['keyword']`, `(string)$_GET['cursor']`, `array_map('sanitize_text_field', ...)` on potentially nested arrays, and implicitly accepted mixed nested remedy identifiers. This diverged from File 15's strict REST/domain input validation and could cause PHP warnings or search for literal `Array`, rather than clean refusal.

**Correction:** `includes/class-rsr-routes.php` validates flat scalar HTML leaves; invalid keyword, mode or nested filter adds an intentionally unknown sentinel dimension so the canonical Radar validator rejects the entire query without invoking a search adapter. Nested cursors return null; malformed comparisons return no selected remedy IDs (rather than a partially accepted list). Valid regular HTML values remain supported.

**Regression:** `tests/html-input-regression.php` covers seven cases: malformed keyword, malformed filter, malformed mode, nested comparison, nested cursor, permitted keyword, permitted two-remedy selection. It runs once in build and in **each** of the 40 sequential cycles.

**Q07 (harness only):** the newly added Reflection test helper initially omitted method arguments; the first CI found it. Helper corrected before final repeated run.

## Forty review angles

| Round | Technical focus | Scope of automated pass |
|---:|---|---|
| 1 | Canonical runtime identity | Automated source-anchor review + full regression suite |
| 2 | Domain dimensions | Automated source-anchor review + full regression suite |
| 3 | Schema completeness | Automated source-anchor review + full regression suite |
| 4 | Migration lock | Automated source-anchor review + full regression suite |
| 5 | Atomic rate limits | Automated source-anchor review + full regression suite |
| 6 | File 00 doctor assertion | Automated source-anchor review + full regression suite |
| 7 | File 00 suspension/hold | Automated source-anchor review + full regression suite |
| 8 | Study role gate | Automated source-anchor review + full regression suite |
| 9 | Current remedy DTO contract | Automated source-anchor review + full regression suite |
| 10 | Legacy remedy bridge proof | Automated source-anchor review + full regression suite |
| 11 | Encrypted studies | Automated source-anchor review + full regression suite |
| 12 | Study rotation/recovery | Automated source-anchor review + full regression suite |
| 13 | PII scanner | Automated source-anchor review + full regression suite |
| 14 | Study versioned updates | Automated source-anchor review + full regression suite |
| 15 | Data portability | Automated source-anchor review + full regression suite |
| 16 | Radar AND/OR filters | Automated source-anchor review + full regression suite |
| 17 | Up-to-three comparison | Automated source-anchor review + full regression suite |
| 18 | Symptom/referrer privacy | Automated source-anchor review + full regression suite |
| 19 | Emergency short circuit | Automated source-anchor review + full regression suite |
| 20 | Emergency adapter scrub | Automated source-anchor review + full regression suite |
| 21 | Source-linked Radar evidence | Automated source-anchor review + full regression suite |
| 22 | External evidence deduplication | Automated source-anchor review + full regression suite |
| 23 | Governed Radar mappings | Automated source-anchor review + full regression suite |
| 24 | Source licence governance | Automated source-anchor review + full regression suite |
| 25 | Aggregated provider registration | Automated source-anchor review + full regression suite |
| 26 | Manual provider safety limits | Automated source-anchor review + full regression suite |
| 27 | Daily/weekly/monthly/yearly | Automated source-anchor review + full regression suite |
| 28 | Ingestion source idempotency | Automated source-anchor review + full regression suite |
| 29 | Spam/repost normalization | Automated source-anchor review + full regression suite |
| 30 | Geo/time normalization | Automated source-anchor review + full regression suite |
| 31 | Stable public report paging | Automated source-anchor review + full regression suite |
| 32 | Editorial separation | Automated source-anchor review + full regression suite |
| 33 | Corrections and retractions | Automated source-anchor review + full regression suite |
| 34 | Durable inbound event dedupe | Automated source-anchor review + full regression suite |
| 35 | Reliable outbound delivery | Automated source-anchor review + full regression suite |
| 36 | File 20 shell delegation | Automated source-anchor review + full regression suite |
| 37 | File 26 public read boundary | Automated source-anchor review + full regression suite |
| 38 | File 16 approved retrieval boundary | Automated source-anchor review + full regression suite |
| 39 | File 24 assurance boundary | Automated source-anchor review + full regression suite |
| 40 | Release and CI evidence | Automated source-anchor review + full regression suite |

## Remaining cross-owner/operational gates

1. File 06 canonical v1 remediation/provider event-ID and native eligibility adapter remains open in [File 06 Issue #10](https://github.com/majidhussainqadri1-dot/06-homeopathy-encyclopedia-foundation/issues/10). Do **not** treat a WordPress published post as a validated eligible remedy without File 06 owner assertion.
2. Full File 26 searchable connector manifest, File 16 public-only educational retrieval and truly end-to-end governance/retraction integration are not live-proven.
3. Actual deployed plugin version/build/checksum, `wp_options` schema/migration state, relevant database tables, logs and staging/Live browser acceptance were not available; source-only defects must not be described as live-resolved.
4. Backup/restore, migration upgrade and rollback drill, two separate staging reviews and Founder approval remain release gates; deployment must be withheld until proved.

## Final source vs live state

- Repository baseline HEAD: `a3b18b684d2529070972f02fa93fc3886d056ed6`
- Corrective candidate: plugin `1.2.4`
- Repository target DB schema: `1.2.0` unchanged
- Deployed Version: **unverified**
- DB Version: **unverified**
- Migration State: **unverified**
- Live Verification Status: **not performed**
- New source product defects: **1 detected, 1 patched**; QA harness: **1 detected, 1 patched**; remaining deployment/cross-file proof gates **open**.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
