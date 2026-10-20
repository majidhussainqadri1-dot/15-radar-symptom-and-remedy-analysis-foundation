#!/usr/bin/env bash
# Forty sequential automated source/test review passes, each with its own
# distinct plan/ownership/quality lens. No staging/live claims are implied.
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
LOG_DIR="${1:-$ROOT/dist}"
mkdir -p "$LOG_DIR"
LOG="$LOG_DIR/third-forty-rounds.log"
: > "$LOG"
for round in $(seq 1 40); do
  label="$(printf '%02d' "$round")"
  echo "BEGIN source-review round $label" >> "$LOG"
  if ! php "$ROOT/tests/review-forty-focused.php" "$round" >> "$LOG" 2>&1 ||
     ! php "$ROOT/tests/run.php" >> "$LOG" 2>&1 ||
     ! php "$ROOT/tests/clinical-boundary-regression.php" >> "$LOG" 2>&1 ||
     ! php "$ROOT/tests/html-input-regression.php" >> "$LOG" 2>&1 ||
     ! php "$ROOT/tests/static-regression.php" >> "$LOG" 2>&1 ||
     ! php "$ROOT/tests/four-plan-compliance.php" >> "$LOG" 2>&1 ||
     ! php "$ROOT/tests/forty-round-regression.php" >> "$LOG" 2>&1; then
    echo "FAIL: source-review round $label blocked before subsequent round" >&2
    tail -n 50 "$LOG" >&2
    exit 1
  fi
  echo "PASS source-review round $label (distinct check + complete source regression suite)" >> "$LOG"
done
echo 'PASS 40 sequential source/test review rounds (NOT live or end-to-end clinical tests)'
