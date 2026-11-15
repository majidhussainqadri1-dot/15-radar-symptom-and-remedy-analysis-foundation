# File 15 Status — v1.2.5 proposed source candidate (2026-11-15)

| Layer | Evidence / current status |
|---|---|
| Audited `main` baseline | `75678c0518e59921d2d3330b9008121afc4ae1b8` — plugin 1.2.4 / schema 1.2.0 |
| Corrective candidate | `fix/file15-v123-signal-governance-20261115` — plugin 1.2.5 / proposed schema 1.2.1 |
| F15 functional 001–018 | Existing code represented; external contracts not verified end-to-end |
| F15 non-functional 001–010 | Source/test framework present; release evidence not complete |
| Source signal taxonomy | Source classification, report grouping and guarded migration proposed |
| Disease Intelligence | Incomplete: canonical disease ID, clinical denominator/case definition and approved medical source ownership require a separate contract |
| File 06 canonical bridge | Open: File 06 Issue #10; File 15 PR #12 is draft, unmerged |
| File 26 public connector | Open: File 15 PR #13 and File 26 PR #22 are draft, unmerged |
| External source adapters | Built-in manual provider only proven by repository; no licensed live provider acceptance |
| Current candidate CI/build | Must be checked against the exact candidate HEAD; previous green runs do not establish candidate acceptance |
| Staging | No evidence of WordPress/MariaDB, role, provider, browser or migration acceptance |
| Live deployment | Unverified; **do not deploy or merge until owner acceptance** |
| DB/migration/live evidence | Not available from connected site; cannot assert production parity |

## Historical audit

- 2026-08-06 baseline forty-pass record: 32 rounds found defects and corrected them; 8 clean rounds.
- 2026-10-20 v1.2.4 main resolved the demonstrated malformed HTML input source defect and one test helper defect.
- 2026-11-15 v1.2.5 draft separates research activity signals, addresses stale status and data-model documentation, and adds test coverage. This does not close live, staging or File 06/26 issues.

## Release gates

1. Pin exact File 06, File 15 and File 26 owner contract versions, complete owner-reviewed adapters and event/correction/tombstone behavior.
2. Review the proposed source taxonomy and clinical source ownership before introducing epidemiological or surveillance datasets.
3. Verify schema 1.2.0 → 1.2.1 and fresh install on real disposable MariaDB, replay/reconciliation, restore and rollback.
4. Run two independent staging reviews, privacy/pentest/accessibility/performance checks and Founder acceptance.
5. Freeze the live plugin source checksum, `wp_options`, tables/columns, migrations and logs; deploy exact package only with rollback.
6. Live re-test and confirm deployed checksum + schema parity; only then mark resolved.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
