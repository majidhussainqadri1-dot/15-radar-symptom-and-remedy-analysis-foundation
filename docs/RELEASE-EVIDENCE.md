# Release Evidence — 1.1.0

Generated: `2026-08-06T18:24:54Z`

Verified source merge: `2b6205d98cc413cb8593efe7ca4f4d0d81bf1c47`

## Automated result

- PHP lint: PASS (`29` plugin PHP files plus tests)
- JavaScript syntax: PASS
- Domain/security tests: PASS (`33` assertions)
- Static requirement/regression tests: PASS (`157` checks)
- Four-plan compliance regression tests: PASS (`24` positive controls and `2` negative regressions)
- ZIP path/integrity validation: PASS (`34` entries)
- Plugin source files: `34`
- Plugin source bytes: `332876`
- Package: `15-radar-symptom-and-remedy-analysis-foundation-1.1.0.zip`
- Package SHA-256: `268e1f529d949e280c57840e0270195acaa69ba76799f5aaf000dcc104117c50`
- Source manifest: `dist/SOURCE-MANIFEST.sha256`

## Four-plan corrective scope

The release hardens File 15 against the Definitive Master Plan v3.0, the recovered directive register v2.1, the Continuous Value/Top-20 Superset plan, and File 15's dedicated master plan. Corrections include fail-closed current claim checks, health-query no-store/no-referrer handling, explainable non-clinical result ordering, zero-result recovery without fabricated remedies, red-flag escalation, current File 06 remedy validation for Saved Studies, encryption failure safety, File 20 context-control consumption, canonical schema keys, localized client states, File 26 wrapper normalization, and explicit cross-file ownership metadata.

## Covered implementation

All functional requirements F15-FR-001 through F15-FR-018 and source-level controls for F15-NFR-001 through F15-NFR-010 are represented in the canonical source and traceability matrix. Version 1.1.0 adds regression evidence for the four-plan audit findings.

## Evidence qualification

The complete deterministic build was reproduced in the available isolated build environment from the merged plugin source and repository tests. GitHub Actions did not create a new run from the connector-authored commits; therefore no new GitHub-hosted Actions-green claim is made. The prior v1.0.0 main Actions run remains historical evidence only.

## Explicitly separate external gates

This evidence does not claim Hostinger/WordPress staging acceptance, production deployment, real provider authorization, operational staffing or live service levels. Those require the external acceptance matrix and Founder approval.
