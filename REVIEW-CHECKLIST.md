# Baseline Review Checklist

## Import integrity

- [x] Original package SHA-256 recorded.
- [x] Reconstructed package checksum matched the supplied ZIP.
- [x] ZIP integrity passed.
- [x] No unsafe absolute or path-traversal archive entries were detected.
- [x] Exactly 18 source files were extracted.
- [x] Per-file SHA-256 checksums were recorded.
- [x] PHP syntax passed for all 15 PHP files during local import verification.
- [x] JavaScript syntax passed for the supplied JavaScript file.

## Required before merge or release acceptance

- [ ] Complete source-code review.
- [ ] Security, privacy, capability, nonce, sanitization, escaping, and SQL review.
- [ ] WordPress fresh-install test.
- [ ] WordPress upgrade and migration test.
- [ ] Dependency integration test with Files 01, 03, 06, 07, and 09.
- [ ] Founder and administrator publishing workflow test.
- [ ] Verified-doctor private Saved Studies workflow test.
- [ ] Public Radar search, filters, comparison, and SEO test.
- [ ] Privacy export and erasure test.
- [ ] Uninstall and rollback-boundary test.
- [ ] Responsive and accessibility acceptance.
- [ ] Hostinger staging activation and runtime acceptance.
- [ ] Correction and retesting of every defect discovered during review.

The pull request must remain in draft until the applicable review gates have passed.
