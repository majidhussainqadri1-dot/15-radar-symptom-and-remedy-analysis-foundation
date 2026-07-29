# File 15 Corrective Audit — Version 0.1.1

## Decision

The imported 0.1.0 baseline was structurally intact but was not safe to merge or deploy. Version 0.1.1 addresses the formal review findings while preserving the original baseline branch unchanged.

## Defect-to-correction traceability

| Audit defect | Correction in 0.1.1 | Primary files |
|---|---|---|
| REST and non-Classic publication bypass | Added registered REST meta, post-type capabilities, post-save validation, REST-after-insert validation, and scheduled-publication enforcement | `class-srf-content.php`, `class-srf-admin.php` |
| Generic `edit_posts`/`publish_posts` permissions | Added dedicated Radar post and taxonomy capabilities; administrators receive all; Founder receives all dynamically; verified doctors receive submission-only capabilities | `class-srf-content.php`, `class-srf-helpers.php`, `class-srf-activator.php` |
| Unowned page slug could be taken over | Reuse is limited to plugin/platform-managed, empty, placeholder, or Radar-shortcode pages; otherwise WordPress creates a distinct managed page | `class-srf-activator.php` |
| Duplicate global navigation under File 20 | Removed module-level global navigation output | `templates/radar.php`, `templates/saved-studies.php`, `class-srf-helpers.php` |
| Keyword did not search structured fields | Added scoped title/content/structured-meta keyword SQL using an `EXISTS` subquery | `class-srf-frontend.php` |
| Private headers depended only on one stored page ID | Added shortcode-aware private request detection and `X-Robots-Tag`, no-cache, noindex, noarchive, and nofollow controls | `class-srf-helpers.php`, `class-srf-frontend.php` |
| Saved Studies remained after user deletion | Added `delete_user` and `wpmu_delete_user` cleanup | `class-srf-studies.php` |
| No publication audit trail | Added schema-versioned audit table and status/metadata/publication-block events | `class-srf-activator.php`, `class-srf-helpers.php`, `class-srf-admin.php` |
| Deactivation/uninstall could expose dead shortcodes | Deactivation no longer rewrites pages; uninstall drafts only plugin-managed pages while preserving data | `class-srf-activator.php`, `uninstall.php` |
| Free-text license and optional review date | Added approved license list and mandatory valid non-future review date | `class-srf-helpers.php`, `class-srf-content.php`, `class-srf-admin.php` |
| Orange/white contrast failure | Orange controls now use dark navy text | `assets/css/radar.css` |
| Nested `<main>` landmarks | Module roots changed to `<div>` containers | `templates/radar.php`, `templates/saved-studies.php` |
| Non-contextual checkbox labels and silent status changes | Added entry-title labels and polite live regions | `templates/entry-card.php`, `templates/radar.php`, `assets/js/radar.js` |
| Three repeated 250-option remedy dropdowns | Replaced with one multi-select and three-selection enforcement | `templates/radar.php`, `assets/js/radar.js`, `assets/css/radar.css` |
| No version-driven database upgrade | Added `SRF_SCHEMA_VERSION` and idempotent `maybe_upgrade()` | `radar-foundation.php`, `class-srf-activator.php`, `class-srf-plugin.php` |
| No repository regression gates for corrections | Added static regression tests and expanded CI quality gates | `tests/`, `.github/workflows/baseline-integrity.yml` |

## Residual staging obligations

Source correction does not prove WordPress runtime acceptance. The exact staging checklist in `STATUS.md` and `REVIEW-CHECKLIST.md` remains binding.
