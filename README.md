# File 15 — Radar, Symptom/Remedy Research and Trend Intelligence

WordPress implementation for File 15 of the Sabri Social Homeopathy Platform.

## Release

- Plugin: `1.2.0`
- Schema: `1.2.0`
- PHP: `8.1+`
- Target integration baseline: WordPress `7.0.1`, PHP `8.3`
- Forty-round result: **32 rounds found defects and fixed them; 8 rounds found no new defect**

## Canonical boundary

File 15 owns governed Radar dimensions/mappings, up-to-three remedy comparison, private verified-doctor Saved Studies, source/provider governance, aggregate trend ingestion, reproducible scoring, editorial reports, corrections and retractions. It does not own diagnosis, prescription, potency/dosage, patient records, identity truth, encyclopedia truth, global shell or federated search.

## Verification

```bash
bash scripts/build.sh
```

The builder runs PHP lint, JavaScript syntax checks, domain/security assertions, static requirement checks, forty-round regression checks, deterministic packaging, manifest generation and SHA-256 verification.

## Evidence boundary

Source/package correctness is not Hostinger staging, live deployment or operational acceptance. See `docs/STAGING-ACCEPTANCE.md` and `docs/FORTY-ROUND-AUDIT-2026-08-06.md`.
