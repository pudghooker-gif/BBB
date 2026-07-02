#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/bbb/current}"
PHP_BIN="${PHP_BIN:-/usr/bin/php}"
ARTIFACT_DIR="${ARTIFACT_DIR:-${APP_DIR}/storage/logs}"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
LOG_FILE="${ARTIFACT_DIR}/b2b-migration-rehearsal-${STAMP}.log"

if [ "${CONFIRM_STAGING_MIGRATION:-}" != "STAGING_MIGRATION_REHEARSAL" ]; then
    echo "Set CONFIRM_STAGING_MIGRATION=STAGING_MIGRATION_REHEARSAL to run migration rehearsal on a staging database copy." >&2
    exit 2
fi

if [ ! -f "${APP_DIR}/artisan" ]; then
    echo "APP_DIR does not look like a Laravel app: ${APP_DIR}" >&2
    exit 3
fi

mkdir -p "${ARTIFACT_DIR}"
cd "${APP_DIR}"

cleanup_boot_cache() {
    "${PHP_BIN}" artisan route:clear --no-interaction >/dev/null 2>&1 || true
    "${PHP_BIN}" artisan config:clear --no-interaction >/dev/null 2>&1 || true
}
trap cleanup_boot_cache EXIT

APP_ENV_VALUE="$("${PHP_BIN}" artisan env --no-interaction | awk -F': ' '/Current application environment/ {print $2}' | tr -d '\r')"
if [ "${APP_ENV_VALUE}" = "production" ]; then
    echo "Refusing to run migration rehearsal against APP_ENV=production." >&2
    exit 4
fi

{
    echo "BBB B2B migration rehearsal ${STAMP}"
    echo "app_dir=${APP_DIR}"
    echo "app_env=${APP_ENV_VALUE:-unknown}"
    echo ""
    echo "== clear caches =="
    "${PHP_BIN}" artisan optimize:clear --no-interaction
    echo ""
    echo "== migration status before =="
    "${PHP_BIN}" artisan migrate:status --no-interaction
    echo ""
    echo "== migration SQL preview =="
    "${PHP_BIN}" artisan migrate --pretend --force --no-interaction
    echo ""
    echo "== apply migrations =="
    "${PHP_BIN}" artisan migrate --force --no-interaction
    echo ""
    echo "== migration status after =="
    "${PHP_BIN}" artisan migrate:status --no-interaction
    echo ""
    echo "== route/config boot =="
    "${PHP_BIN}" artisan config:cache --no-interaction
    "${PHP_BIN}" artisan route:cache --no-interaction
    "${PHP_BIN}" artisan route:clear --no-interaction
    "${PHP_BIN}" artisan config:clear --no-interaction
    "${PHP_BIN}" artisan optimize:clear --no-interaction
} | tee "${LOG_FILE}"

echo "Migration rehearsal completed. Log: ${LOG_FILE}"
