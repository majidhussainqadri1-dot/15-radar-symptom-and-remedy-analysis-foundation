#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_DIR="$ROOT/15-radar-symptom-and-remedy-analysis-foundation"
DIST="$ROOT/dist"
VERSION="1.2.2"
ZIP_NAME="15-radar-symptom-and-remedy-analysis-foundation-${VERSION}.zip"
ZIP_PATH="$DIST/$ZIP_NAME"
SECOND_ZIP="$DIST/.determinism-second.zip"

rm -rf "$DIST"
mkdir -p "$DIST"

printf 'Running PHP lint...\n'
PHP_COUNT=0
while IFS= read -r -d '' file; do
    php -l "$file"
    PHP_COUNT=$((PHP_COUNT + 1))
done < <(find "$PLUGIN_DIR" "$ROOT/tests" -type f -name '*.php' -print0 | sort -z) > "$DIST/php-lint.log"

printf 'Running JavaScript syntax checks...\n'
JS_COUNT=0
while IFS= read -r -d '' file; do
    node --check "$file"
    JS_COUNT=$((JS_COUNT + 1))
done < <(find "$PLUGIN_DIR/assets/js" -type f -name '*.js' -print0 | sort -z)
printf 'PASS %s JavaScript syntax checks\n' "$JS_COUNT" > "$DIST/javascript-syntax.log"

printf 'Running domain/security tests...\n'
php "$ROOT/tests/run.php" | tee "$DIST/domain-tests.log"
DOMAIN_ASSERTIONS="$(grep -Eo 'PASS [0-9]+ domain/security assertions' "$DIST/domain-tests.log" | awk '{print $2}')"
printf 'Running static requirement/regression tests...\n'
php "$ROOT/tests/static-regression.php" | tee "$DIST/static-regression.log"
STATIC_CHECKS="$(grep -Eo 'PASS [0-9]+ static requirement/regression checks' "$DIST/static-regression.log" | awk '{print $2}')"
printf 'Running forty-round regression tests...\n'
php "$ROOT/tests/forty-round-regression.php" | tee "$DIST/forty-round-regression.log"
printf 'Running four-plan compliance regression...\n'
php "$ROOT/tests/four-plan-compliance.php" | tee "$DIST/four-plan-compliance.log"
printf 'Running red-flag, privacy and provenance regressions...\n'
php "$ROOT/tests/clinical-boundary-regression.php" | tee "$DIST/clinical-boundary-regression.log"
printf 'Running forty sequential source/test review rounds...\n'
bash "$ROOT/scripts/forty-round-source-review.sh" "$DIST" | tee "$DIST/second-forty-summary.log"
FORTY_CHECKS="$(grep -Eo 'PASS [0-9]+ forty-round controls' "$DIST/forty-round-regression.log" | awk '{print $2}')"

printf 'Generating source manifest...\n'
(
    cd "$ROOT"
    find "$(basename "$PLUGIN_DIR")" -type f -print0 | sort -z | xargs -0 sha256sum
) > "$DIST/SOURCE-MANIFEST.sha256"

build_zip() {
    local destination="$1"
    python3 - "$PLUGIN_DIR" "$destination" <<'PY'
import os
import stat
import sys
import zipfile

plugin_dir, zip_path = sys.argv[1:]
fixed_time = (2026, 8, 6, 23, 50, 0)
base = os.path.basename(plugin_dir)
paths = []
for current, dirs, files in os.walk(plugin_dir):
    dirs.sort()
    files.sort()
    for name in files:
        path = os.path.join(current, name)
        relative = os.path.relpath(path, os.path.dirname(plugin_dir)).replace(os.sep, '/')
        paths.append((relative, path))
with zipfile.ZipFile(zip_path, 'w', compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
    for relative, path in sorted(paths):
        info = zipfile.ZipInfo(relative, fixed_time)
        info.create_system = 3
        info.external_attr = (stat.S_IFREG | 0o644) << 16
        info.compress_type = zipfile.ZIP_DEFLATED
        with open(path, 'rb') as handle:
            archive.writestr(info, handle.read())
PY
}

printf 'Building deterministic ZIP twice...\n'
build_zip "$ZIP_PATH"
build_zip "$SECOND_ZIP"
cmp -s "$ZIP_PATH" "$SECOND_ZIP"
rm -f "$SECOND_ZIP"

printf 'Verifying ZIP paths and required files...\n'
ZIP_ENTRIES="$(python3 - "$ZIP_PATH" <<'PY'
import sys
import zipfile
path = sys.argv[1]
root = '15-radar-symptom-and-remedy-analysis-foundation/'
required = {
    root + 'radar-symptom-remedy-analysis.php',
    root + 'readme.txt',
    root + 'uninstall.php',
    root + 'includes/class-rsr-hardening.php',
}
with zipfile.ZipFile(path) as archive:
    names = archive.namelist()
    unsafe = [n for n in names if n.startswith('/') or '..' in n.split('/')]
    bad_roots = [n for n in names if not n.startswith(root)]
    missing = sorted(required.difference(names))
    duplicates = sorted({n for n in names if names.count(n) > 1})
    if unsafe or bad_roots or missing or duplicates:
        raise SystemExit(f'unsafe={unsafe}; bad_roots={bad_roots[:5]}; missing={missing}; duplicates={duplicates}')
    print(len(names))
PY
)"

