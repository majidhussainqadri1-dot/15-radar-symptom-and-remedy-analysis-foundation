=== Radar, Symptom/Remedy Research and Trend Intelligence ===
Contributors: sabrihomeopathy
Tags: homeopathy, research, radar, trends, remedies, analytics
Requires at least: 6.5
Tested up to: 7.0.1
Requires PHP: 8.1
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

File 15 of the Sabri Social Homeopathy Platform: source-linked Radar research, private verified-doctor studies, and editorially governed trend intelligence.

== Description ==

This plugin implements the canonical File 15 boundary:

* Sixteen governed symptom/research dimensions with canonical values, aliases, definitions, source IDs, and versions.
* Bounded AND/OR Radar search with explicit query explanation, “Why this result,” zero-result recovery, and no fabricated remedy.
* File 06 remedy linkage and a strict one-to-three remedy comparison.
* Source/license/review-date registry and auditable mappings.
* Verified-doctor-only private Saved Studies with current suspension/revocation rechecks, PII blocking, authenticated encryption, owner-only access, export, and deletion.
* Versioned trend-provider registry; the built-in provider accepts reviewed aggregate rows and performs no network calls.
* Idempotent daily, weekly, monthly, and yearly ingestion windows using UTC storage and a declared display time zone.
* Reproducible normalization, deduplication, source-quality scoring, confidence, freshness, and thresholds.
* Draft → analyst review → editorial review → approved → published workflow. Ingestion never auto-publishes.
* Public correction/retraction history and versioned outbox events.
* Public-only Search/AI/feed contracts; private studies are structurally excluded.
* Health-research query transport uses private no-store/no-referrer controls and noindex query variants.
* Explainable evidence ordering has no clinical, paid, donor, popularity, potency, dosage, or cure-probability meaning.
* File 20 context-control contract with safe fallback, central-green, RTL-aware, mobile-first interfaces.

This is an educational research system. It does not diagnose, prescribe, select potency or dosage, replace a qualified clinician, or provide emergency care.

== Installation ==

1. Back up the database and files.
2. Install on a Hostinger-equivalent staging site first.
3. Activate the plugin.
4. Confirm the ten `rsr_` tables, rewrite routes, scheduled events, capabilities, and diagnostics.
5. Connect File 00 verified/current claim adapters and File 06 remedy contracts.
6. Add only licensed aggregate trend sources.
7. Execute the complete staging acceptance matrix in `docs/STAGING-ACCEPTANCE.md` before any production deployment.

== Frequently Asked Questions ==

= Does Radar prescribe or rank a remedy for treatment? =

No. Results are source-linked educational possibilities. Result order reflects matching governed evidence only and never contains prescription, potency, dosage, cure probability, or treatment-priority meaning.

= What happens when there is no result? =

Radar gives safe query-recovery steps and an optional governed source-gap route. It does not fabricate a remedy.

= Are Saved Studies clinical records? =

No. Patient-identifying information is prohibited. Clinical records belong to the separately approved clinical domain if and when activated.

= Are trend reports automatically published? =

No. Automated and manual ingestion creates or refreshes a draft. Separate analyst, editorial, approval, and publish transitions are mandatory.

= Can private studies enter AI, search, feeds, or trends? =

No. Only public approved Radar results and published/corrected public reports are exposed through integration contracts.

== Changelog ==

= 1.1.0 =
* Completed four-plan compliance review against the three platform constitutions and the File 15 plan.
* Added fail-closed suspension, verification-revocation, and security-hold checks at protected actions.
* Added no-store/no-referrer/noindex handling for research-query variants and sensitive REST operations.
* Replaced misleading percentage presentation with explainable evidence counts and “Why this result.”
* Added zero-result recovery without fabricated remedies and conservative urgent-red-flag escalation.
* Required every Saved Study remedy reference to resolve through the current eligible File 06 contract.
* Made study encryption failure fail closed and removed raw internal owner ID from portable exports.
* Added robust quoted If-Match version parsing, mutation/report throttles, canonical schema-value submission, File 20 context-control consumption, localized client states, and four-plan regression tests.

= 1.0.0 =
* Complete coding baseline against File 15 master plan v1.0.
* Added governed Radar schema/search/comparison/source mappings.
* Added encrypted verified-doctor Saved Studies and privacy lifecycle.
* Added provider registry, idempotent trend ingestion, normalization, scoring, windows, report workflow, fallback, correction, outbox, REST, CLI, UI, diagnostics, tests, build, and release evidence.
