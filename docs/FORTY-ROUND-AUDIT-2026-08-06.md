# File 15 — چالیس ادوارِ نظرِ ثانی، اصلاحِ نقائص اور حتمی رجسٹر

**تاریخ:** 6 اگست 2026ء، پاکستان معیاری وقت  
**بنیادی نسخہ:** `1.1.0` / main `622c0fbe9b541c8c594e22e99cf3863fbf411872`  
**اصلاحی نسخہ:** `1.2.0`  
**شاخ:** `audit/forty-round-review-2026-08-06`

## حاکم دائرۂ کار

ہر دور الگ focus، تازہ source reading، خطرہ، منصوبہ جاتی تقاضا، implementation اور regression evidence پر قائم ہے۔ جس دور میں نقص ملا، اسی دور کے اختتام پر اصلاح اور متعلقہ دوبارہ جانچ مکمل کی گئی؛ اس کے بعد ہی اگلا دور شروع کیا گیا۔ بنیاد چار منصوبے ہیں: Definitive Master Plan v3.0، Recovered Directives v2.1، Continuous Value/Global Top-20 Superset، اور File 15 Dedicated Master Plan v1.0۔

## شمارِ حتمی

- **کل نظرِ ثانی:** 40
- **جن ادوار میں نقائص ملے:** 32
- **جن ادوار میں کوئی نیا نقص نہیں ملا:** 8
- **معلوم unresolved blocker/critical source defects:** 0
- **خارجی gates:** حقیقی Hostinger staging، integrated companion contracts، provider sandbox، performance/accessibility matrix، backup/restore اور Founder approval الگ ہیں۔

## چالیس ادوار کا اندراج

