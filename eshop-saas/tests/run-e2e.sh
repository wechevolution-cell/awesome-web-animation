#!/usr/bin/env bash
# Fresh-install browser E2E: starts a throwaway server on :8001 with its own database.
set -e
cd "$(dirname "$0")/.."
OUT="${1:-/tmp/eshop-e2e}"; mkdir -p "$OUT"; rm -f "$OUT"/e2e.sqlite*
APP_DB="$OUT/e2e.sqlite" APP_NO_MAIL=1 php -S 127.0.0.1:8001 index.php > "$OUT/server.log" 2>&1 &
SRV=$!; trap 'kill $SRV' EXIT; sleep 1
PW_PATH="${PW_PATH:-/opt/node22/lib/node_modules/playwright}" node tests/e2e.mjs http://127.0.0.1:8001 "$OUT/shots"
