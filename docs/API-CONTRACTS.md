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


## File 06 canonical bridge correction — source candidate (2026-11-08)

File 15 accepts `KnowledgeConceptMerged.v1` from a proven File 06 outbox source with `event_id`, `source_remedy_public_id` and `target_remedy_public_id` UUIDs. It marks the **old source remedy** mapping `review_required`, invalidates the comparison cache and emits a restricted review-needed signal. It **does not** automatically transfer or reclassify mapping evidence to the merged target. Invalid identifiers fail closed.

`EncyclopediaEntryCorrected.v1` and `EncyclopediaEntryRetracted.v1` now require the mapping update to succeed before acknowledging the event. Retry does not repeatedly increment a mapping that is already `review_required`. Existing File 15 durable inbox deduplicates by source event ID/hash; File 06 must send the original persisted UUID.

Canonical read filter `rsr_file06_get_remedies` must return only `published=true`, `eligible=true`, current safely reviewed and source-linked File 06 remedy objects. A public WordPress post or a text-search result cannot substitute for this contract. `rsr_file06_search` remains optional: File 15's governed mapping engine is the safe fallback.

**Acceptance remains pending:** the updated File 06 candidate must be approved together with this File 15 candidate. Test actual File 06/15/26 installed versions and schema, new/backfilled mappings, duplicate/delayed/retracted/merged events, cache/visibility/denial, emergency diversion, staging restore and rollback. Neither repository source nor green CI certifies a deployed integration.