| دور | جانچ کا موضوع | نتیجہ اور اسی دور کی اصلاح |
|---:|---|---|
| 01 | Release/schema identity and evidence truth | **نقص ملا:** plugin version آگے تھا مگر schema/evidence identity ہم آہنگ نہ تھی؛ version/schema/build/readme کو `1.2.0` پر ہم آہنگ کرکے دوبارہ جانچ کامیاب۔ |
| 02 | Atomic schema upgrade and complete-table verification | **نقص ملا:** concurrent upgrade اور جزوی `dbDelta` کامیابی پر schema version آگے بڑھنے کا خطرہ؛ upgrade lock، stale-lock recovery اور تمام tables کی تصدیق شامل، دوبارہ جانچ کامیاب۔ |
| 03 | Abuse control concurrency | **نقص ملا:** transient fixed-window limiter concurrent requests میں count کھو سکتا تھا؛ database-atomic `rate_limits` table اور fail-closed limiter نافذ، دوبارہ جانچ کامیاب۔ |
| 04 | Incoming event durability and deduplication | **نقص ملا:** transient dedupe طویل مدت یا multi-node ماحول میں کافی نہ تھی؛ durable `inbox`، payload hash، unique event ID، lease اور collision rejection شامل، دوبارہ جانچ کامیاب۔ |
| 05 | Incoming event schema/version validation | **نقص ملا:** supported event name کے باوجود required payload fields خاموشی سے غائب ہوسکتے تھے؛ exact version/name اور per-event schema validation شامل، دوبارہ جانچ کامیاب۔ |
| 06 | Outbox worker concurrency and stale recovery | **نقص ملا:** global transient lock row-level claim، crash recovery اور horizontal workers کے لیے ناکافی تھا؛ atomic claim/lease/retry/dead-letter workflow شامل، دوبارہ جانچ کامیاب۔ |
| 07 | Report publication atomicity | **نقص ملا:** report state اور published event الگ writes تھے؛ transaction میں state transition اور outbox publication یکجا، دوبارہ جانچ کامیاب۔ |
| 08 | Production encryption-key safety | **نقص ملا:** predictable fallback key کا امکان production confidentiality توڑ سکتا تھا؛ explicit key یا WordPress salts لازم، کمزور/غائب material پر fail-closed، دوبارہ جانچ کامیاب۔ |
| 09 | Private-study key rotation | **نقص ملا:** ciphertext میں key identifier اور previous-key ring نہ تھی؛ v2 fingerprinted format، current/previous key ring اور legacy decrypt compatibility شامل، دوبارہ جانچ کامیاب۔ |
| 10 | Decrypt-failure overwrite protection | **نقص ملا:** inaccessible old note کو edit کرنے پر خالی plaintext سے overwrite ہونے کا خطرہ؛ update سے پہلے decrypt لازم، UI warning اور edit disable، دوبارہ جانچ کامیاب۔ |
| 11 | PII scanner resource bounds | **نقص ملا:** recursive/oversized structured input CPU/memory exhaustion پیدا کرسکتا تھا؛ depth/node/byte limits اور fail-closed categories شامل، دوبارہ جانچ کامیاب۔ |
| 12 | Log/audit minimization and tamper evidence | **نقص ملا:** deep context، secret-like values اور audit tampering کے خلاف مکمل control نہ تھا؛ bounded redaction، PII/secret filtering، `entry_hash` اور verifier شامل، دوبارہ جانچ کامیاب۔ |
| 13 | Anonymous request hashing | **نقص ملا:** predictable IP-hash salt privacy boundary کمزور کرتا تھا؛ production salts required اور raw IP persistence ممنوع رکھی، دوبارہ جانچ کامیاب۔ |
| 14 | Source-rights state separation | **نقص ملا:** legacy licence codes نئی writes میں بھی قبول ہوسکتے تھے؛ strict writable allowlist اور legacy read-only migration list جدا، دوبارہ جانچ کامیاب۔ |
| 15 | Provider configuration secret/size control | **نقص ملا:** nested secret values اور oversized configuration صرف key-name regex سے مکمل نہ رک سکتے تھے؛ byte limit، material scan، PII scan اور secret-manager reference scheme شامل، دوبارہ جانچ کامیاب۔ |
| 16 | Provider capability contract | **نقص ملا:** adapter privacy/window/geography/timeout/allowlist declarations مکمل enforce نہ تھیں؛ registry contract validation اور network-adapter prerequisites شامل، دوبارہ جانچ کامیاب۔ |
| 17 | Manual ingestion payload and numeric safety | **نقص ملا:** manual rows/body اور `NaN/INF` یا انتہائی اعداد bounded نہ تھے؛ row/body/reference/geography limits اور finite clamps شامل، دوبارہ جانچ کامیاب۔ |
| 18 | Trend provenance snapshot completeness | **نقص ملا:** public report source snapshot میں edition/review date/territory/restrictions مکمل نہ تھے؛ immutable provenance DTO اور UI شامل، دوبارہ جانچ کامیاب۔ |
| 19 | Report release evidence gate | **نقص ملا:** confidence threshold، current licence، review date اور topic-to-source consistency مکمل enforce نہ تھی؛ release gate سخت کیا، دوبارہ جانچ کامیاب۔ |
| 20 | Correction/retraction transactional integrity | **نقص ملا:** report update، correction row اور event جزوی طور پر الگ ہوسکتے تھے؛ transaction، before/after hashes اور insertion checks شامل، دوبارہ جانچ کامیاب۔ |
| 21 | Retraction event semantics | **نقص ملا:** correction اور retraction ایک ہی event name سے ظاہر ہوسکتے تھے؛ الگ `RadarTrendReportRetracted.v1` شامل، دوبارہ جانچ کامیاب۔ |
| 22 | Editorial separation of duties | **نقص ملا:** reviewer ہی approve/publish کرسکتا تھا؛ الگ `APPROVE_REPORTS` capability، role separation اور controlled override شامل، دوبارہ جانچ کامیاب۔ |
| 23 | Normalized filter-key collision | **نقص ملا:** دو input keys ایک normalized dimension بن کر سابق values overwrite کرسکتے تھے؛ deterministic merge اور post-merge limits شامل، دوبارہ جانچ کامیاب۔ |
| 24 | Invalid comparison identifier handling | **نقص ملا:** malformed non-empty IDs خاموشی سے drop ہوسکتے تھے؛ explicit `invalid_remedy_id` failure شامل، دوبارہ جانچ کامیاب۔ |
| 25 | Mapping source identity/licence consistency | **نقص ملا:** mapping source ID، source status اور declared licence میں mismatch ممکن تھا؛ active source lookup اور exact licence match لازم، دوبارہ جانچ کامیاب۔ |
| 26 | Schema value source governance | **نقص ملا:** schema value کا source reference غیر موجود یا غیر فعال ہوسکتا تھا؛ governed source validation شامل، دوبارہ جانچ کامیاب۔ |
| 27 | Disabled-source exclusion | **نقص ملا:** disabled source سے پرانی schema/mappings/results browse ہوسکتی تھیں؛ joins اور eligibility checks سے exclusion شامل، دوبارہ جانچ کامیاب۔ |
| 28 | External File 06/26 search DTO trust boundary | **نقص ملا:** adapter result کے اضافی/غیر معتبر fields پاس ہوسکتے تھے؛ strict whitelist، current eligibility اور provenance normalization شامل، دوبارہ جانچ کامیاب۔ |
| 29 | Cached remedy eligibility freshness | **نقص ملا:** cached search result suspension/retraction کے بعد بھی پرانی remedy دکھاسکتا تھا؛ cache hit پر current File 06 revalidation/refresh شامل، دوبارہ جانچ کامیاب۔ |
| 30 | Stable Radar pagination | **نقص ملا:** mapping-row cursor سے remedy duplication/skips اور score ordering instability ممکن تھی؛ stable lexical remedy cursor، candidate-first query اور deterministic ties شامل، دوبارہ جانچ کامیاب۔ |
| 31 | Private Saved Studies pagination | **نقص ملا:** UI پہلے 50 records کے بعد next cursor consume نہ کرتی تھی؛ cursor route/view اور “Load older studies” شامل، دوبارہ جانچ کامیاب۔ |
| 32 | Health-query transport, lazy loading and client localization | **نقص ملا:** public GET query leakage، every-route full schema load اور hard-coded client strings باقی تھے؛ Radar/Compare POST، GET compatibility protection، lazy schema اور localized statuses شامل، دوبارہ جانچ کامیاب۔ |
| 33 | Canonical ownership and no duplicate backend | **کوئی نیا نقص نہیں ملا:** File 15 ہی Radar/trend owner؛ Files 00/06/20/24/25/26 کی ownership محفوظ، regression کامیاب۔ |
| 34 | Clinical boundary | **کوئی نیا نقص نہیں ملا:** diagnosis، prescription، potency، dosage اور emergency replacement outputs موجود نہیں؛ safety regression کامیاب۔ |
| 35 | Private-data exclusion from public AI/search/feed/trends | **کوئی نیا نقص نہیں ملا:** Saved Studies الگ owner-scoped encrypted service میں ہیں اور public providers میں رجسٹر نہیں؛ regression کامیاب۔ |
| 36 | RTL, Back/Home and shell integration | **کوئی نیا نقص نہیں ملا:** File 20 context-control contract پہلے consume، duplicate-safe fallback، RTL arrow/logical layout برقرار؛ regression کامیاب۔ |
| 37 | Accessibility and responsive source controls | **کوئی نیا نقص نہیں ملا:** semantic labels، focus-visible، reduced motion، mobile grid اور logical CSS برقرار؛ source-level regression کامیاب، real browser matrix خارجی gate ہے۔ |
| 38 | Dangerous primitives, embedded secrets and package paths | **کوئی نیا نقص نہیں ملا:** dangerous execution primitives/credential signatures/unsafe archive paths نہیں ملے؛ scan کامیاب۔ |
| 39 | Fresh adversarial regression after final correction | **کوئی نیا نقص نہیں ملا:** domain، static اور forty-round negative controls دوبارہ کامیاب؛ کوئی معلوم blocker/critical source defect باقی نہیں۔ |
| 40 | Deterministic build, manifest and clean-extract integrity | **کوئی نیا نقص نہیں ملا:** reproducible ZIP، single root، required files، source manifest اور SHA-256 verification کامیاب۔ |

## اصلاح شدہ بنیادی سطحیں

- schema migration، audit، inbox/outbox، rate limiting اور transactional writes؛
- encryption/key rotation، PII bounds، redaction اور privacy-preserving identifiers؛
- source/provider/licence/provenance governance؛
- search normalization، cache freshness، pagination اور external-adapter DTOs؛
- editorial separation، correction/retraction history اور release gates؛
- POST health-query transport، cursor UI، localization اور lazy schema loading؛
- automated domain/static/forty-round gates اور deterministic packaging۔

## حتمی حکم

Repository source، automated tests، documentation اور package کی سطح پر چالیس ادوار مکمل ہوئے۔ **32 ادوار میں نقائص نکلے اور اسی دور میں درست ہوئے؛ 8 ادوار میں کوئی نیا نقص نہیں نکلا۔** حقیقی staging/live/operational acceptance الگ ثبوت کے بغیر claim نہیں کی گئی۔
