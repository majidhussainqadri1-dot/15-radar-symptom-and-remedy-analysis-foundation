#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_DIR="$ROOT/15-radar-symptom-and-remedy-analysis-foundation"
DIST="$ROOT/dist"
VERSION="1.1.0"
ZIP_NAME="15-radar-symptom-and-remedy-analysis-foundation-${VERSION}.zip"
ZIP_PATH="$DIST/$ZIP_NAME"

rm -rf "$DIST"
mkdir -p "$DIST"

printf 'Running PHP lint...\n'
while IFS= read -r -d '' file; do
    php -l "$file"
done < <(find "$PLUGIN_DIR" "$ROOT/tests" -type f -name '*.php' -print0 | sort -z) > "$DIST/php-lint.log"

printf 'Running JavaScript syntax checks...\n'
while IFS= read -r -d '' file; do
    node --check "$file"
done < <(find "$PLUGIN_DIR/assets/js" -type f -name '*.js' -print0 | sort -z)
printf 'PASS JavaScript syntax\n' > "$DIST/javascript-syntax.log"

printf 'Running domain/security tests...\n'
php "$ROOT/tests/run.php" | tee "$DIST/domain-tests.log"
printf 'Running static requirement/regression tests...\n'
php "$ROOT/tests/static-regression.php" | tee "$DIST/static-regression.log"
printf 'Running four-plan compliance regression tests...\n'
php "$ROOT/tests/four-plan-compliance.php" | tee "$DIST/four-plan-compliance.log"

printf 'Generating source manifest...\n'
(
    cd "$ROOT"
    find "$(basename "$PLUGIN_DIR")" -type f -print0 | sort -z | xargs -0 sha256sum
) > "$DIST/SOURCE-MANIFEST.sha256"

printf 'Building deterministic ZIP...\n'
python3 - "$ROOT" "$PLUGIN_DIR" "$ZIP_PATH" <<'PY'
import os
import stat
import sys
import zipfile

root, plugin_dir, zip_path = sys.argv[1:]
fixed_time = (2026, 8, 6, 17, 30, 0)
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

printf 'Verifying ZIP paths and required files...\n'
python3 - "$ZIP_PATH" <<'PY'
import sys
import zipfile
path = sys.argv[1]
required = {
    '15-radar-symptom-and-remedy-analysis-foundation/radar-symptom-remedy-analysis.php',
    '15-radar-symptom-and-remedy-analysis-foundation/readme.txt',
    '15-radar-symptom-and-remedy-analysis-foundation/uninstall.php',
    '15-radar-symptom-and-remedy-analysis-foundation/includes/class-rsr-four-plan-compliance.php',
}
with zipfile.ZipFile(path) as z:
    names = z.namelist()
    unsafe = [n for n in names if n.startswith('/') or '..' in n.split('/')]
    missing = sorted(required.difference(names))
    if unsafe or missing:
        raise SystemExit(f'Unsafe paths={unsafe}; missing={missing}')
    bad_roots = [n for n in names if not n.startswith('15-radar-symptom-and-remedy-analysis-foundation/')]
    if bad_roots:
        raise SystemExit(f'Unexpected ZIP roots: {bad_roots[:5]}')
print(f'PASS ZIP integrity: {len(names)} entries')
PY

sha256sum "$ZIP_PATH" > "$DIST/$ZIP_NAME.sha256"
SOURCE_COUNT="$(find "$PLUGIN_DIR" -type f | wc -l | tr -d ' ')"
SOURCE_BYTES="$(find "$PLUGIN_DIR" -type f -printf '%s\n' | awk '{s+=$1} END {print s+0}')"
PHP_COUNT="$(find "$PLUGIN_DIR" -type f -name '*.php' | wc -l | tr -d ' ')"
ZIP_HASH="$(cut -d' ' -f1 "$DIST/$ZIP_NAME.sha256")"
BUILD_UTC="$(date -u +'%Y-%m-%dT%H:%M:%SZ')"

cat > "$ROOT/docs/RELEASE-EVIDENCE.md" <<EOF
# Release Evidence — 1.1.0

Generated: \`$BUILD_UTC\`

## Automated result

- PHP lint: PASS (\`$PHP_COUNT\` plugin PHP files plus tests)
- JavaScript syntax: PASS
- Domain/security tests: PASS
- Static requirement/regression tests: PASS
- Four-plan compliance regression tests: PASS
- ZIP path/integrity validation: PASS
- Plugin source files: \`$SOURCE_COUNT\`
- Plugin source bytes: \`$SOURCE_BYTES\`
- Package: \`$ZIP_NAME\`
- Package SHA-256: \`$ZIP_HASH\`
- Source manifest: \`dist/SOURCE-MANIFEST.sha256\`

## Four-plan corrective scope

The release hardens File 15 against the Definitive Master Plan v3.0, the recovered directive register v2.1, the Continuous Value/Top-20 Superset plan, and File 15's dedicated master plan. Corrections include fail-closed current claim checks, health-query no-store/no-referrer handling, explainable non-clinical result ordering, zero-result recovery without fabricated remedies, red-flag escalation, current File 06 remedy validation for Saved Studies, encryption failure safety, File 20 context-control consumption, canonical schema keys, localized client states, and explicit cross-file ownership metadata.

## Covered implementation

All functional requirements F15-FR-001 through F15-FR-018 and source-level controls for F15-NFR-001 through F15-NFR-010 are represented in the canonical source and traceability matrix. Version 1.1.0 adds regression evidence for the four-plan audit findings.

## Explicitly separate external gates

This evidence does not claim Hostinger/WordPress staging acceptance, production deployment, real provider authorization, operational staffing or live service levels. Those require the external acceptance matrix and Founder approval.
EOF

printf 'Build complete: %s\nSHA-256: %s\n' "$ZIP_PATH" "$ZIP_HASH"
