# Automated and Manual Test Matrix

## Automated in repository

- PHP syntax for every PHP file.
- JavaScript syntax for both browser bundles.
- Domain validation: sixteen dimensions, query bounds, comparison maximum, report transitions, window boundaries, deterministic score and canonical hash.
- PII detection and safe-input controls.
- Encryption round trip and tamper rejection.
- Static requirements: routes, tables, capabilities, REST paths, event names, private headers, outbox, cron, source governance, no auto-publish path, text domain/prefix/version and package structure.
- ZIP path safety, source manifest and SHA-256.

## Requires WordPress integration/staging

- `dbDelta`, real `$wpdb`, object cache, rewrite, REST authentication/nonces and WordPress privacy callbacks;
- File 00/File 06/File 20/File 26 real contracts;
- real provider sandbox and network failure behavior;
- browsers, assistive technologies, cache/CDN and Hostinger cron;
- load, penetration, backup/restore and rollback.

## Defect discipline

Any failure blocks release evidence. Correct the defect, add a regression assertion, rerun the entire automated suite, then rerun every affected staging journey.