printf 'Running clean-extract syntax verification...\n'
EXTRACT="$DIST/clean-extract"
mkdir -p "$EXTRACT"
python3 - "$ZIP_PATH" "$EXTRACT" <<'PY'
import sys
import zipfile
with zipfile.ZipFile(sys.argv[1]) as archive:
    archive.extractall(sys.argv[2])
PY
while IFS= read -r -d '' file; do
    php -l "$file" >/dev/null
done < <(find "$EXTRACT" -type f -name '*.php' -print0 | sort -z)
while IFS= read -r -d '' file; do
    node --check "$file" >/dev/null
done < <(find "$EXTRACT" -type f -name '*.js' -print0 | sort -z)
rm -rf "$EXTRACT"

sha256sum "$ZIP_PATH" > "$DIST/$ZIP_NAME.sha256"
ZIP_HASH="$(cut -d' ' -f1 "$DIST/$ZIP_NAME.sha256")"
SOURCE_COUNT="$(find "$PLUGIN_DIR" -type f | wc -l | tr -d ' ')"
SOURCE_BYTES="$(find "$PLUGIN_DIR" -type f -printf '%s\n' | awk '{s+=$1} END {print s+0}')"
PLUGIN_PHP_COUNT="$(find "$PLUGIN_DIR" -type f -name '*.php' | wc -l | tr -d ' ')"
BUILD_UTC="$(date -u +'%Y-%m-%dT%H:%M:%SZ')"

cat > "$ROOT/docs/RELEASE-EVIDENCE.md" <<EOF
# Release Evidence — 1.2.2

Generated: \`$BUILD_UTC\`

## Historical forty-round result (2026-08-06 baseline)

- Total sequential review/fix rounds: **40**
- Rounds in which defects were found and corrected before the next round: **32**
- Rounds in which no new defect was found: **8**

## Automated result

- PHP lint: PASS (\`$PLUGIN_PHP_COUNT\` plugin PHP files; \`$PHP_COUNT\` total PHP files including tests)
- JavaScript syntax: PASS (\`$JS_COUNT\` files)
- Domain/security tests: PASS (\`$DOMAIN_ASSERTIONS\` assertions)
- Static requirement/regression tests: PASS (\`$STATIC_CHECKS\` checks)
- Forty-round regression tests: PASS (\`$FORTY_CHECKS\` checks)
- Deterministic double build: PASS (byte-identical)
- ZIP path/integrity and clean-extract syntax: PASS (\`$ZIP_ENTRIES\` entries)
- Plugin source files: \`$SOURCE_COUNT\`
- Plugin source bytes: \`$SOURCE_BYTES\`
- Package: \`$ZIP_NAME\`
- Package SHA-256: \`$ZIP_HASH\`
- Source manifest: \`dist/SOURCE-MANIFEST.sha256\`

## Corrective scope

Baseline version 1.2.0 added atomic schema upgrade verification, durable event inbox and row-level outbox leases, database-atomic rate limiting, production-grade encryption-key requirements and rotation, decrypt-failure overwrite protection, bounded PII/log/provider ingestion, stricter source/licence/provenance gates, transactional publish/correct/retract operations, approval separation, stable pagination, current eligibility revalidation, POST health-query transport, lazy schema loading and expanded regression evidence.

Baseline version 1.2.1 repaired the public trend report sort/cursor mismatch, tests backward-compatible cursor decoding, and restores four-plan compliance to the mandatory build gate. The database schema remains 1.2.0. These candidate fixes do not constitute staging/live verification.\n\n## Second corrective audit

Version 1.2.2 adds urgent-care diversion before radar provider lookup, single-keyword research privacy, verified File 06 legacy eligibility, source-grounded external mappings, and forty sequential post-correction source/test review passes. These are automated repository checks, not forty live clinical workflows. Schema remains 1.2.0.

## Evidence qualification

This document establishes source implementation, deterministic package integrity and automated checks in the available build environment. It does not establish Hostinger staging, real companion-plugin/provider contracts, measured browser/accessibility/performance acceptance, backup/restore rehearsal, production deployment or operational service. Those remain explicit external gates.
EOF

printf 'Build complete: %s\nSHA-256: %s\n' "$ZIP_PATH" "$ZIP_HASH"
