#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="${APP_ROOT:-/var/www/bbb}"
CURRENT_LINK="${CURRENT_LINK:-${APP_ROOT}/current}"
TARGET_RELEASE="${1:-}"
PHP_BIN="${PHP_BIN:-/usr/bin/php}"
ROLLBACK_ARTIFACT_DIR="${ROLLBACK_ARTIFACT_DIR:-${APP_ROOT}/release-evidence/rollback}"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
LOG_FILE="${ROLLBACK_ARTIFACT_DIR}/b2b-rollback-${STAMP}.log"
RELEASE_CHECK_LOG="${ROLLBACK_ARTIFACT_DIR}/b2b-rollback-release-check-${STAMP}.log"

log() {
    printf '%s %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$*" | tee -a "${LOG_FILE}"
}

if [ -z "${TARGET_RELEASE}" ]; then
    echo "Usage: rollback.sh /var/www/bbb/releases/<release-id>" >&2
    exit 2
fi

if [ ! -f "${TARGET_RELEASE}/artisan" ]; then
    echo "Target release does not look like a Laravel app: ${TARGET_RELEASE}" >&2
    exit 3
fi

PREVIOUS="$(readlink -f "${CURRENT_LINK}" || true)"
mkdir -p "${ROLLBACK_ARTIFACT_DIR}"
log "Starting BBB B2B rollback ${STAMP}"
log "current_link=${CURRENT_LINK}"
log "previous_release=${PREVIOUS:-unknown}"
log "target_release=${TARGET_RELEASE}"
ln -sfn "${TARGET_RELEASE}" "${CURRENT_LINK}"
log "PASS symlink_switch ${CURRENT_LINK}"

cd "${CURRENT_LINK}"
"${PHP_BIN}" artisan config:clear --no-interaction
"${PHP_BIN}" artisan route:clear --no-interaction
"${PHP_BIN}" artisan view:clear --no-interaction
"${PHP_BIN}" artisan b2b:release-check --production --no-interaction >"${RELEASE_CHECK_LOG}"
log "PASS release_check ${RELEASE_CHECK_LOG}"

if command -v systemctl >/dev/null 2>&1; then
    systemctl reload php8.3-fpm || true
    systemctl restart bbb-websocket || true
    log "PASS systemd_reload_attempted"
fi

if command -v supervisorctl >/dev/null 2>&1; then
    supervisorctl reread || true
    supervisorctl update || true
    supervisorctl restart 'bbb-b2b-*' || true
    log "PASS supervisor_reload_attempted"
fi

log "Rollback switched ${CURRENT_LINK} to ${TARGET_RELEASE}. Artifact log: ${LOG_FILE}"
echo "Rollback switched ${CURRENT_LINK} to ${TARGET_RELEASE}. Artifact log: ${LOG_FILE}"
if [ -n "${PREVIOUS}" ]; then
    echo "Previous release was ${PREVIOUS}"
fi
