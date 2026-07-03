#!/usr/bin/env bash
set -euo pipefail

APP_URL="${APP_URL:-https://b2b.example.com}"
APP_DIR="${APP_DIR:-/var/www/bbb/current}"
PHP_BIN="${PHP_BIN:-/usr/bin/php}"
TIMEOUT="${TIMEOUT:-5}"
HEALTHCHECK_ARTIFACT_DIR="${HEALTHCHECK_ARTIFACT_DIR:-${APP_DIR}/storage/logs}"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
LOG_FILE="${HEALTHCHECK_ARTIFACT_DIR}/b2b-healthcheck-${STAMP}.log"
RELEASE_CHECK_LOG="${HEALTHCHECK_ARTIFACT_DIR}/b2b-release-check-${STAMP}.log"

mkdir -p "${HEALTHCHECK_ARTIFACT_DIR}"

log() {
  printf '%s %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$*" | tee -a "${LOG_FILE}"
}

log "Starting B2B healthcheck for ${APP_URL}"

READINESS_RESPONSE="${HEALTHCHECK_ARTIFACT_DIR}/readiness-${STAMP}.json"
METRICS_RESPONSE="${HEALTHCHECK_ARTIFACT_DIR}/metrics-${STAMP}.txt"
curl -fsS --max-time "${TIMEOUT}" "${APP_URL%/}/api/b2b/v1/readiness" > "${READINESS_RESPONSE}"
grep -q '"status":"ready"' "${READINESS_RESPONSE}"
log "PASS readiness ${READINESS_RESPONSE}"

curl -fsS --max-time "${TIMEOUT}" "${APP_URL%/}/api/b2b/v1/metrics" > "${METRICS_RESPONSE}"
grep -q 'bbb_b2b_info' "${METRICS_RESPONSE}"
log "PASS metrics ${METRICS_RESPONSE}"

if [[ -n "${WEBSOCKET_TCP_HOST:-}" && -n "${WEBSOCKET_TCP_PORT:-}" ]]; then
  timeout "${TIMEOUT}" bash -c "</dev/tcp/${WEBSOCKET_TCP_HOST}/${WEBSOCKET_TCP_PORT}"
  log "PASS websocket_tcp ${WEBSOCKET_TCP_HOST}:${WEBSOCKET_TCP_PORT}"
fi

if [[ -n "${WEBSOCKET_HEALTH_URL:-}" ]]; then
  WEBSOCKET_HEALTH_RESPONSE="${HEALTHCHECK_ARTIFACT_DIR}/websocket-health-${STAMP}.json"
  curl -fsS --max-time "${TIMEOUT}" "${WEBSOCKET_HEALTH_URL}" > "${WEBSOCKET_HEALTH_RESPONSE}"
  grep -q '"service":"bbb-websocket"' "${WEBSOCKET_HEALTH_RESPONSE}"
  log "PASS websocket_health ${WEBSOCKET_HEALTH_RESPONSE}"
fi

cd "${APP_DIR}"
"${PHP_BIN}" artisan b2b:release-check --production --no-interaction >"${RELEASE_CHECK_LOG}"
log "PASS release_check ${RELEASE_CHECK_LOG}"

log "Health check passed. Artifact log: ${LOG_FILE}"
echo "Health check passed. Artifact log: ${LOG_FILE}"
