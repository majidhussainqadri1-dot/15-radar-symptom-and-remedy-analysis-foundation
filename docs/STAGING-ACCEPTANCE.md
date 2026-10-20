# File 15 Staging Acceptance Matrix

Repository completion does not satisfy these external gates.

1. Fresh install and upgrade from v1.1.0 on WordPress 7.0.1/PHP 8.3.
2. Verify all twelve File 15 tables, including `inbox` and `rate_limits`.
3. Exercise File 00 current verification, suspension, revocation and security-hold claims.
4. Exercise File 06 current eligibility and File 20/24/25/26 contracts.
5. Verify encryption current/previous keys and inaccessible-key warning without overwrite.
6. Run real licensed provider sandbox, quota, timeout, outage, retry and dead-letter paths.
7. Test analyst, approver, publisher and corrector separation with negative journeys.
8. Run Urdu/Arabic/English RTL/LTR, keyboard, screen reader, zoom, reduced motion, forced colors and representative devices.
9. Measure p75/p95 performance, database queries, cache behavior and ingestion load.
10. Rehearse database/files/config/key backup, restore, cache/index rebuild and rollback.
11. Record Founder acceptance, production window, monitoring owner and rollback threshold.

## Additional v1.2.2 medical/privacy and integration gates

12. Submit `chest pain`, `shortness of breath`, and Urdu emergency signals using HTML, POST REST and the public File 26 adapter; assert no remedy results, no cursor, no alternate-remedy recovery, and immediate appropriate qualified emergency-care message.
13. Verify only-keyword POST and GET HTML requests carry no-store, no-referrer, noindex and do not publish a canonical URL containing sensitive query data.
14. Verify a File 06 legacy published WordPress post **without** explicit native-owner current eligibility is rejected; then validate a governed eligible DTO; rerun after correction/retraction/revocation.
15. Inject an adapter's fabricated `match_count`, duplicate source references, invalid source IDs, missing mappings, future/impossible review dates, and verify they are rejected before Radar displays evidence.
16. File 06 canonical events use a stable event ID and affected public concept/remedy identity; replay corrections/retractions/merges through File 15 inbox and File 26 rebuild, with cache invalidation and no private-data exposure. A current source/deployment version and adapter owner must be pinned.
17. Freeze deployed WordPress file checksum, active plugin source version, DB schema/migration state and log evidence before any live defect diagnosis. Re-run staging backup/restore and exact-artifact deployment parity.
