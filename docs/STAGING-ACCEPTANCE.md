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
