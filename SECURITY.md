# Security Policy

## Supported version

Security fixes are applied to the latest `1.x` release candidate until a later supported line is declared.

## Reporting

Do not publish suspected vulnerabilities, credentials, private studies, patient information, source contracts, or exploit details in a public issue. Use the repository owner's private GitHub security-reporting channel or another approved confidential channel.

A useful report includes the affected version, route or command, required role, reproduction steps using synthetic data, impact, logs with secrets removed, and a proposed containment path.

## Security invariants

- File 00 is the identity/claim authority; File 15 performs native owner/state checks as well.
- Private Saved Studies are owner-only, encrypted at rest, noindex, no-cache and excluded from public contracts.
- Patient-identifying information is prohibited and blocked conservatively.
- Raw provider credentials are prohibited; only secret-manager references may be stored.
- All sensitive mutations require authenticated capability checks, nonces under cookie authentication, version checks, audit and bounded inputs.
- Trend ingestion is idempotent and never auto-publishes.
- Public reports expose aggregate provenance only.
- Errors and diagnostics are redacted; audit IP values are salted hashes.
- Uninstall is non-destructive unless an explicit purge flag is set.

## Release rule

Any unresolved critical or high vulnerability blocks merge, packaging, staging acceptance and production deployment.
