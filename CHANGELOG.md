# Changelog

## 1.2.2 — 2026-10-20 (second corrective audit)

- Urgent red flags now suppress all Radar recommendations/cursors before File 06 lookup, including defensive public-contract decoration.
- Restored proper privacy/noindex protection for single-keyword POST/GET health searches.
- External Radar evidence must be date-valid, source-linked, nonempty and deduplicated; provider-supplied evidence counts are not blindly trusted.
- Legacy File 06 remedy bridge requires explicit native-owner per-remedy eligibility approval, default deny.
- Added medical boundary regression suite and forty sequential automated source/test review rounds.
- Database schema is unchanged at 1.2.0; staging, deployed artifact and integration runtime remain unverified.

## 1.2.1 — 2026-10-20 (corrective candidate)

- Fixed public trend report pagination to keyset on publication-time/ID tuple rather than ID alone; accepts prior ID-only cursors.
- Restored the four-plan regression gate in the release builder and updated stale version assertions.
- Added regression checks for cursor parsing, invalid dates, upgrade compatibility and SQL ordering.
- This is source-review candidate evidence; real staging, cross-file contracts and deployment parity remain separate gates.

## 1.0.0 — 2026-08-06

- Replaced the incomplete 0.1.x foundation with the canonical File 15 implementation.
- Added all F15-FR-001 through F15-FR-018 functional requirements.
- Added all F15-NFR-001 through F15-NFR-010 release-gate controls at source/documentation level.
- Added ten-table versioned data model, capability model, audit, observability and outbox.
- Added public Radar, comparison, private studies, public trends, report detail and operations routes.
- Added PII prevention, authenticated encryption, private noindex/no-cache, privacy export and erasure.
- Added source registry, provider abstraction, quotas, rate limits, health, cost model and credential references.
- Added scheduled/manual idempotent ingestion, normalization, scoring, reports and editorial transitions.
- Added correction/retraction events and public-only integration contracts.
- Added automated tests, CI, build, checksums, manifest, security/operations/migration/staging documentation.
