# File 15 — Radar, Symptom/Remedy Research and Trend Intelligence

WordPress implementation for File 15 of the Sabri Social Homeopathy Platform.

## Release

- Plugin: `1.2.2`
- Schema: `1.2.0`
- PHP: `8.1+`
- Target integration baseline: WordPress `7.0.1`, PHP `8.3`
- Forty-round result: **32 rounds found defects and fixed them; 8 rounds found no new defect**

## Canonical boundary

File 15 owns governed Radar dimensions/mappings, up-to-three remedy comparison, private verified-doctor Saved Studies, source/provider governance, aggregate trend ingestion, reproducible scoring, editorial reports, corrections and retractions. It does not own diagnosis, prescription, potency/dosage, patient records, identity truth, encyclopedia truth, global shell or federated search.

## Correction in 1.2.1

Fixes out-of-order public trend pagination and restores the four-plan compliance regression to the mandatory build suite. The schema remains `1.2.0`. No staging or live acceptance is implied.

## Current correction 1.2.2

Medical red flags short-circuit before remedy lookup; health-query privacy covers both POST and GET; legacy File 06 eligibility and external mapped evidence fail closed unless verified. Forty sequential automated source regression rounds are required in CI. This is repository evidence, not deployed acceptance. Schema remains `1.2.0`.

## Verification

```bash
bash scripts/build.sh
```

The builder runs PHP lint, JavaScript syntax checks, domain/security assertions, static requirement checks, forty-round regression checks, deterministic packaging, manifest generation and SHA-256 verification.

## Evidence boundary

Source/package correctness is not Hostinger staging, live deployment or operational acceptance. See `docs/STAGING-ACCEPTANCE.md` and `docs/FORTY-ROUND-AUDIT-2026-08-06.md`.
