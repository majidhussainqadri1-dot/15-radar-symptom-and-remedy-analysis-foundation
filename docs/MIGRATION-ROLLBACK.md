# Migration, Upgrade and Rollback

## From the historical 0.1.x foundation

The historical branch used the `radar-foundation` folder, `SRF_` symbols and a narrower post/shortcode model. Version `1.0.0` is the canonical File 15 implementation with the required `15-radar-symptom-and-remedy-analysis-foundation` folder, `RSR_` symbols, ten-table model and full trend domain.

No automatic destructive import of historical data is performed. Before migration:

1. export the old package and database;
2. inventory old Radar posts, taxonomies, mappings and Saved Studies;
3. classify every field as safe public mapping, private non-patient study, or prohibited/unknown data;
4. write a one-time migration adapter with dry-run report, source/provenance, collision handling and idempotency;
5. reject or quarantine patient-identifying/unsupported data;
6. reconcile counts and hashes before cutover;
7. preserve the old package for rollback until Founder acceptance.

## Fresh install

Activation creates/updates the ten tables with `dbDelta`, seeds dimension definitions, grants administrator capabilities, registers schedules and rewrite rules, and records versions. Repeated activation is idempotent.

## Upgrade

`RSR_DB::maybe_upgrade()` compares the installed schema version and re-runs the idempotent installer. Every future schema change must:

- increment `RSR_SCHEMA_VERSION`;
- remain safe on repeated execution;
- preserve public IDs and history;
- include forward and rollback tests;
- document data transformation and reconciliation.

## Rollback

1. Enter File 15 safe mode if private writes must stop.
2. Pause ingestion/outbox schedules.
3. Take database and file backups and verify restore.
4. Deploy the previously accepted package.
5. If its schema cannot read newer data, restore the matched pre-deployment database backup; never silently drop columns/tables.
6. Flush rewrite rules and caches.
7. verify public Radar, private access denial/allow, reports, schedules and outbox.
8. record the incident, affected versions, data reconciliation and Founder decision.

Deactivation and uninstall are non-destructive by default. Physical purge requires the explicit `rsr_purge_on_uninstall` option and separate backup/approval evidence.
