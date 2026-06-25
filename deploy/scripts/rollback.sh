#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="${APP_ROOT:-/var/www/bbb}"
CURRENT_LINK="${CURRENT_LINK:-${APP_ROOT}/current}"
TARGET_RELEASE="${1:-}"
PHP_BIN="${PHP_BIN:-/usr/bin/php}"

if [ -z "${TARGET_RELEASE}" ]; then
    echo "Usage: rollback.sh /var/www/bbb/releases/<release-id>" >&2
    exit 2
fi

if [ ! -f "${TARGET_RELEASE}/artisan" ]; then
    echo "Target release does not look like a Laravel app: ${TARGET_RELEASE}" >&2
    exit 3
fi

PREVIOUS="$(readlink -f "${CURRENT_LINK}" || true)"
ln -sfn "${TARGET_RELEASE}" "${CURRENT_LINK}"

cd "${CURRENT_LINK}"
"${PHP_BIN}" artisan config:clear --no-interaction
"${PHP_BIN}" artisan route:clear --no-interaction
"${PHP_BIN}" artisan view:clear --no-interaction
"${PHP_BIN}" artisan b2b:release-check --production --no-interaction

if command -v systemctl >/dev/null 2>&1; then
    systemctl reload php7.4-fpm || true
    systemctl restart bbb-websocket || true
fi

if command -v supervisorctl >/dev/null 2>&1; then
    supervisorctl reread || true
    supervisorctl update || true
    supervisorctl restart 'bbb-b2b-*' || true
fi

echo "Rollback switched ${CURRENT_LINK} to ${TARGET_RELEASE}"
if [ -n "${PREVIOUS}" ]; then
    echo "Previous release was ${PREVIOUS}"
fi
