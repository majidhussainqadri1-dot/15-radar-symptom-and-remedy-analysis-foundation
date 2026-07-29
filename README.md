# Radar Symptom and Remedy Analysis Foundation

This repository governs **File 15** of the **Sabri Social Homeopathy Platform**.

## Module identity

- **File:** 15
- **Plugin:** Radar Symptom and Remedy Analysis Foundation
- **Corrective version:** 0.1.1
- **Original preserved version:** 0.1.0
- **WordPress requirement:** 6.0 or later
- **PHP requirement:** 7.4 or later
- **Platform:** Sabri Social Homeopathy Platform
- **Website:** https://www.sabrihomeopathy.com/

## Corrective release scope

Version 0.1.1 repairs the defects found during the first formal audit of the imported 0.1.0 baseline:

- dedicated Radar capabilities instead of generic WordPress post capabilities;
- publication enforcement across Classic Editor, REST, imports, scheduled publishing, WP-CLI, and programmatic updates;
- mandatory source, approved license, review date, confirmations, and authorized approval;
- audit records for metadata and status changes;
- strict managed-page ownership so unrelated pages are never taken over;
- File 20 compatibility without a duplicate global navigation bar;
- keyword search across structured Radar fields;
- shortcode-aware no-cache and noindex controls for Saved Studies;
- automatic Saved Studies deletion when the owning user is deleted;
- accessible dark text on orange controls, contextual checkbox labels, live status announcements, and removal of nested `<main>` landmarks;
- one remedy multi-select rather than three duplicated 250-option lists;
- schema-versioned database upgrades and repository regression gates.

## Functional scope

- Public symptom and rubric search.
- Structured filters for symptom characteristics and modalities.
- Referenced Radar entries with source, license, review, approval, and audit controls.
- Public comparison of up to three published Encyclopedia remedy entries.
- Private anonymous Saved Studies for verified doctors.
- WordPress privacy export and erasure support.
- Responsive American English interface.

The plugin explicitly does **not** provide automated diagnosis, prescribing, potency or dosage recommendations, autonomous AI treatment decisions, patient medical records, or complete advanced repertorization.

## Branches

- `baseline/file-15-original-import` preserves the exact imported 0.1.0 source.
- `fix/file-15-corrective-release-0.1.1` contains the audited corrective release candidate.

## Governance

This corrective branch is not a production-completion claim. Merge requires successful GitHub quality gates, code review, WordPress staging installation, upgrade and rollback tests, role-matrix tests, REST and scheduled-publication tests, dependency tests with Files 06/07/09/20, responsive and accessibility acceptance, and Founder approval.
