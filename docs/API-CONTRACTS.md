# API and Integration Contracts

## REST namespace

`/wp-json/rsr/v1`

### Public reads/commands

| Method | Route | Contract |
|---|---|---|
| GET | `/schema` | Sixteen dimensions, governed values, limits and safety notice |
| POST | `/radar/search` | Validated AND/OR query; bounded source-linked public results |
| POST | `/radar/compare` | One-to-three eligible File 06 remedy IDs |
| GET | `/trends` | Published/corrected public reports only |
| GET | `/trends/{id}` | Public report, provenance, freshness and correction notices |

### Private studies

| Method | Route | Contract |
|---|---|---|
| GET/POST | `/studies` | Owner list/create; verified doctor required |
| GET/PUT/DELETE | `/studies/{id}` | Owner-only read/versioned update/versioned delete |
| GET | `/studies/export` | Own portable JSON export |

Private responses carry `Cache-Control: private, no-store` and `X-Robots-Tag: noindex, noarchive, nofollow`.

### Restricted operations

- `/manage/sources` and `/manage/sources/{id}`
- `/manage/ingestion` with required `Idempotency-Key`
- `/manage/reports`
- `/manage/reports/{id}/transition`
- `/manage/reports/{id}/correction`
- `/manage/schema`
- `/manage/mappings`
- `/manage/diagnostics`

Every response contains a trace ID and API/plugin versions. Errors use stable codes and HTTP status without raw stack traces or secrets.

## Published events

| Event | Aggregate | Public payload purpose |
|---|---|---|
| `RadarTrendReportPublished.v1` | `trend_report` | File 21/26/16 may discover an approved report |
| `RadarTrendReportCorrected.v1` | `trend_report` | Invalidate/refresh impacted discovery and show public notice |
| `RadarSourceDegraded.v1` | `trend_source` | Operations/assurance notification without credentials |

## Consumed events

- `EncyclopediaEntryPublished.v1`
- `EncyclopediaEntryCorrected.v1`
- `EncyclopediaEntryRetracted.v1`
- `DoctorSuspended.v1`
- `ProviderQuotaChanged.v1`

Each consumed event requires a stable event ID and is deduplicated. Encyclopedia corrections/retractions mark impacted mappings review-required and invalidate comparison cache. A doctor-suspension event invalidates only short-lived claim cache; File 00 remains authoritative.

## Cross-file read contracts

- `rsr_file00_claim` — versioned identity/claim assertion.
- `rsr_file06_search` — optional canonical indexed Radar search.
- `rsr_file06_get_remedies` — published/eligible remedy DTOs.
- `sabri_file15_public_search_v1` — public-only Radar/report search result.
- `sabri_file15_public_reports_v1` — public reports only.

No contract exports private studies.

## Medical red-flag and external-evidence hardening (v1.2.2)

A validated emergency-indicative Radar query is diverted **before** invoking either the local mapping engine or a File 06 search adapter. The public search response returns `results=[]`, `next_cursor=null`, `emergency_diversion=true` and a qualified local emergency-care notice; it must not provide alternative self-treatment recommendations. Public adapters receive defense-in-depth scrubbing if they emit results for an urgent query.

External search candidates are accepted only with eligible current File 06 remedy references and valid, nonfuture, source-linked, deduplicated mapping evidence. Their displayed evidence count is determined from accepted mapping entries, not provider-claimed ranking counts.

The prior `HE_Content` legacy compatibility path now requires a per-remedy explicit `rsr_file06_legacy_remedy_eligible` approval from an owner-controlled adapter; default false. A `published` WordPress post alone does not satisfy canonical clinical eligibility.

File 06's independent branch currently emits a `sabri_domain_event` envelope rather than File 15's `sabri_platform_event_v1`; do not claim bridge parity or implement implicit object-ID inference. See File 06 blocker issue #10.
