# File 15 — Radar, Symptom/Remedy Research and Trend Intelligence

Production-oriented WordPress source for **File 15** of the **Sabri Social Homeopathy Platform**.

## Canonical boundary

File 15 owns:

- the governed Radar rubric/search schema;
- source-linked educational remedy mappings and one-to-three remedy comparison;
- verified-doctor-only, non-patient private Saved Studies;
- trend-source adapters and governance;
- daily, weekly, monthly, and yearly aggregate ingestion;
- normalization, deduplication, scoring, confidence, freshness, and reproducibility;
- report editorial approval, publication, correction, retraction, and public provenance.

It does **not** own diagnosis, prescription, potency, dosage, emergency care, clinical records, File 06 encyclopedia truth, File 00 identity truth, File 20 shell truth, File 25 visual-system truth, File 26 federated search truth, or general feed publishing.

## Four-plan compliance release

Version `1.1.0` is the corrective release produced by four distinct review passes against:

1. Definitive Integrated Master Plan v3.0;
2. Consolidated All-Chats Recovered Directive Register v2.1;
3. Continuous Value / Global Top-20 Feature Superset Master Plan;
4. File 15 dedicated complete master plan v1.0.

The exact findings, corrections, evidence, and residual external gates are recorded in `docs/FOUR-PLAN-AUDIT-2026-08-06.md`.

## Repository layout

```text
15-radar-symptom-and-remedy-analysis-foundation/  WordPress plugin
.github/workflows/quality.yml                     CI quality gates
docs/                                             architecture, contracts, security, QA, operations
tests/                                            executable domain and static regression tests
scripts/build.sh                                  deterministic release builder
```

## Release identity

- Plugin version: `1.1.0`
- Schema version: `1.0.0`
- Text domain: `radar-symptom-remedy-analysis`
- PHP prefix: `RSR_`
- Target project baseline: WordPress `7.0.1`, PHP `8.3`
- Minimum declared runtime: WordPress `6.5`, PHP `8.1`

## Local verification

```bash
bash scripts/build.sh
```

The build command performs PHP lint, JavaScript syntax checks, executable domain tests, static plan/regression checks, four-plan compliance regression checks, manifest/checksum generation, and deterministic ZIP creation.

## Completion semantics

This repository can prove **source implementation, package integrity, and automated QA**. It cannot by itself prove Hostinger/WordPress staging acceptance, production deployment, real provider credentials, operational staffing, or live service levels. Those remain explicit gates in `docs/STAGING-ACCEPTANCE.md`.

## Safety

Radar is an educational research tool. It does not diagnose disease, prescribe a remedy, select potency or dosage, replace a qualified clinician, or provide emergency care. Private studies must never contain patient-identifying information.
