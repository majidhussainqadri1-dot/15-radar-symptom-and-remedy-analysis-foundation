# Release Evidence — 1.2.0

Generated: `2026-08-06T19:41:35Z`

## Forty-round result

- Total sequential review/fix rounds: **40**
- Rounds in which defects were found and corrected before the next round: **32**
- Rounds in which no new defect was found: **8**

## Automated result

- PHP lint: PASS (`37` plugin PHP files; `41` total PHP files including tests)
- JavaScript syntax: PASS (`2` files)
- Domain/security tests: PASS (`39` assertions)
- Static requirement/regression tests: PASS (`165` checks)
- Forty-round regression tests: PASS (`26` checks)
- Deterministic double build: PASS (byte-identical)
- ZIP path/integrity and clean-extract syntax: PASS (`42` entries)
- Plugin source files: `42`
- Plugin source bytes: `372139`
- Package: `15-radar-symptom-and-remedy-analysis-foundation-1.2.0.zip`
- Package SHA-256: `6bd273de2509bf2e7e3697a66f2849e57ab7af501000901b64500d2445007ed1`
- Source manifest: `dist/SOURCE-MANIFEST.sha256`

## Corrective scope

Version 1.2.0 adds atomic schema upgrade verification, durable event inbox and row-level outbox leases, database-atomic rate limiting, production-grade encryption-key requirements and rotation, decrypt-failure overwrite protection, bounded PII/log/provider ingestion, stricter source/licence/provenance gates, transactional publish/correct/retract operations, approval separation, stable pagination, current eligibility revalidation, POST health-query transport, lazy schema loading and expanded regression evidence.

## Evidence qualification

This document establishes source implementation, deterministic package integrity and automated checks in the available build environment. It does not establish Hostinger staging, real companion-plugin/provider contracts, measured browser/accessibility/performance acceptance, backup/restore rehearsal, production deployment or operational service. Those remain explicit external gates.
