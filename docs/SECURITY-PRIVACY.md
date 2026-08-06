# Security, Privacy and Clinical-Safety Controls

## Authorization

- Roles are context labels, not authorization by themselves.
- File 00 verified-doctor/founder claims are requested through a versioned filter.
- File 15 repeats native capability, object owner, state and version checks at action time.
- Private study IDs are looked up with both public ID and owner user ID, preventing IDOR.
- Report publication, source management, schema management, correction and diagnostics use separate capabilities.

## Private studies

- Authenticated encryption: Sodium secretbox where available; AES-256-GCM fallback.
- Key derivation: WordPress secret material via HKDF; no key is stored in the File 15 tables.
- Conservative PII scanner before create/update.
- `noindex`, `noarchive`, `nofollow`, no-store, and no public cache.
- Export and erasure through REST and WordPress privacy tools.
- User deletion purges studies.
- Logs redact secrets and study ciphertext fields.

## Provider security

- Raw secret/token/password/API-key/cookie/authorization keys in source config are rejected.
- Only a `credentials_ref` to the platform secret manager may be persisted.
- Provider adapters declare network, window, geography, credential, replay and privacy capabilities.
- The built-in manual provider performs no network request and accepts aggregate rows only.
- License allowlist, non-future review date, territory, restrictions, quality, quota, rate limit, cost model, health and staleness are explicit.

## Ingestion and publication

- A cryptographic idempotency key protects each command.
- A short distributed lock protects concurrent execution.
- Per-source hourly rate limits and quota state are enforced.
- Spam/bot probabilities above the threshold are rejected; repost factor normalizes counts; aliases and geography can be normalized by versioned rules.
- Source failures become explicit degraded states and outbox events.
- Ingestion creates/refreshes draft reports only. Automatic publication is structurally absent.
- Publication requires the encoded analyst/editorial/approval sequence.

## Medical boundary

Every public surface and response states that Radar and trends are educational research only. File 15 produces no diagnosis, remedy prescription, potency, dosage, emergency triage, patient chart or treatment ranking. Red-flag messaging directs users to appropriate local emergency care.

## Threats covered by tests/review

- IDOR and privilege escalation;
- REST/cookie-CSRF behavior;
- stale claim/session access;
- duplicate/idempotency races;
- PII bypass attempts;
- encryption tamper/decrypt failure;
- raw credential leakage;
- source/license bypass;
- auto-publication bypass;
- private cache/index leakage;
- event replay and dead-letter handling;
- report version conflicts and silent overwrite;
- unsafe uninstall and rollback data loss.
