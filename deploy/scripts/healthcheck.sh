#!/usr/bin/env bash
set -euo pipefail

APP_URL="${APP_URL:-https://b2b.example.com}"
APP_DIR="${APP_DIR:-/var/www/bbb/current}"
PHP_BIN="${PHP_BIN:-/usr/bin/php}"
TIMEOUT="${TIMEOUT:-5}"

curl -fsS --max-time "${TIMEOUT}" "${APP_URL%/}/api/b2b/v1/readiness" | grep -q '"status":"ready"'
curl -fsS --max-time "${TIMEOUT}" "${APP_URL%/}/api/b2b/v1/metrics" | grep -q 'bbb_b2b_info'

if [[ -n "${WEBSOCKET_TCP_HOST:-}" && -n "${WEBSOCKET_TCP_PORT:-}" ]]; then
  timeout "${TIMEOUT}" bash -c "</dev/tcp/${WEBSOCKET_TCP_HOST}/${WEBSOCKET_TCP_PORT}"
fi

cd "${APP_DIR}"
"${PHP_BIN}" artisan b2b:release-check --production --no-interaction >/tmp/bbb-b2b-release-check.out

echo "Health check passed"
