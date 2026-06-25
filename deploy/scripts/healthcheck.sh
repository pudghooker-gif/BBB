#!/usr/bin/env bash
set -euo pipefail

APP_URL="${APP_URL:-https://b2b.example.com}"
APP_DIR="${APP_DIR:-/var/www/bbb/current}"
PHP_BIN="${PHP_BIN:-/usr/bin/php}"
TIMEOUT="${TIMEOUT:-5}"

curl -fsS --max-time "${TIMEOUT}" "${APP_URL%/}/api/b2b/v1/health" | grep -q '"success":true'

cd "${APP_DIR}"
"${PHP_BIN}" artisan b2b:release-check --production --no-interaction >/tmp/bbb-b2b-release-check.out

echo "Health check passed"
