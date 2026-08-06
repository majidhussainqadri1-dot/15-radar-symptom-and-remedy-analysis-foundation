=== Radar, Symptom/Remedy Research and Trend Intelligence ===
Contributors: sabrihomeopathy
Tags: homeopathy, research, radar, trends, remedies, analytics
Requires at least: 6.5
Tested up to: 7.0.1
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

File 15 of the Sabri Social Homeopathy Platform: source-linked Radar research, private verified-doctor studies, and editorially governed trend intelligence.

== Description ==

This plugin implements the canonical File 15 boundary:

* Sixteen governed symptom/research dimensions with values, aliases, definitions, source IDs, and versions.
* Bounded AND/OR Radar search with explicit query explanation.
* File 06 remedy linkage and a strict one-to-three remedy comparison.
* Source/license/review-date registry and auditable mappings.
* Verified-doctor-only private Saved Studies with PII blocking, authenticated encryption, owner-only access, export, and deletion.
* Versioned trend-provider registry; the built-in provider accepts reviewed aggregate rows and performs no network calls.
* Idempotent daily, weekly, monthly, and yearly ingestion windows using UTC storage and a declared display time zone.
* Reproducible normalization, deduplication, source-quality scoring, confidence, freshness, and thresholds.
* Draft → analyst review → editorial review → approved → published workflow. Ingestion never auto-publishes.
* Public correction/retraction history and versioned outbox events.
* Public-only Search/AI/feed contracts; private studies are structurally excluded.
* Accessible, RTL-aware, mobile-first public and operations interfaces using the platform's central green visual identity.

This is an educational research system. It does not diagnose, prescribe, select potency or dosage, replace a qualified clinician, or provide emergency care.

== Installation ==

1. Back up the database and files.
2. Install on a Hostinger-equivalent staging site first.
3. Activate the plugin.
4. Confirm the ten `rsr_` tables, rewrite routes, scheduled events, capabilities, and diagnostics.
5. Connect File 00 verified claims and File 06 remedy contracts.
6. Add only licensed aggregate trend sources.
7. Execute the complete staging acceptance matrix in `docs/STAGING-ACCEPTANCE.md` before any production deployment.

== Frequently Asked Questions ==

= Does Radar prescribe a remedy? =

No. Results are source-linked educational possibilities and never contain prescription, potency, or dosage outputs.

= Are Saved Studies clinical records? =

No. Patient-identifying information is prohibited. Clinical records belong to the separately approved clinical domain if and when activated.

= Are trend reports automatically published? =

No. Automated and manual ingestion creates or refreshes a draft. Separate analyst, editorial, approval, and publish transitions are mandatory.

= Can private studies enter AI, search, feeds, or trends? =

No. Only public approved Radar results and published/corrected public reports are exposed through integration contracts.

== Changelog ==

= 1.0.0 =
* Complete coding baseline against File 15 master plan v1.0.
* Added governed Radar schema/search/comparison/source mappings.
* Added encrypted verified-doctor Saved Studies and privacy lifecycle.
* Added provider registry, idempotent trend ingestion, normalization, scoring, windows, report workflow, fallback, correction, outbox, REST, CLI, UI, diagnostics, tests, build, and release evidence.
