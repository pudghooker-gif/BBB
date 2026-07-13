#!/usr/bin/env bash
set -euo pipefail

APP_URL="${APP_URL:-https://b2b.example.com}"
APP_DIR="${APP_DIR:-/var/www/bbb/current}"
PHP_BIN="${PHP_BIN:-/usr/bin/php}"
CURL_BIN="${CURL_BIN:-curl}"
OPENSSL_BIN="${OPENSSL_BIN:-openssl}"
PNPM_BIN="${PNPM_BIN:-pnpm}"
TIMEOUT="${TIMEOUT:-10}"
TLS_MIN_VALID_SECONDS="${TLS_MIN_VALID_SECONDS:-604800}"
FINAL_TOPOLOGY_ARTIFACT_DIR="${FINAL_TOPOLOGY_ARTIFACT_DIR:-${APP_DIR}/storage/logs}"
FINAL_TOPOLOGY_ARTIFACT="${FINAL_TOPOLOGY_ARTIFACT:-${FINAL_TOPOLOGY_ARTIFACT_DIR}/final-domains-tls-proxy-redis-queue-scheduler-validation.log}"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"

mkdir -p "$(dirname "$FINAL_TOPOLOGY_ARTIFACT")"
: > "$FINAL_TOPOLOGY_ARTIFACT"

log() {
    printf '%s %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$*" | tee -a "$FINAL_TOPOLOGY_ARTIFACT"
}

fail() {
    log "FAIL $*"
    exit 1
}

require_command() {
    local command_name="$1"
    if ! command -v "$command_name" >/dev/null 2>&1 && [[ ! -x "$command_name" ]]; then
        fail "Required command is missing: $command_name"
    fi
}

url_part() {
    "$PHP_BIN" -r '
        $parts = parse_url($argv[1]);
        if (!is_array($parts)) {
            exit(1);
        }
        $key = $argv[2];
        if (!array_key_exists($key, $parts)) {
            exit(2);
        }
        echo $parts[$key];
    ' "$APP_URL" "$1"
}

config_show() {
    "$PHP_BIN" artisan config:show "$1" --no-ansi 2>&1
}

require_command "$PHP_BIN"
require_command "$CURL_BIN"
require_command "$OPENSSL_BIN"
require_command "$PNPM_BIN"

case "$APP_URL" in
    https://*) ;;
    *) fail "APP_URL must use https:// for production topology validation." ;;
esac

if [[ "$APP_URL" == *"example.com"* || "$APP_URL" == *"example.invalid"* ]]; then
    fail "APP_URL must be replaced with the final production domain."
fi

TLS_HOST="$(url_part host || true)"
TLS_PORT="$(url_part port || true)"
TLS_PORT="${TLS_PORT:-443}"
if [[ -z "$TLS_HOST" ]]; then
    fail "Unable to parse APP_URL host."
fi

log "Starting final topology validation for ${APP_URL%/}"
log "artifact=${FINAL_TOPOLOGY_ARTIFACT}"
log "tls_host=${TLS_HOST} tls_port=${TLS_PORT}"

CERT_FILE="$(mktemp)"
trap 'rm -f "$CERT_FILE"' EXIT

if ! echo | "$OPENSSL_BIN" s_client -servername "$TLS_HOST" -connect "${TLS_HOST}:${TLS_PORT}" -verify_return_error 2>/dev/null \
    | "$OPENSSL_BIN" x509 -outform PEM > "$CERT_FILE"; then
    fail "TLS certificate could not be fetched or verified for ${TLS_HOST}:${TLS_PORT}."
fi

"$OPENSSL_BIN" x509 -in "$CERT_FILE" -noout -subject -issuer -enddate | sed 's/^/tls_cert_/' | tee -a "$FINAL_TOPOLOGY_ARTIFACT" >/dev/null
if ! "$OPENSSL_BIN" x509 -in "$CERT_FILE" -checkend "$TLS_MIN_VALID_SECONDS" -noout >/dev/null; then
    fail "TLS certificate expires within ${TLS_MIN_VALID_SECONDS} seconds."
fi
SAN_OUTPUT="$("$OPENSSL_BIN" x509 -in "$CERT_FILE" -noout -ext subjectAltName 2>/dev/null || true)"
TLS_PARENT="${TLS_HOST#*.}"
if ! printf '%s\n' "$SAN_OUTPUT" | grep -Fq "DNS:${TLS_HOST}" \
    && ! { [[ "$TLS_PARENT" != "$TLS_HOST" ]] && printf '%s\n' "$SAN_OUTPUT" | grep -Fq "DNS:*.${TLS_PARENT}"; }; then
    fail "TLS certificate SAN does not include DNS:${TLS_HOST} or DNS:*.${TLS_PARENT}."
fi
log "PASS tls_certificate host=${TLS_HOST} min_valid_seconds=${TLS_MIN_VALID_SECONDS}"

READINESS_RESPONSE="${FINAL_TOPOLOGY_ARTIFACT_DIR}/final-topology-readiness-${STAMP}.json"
METRICS_RESPONSE="${FINAL_TOPOLOGY_ARTIFACT_DIR}/final-topology-metrics-${STAMP}.txt"

"$CURL_BIN" --fail --silent --show-error --max-time "$TIMEOUT" \
    --header 'Accept: application/json' \
    "${APP_URL%/}/api/b2b/v1/readiness" > "$READINESS_RESPONSE"
grep -q '"status":"ready"' "$READINESS_RESPONSE"
log "PASS public_readiness response=${READINESS_RESPONSE}"

"$CURL_BIN" --fail --silent --show-error --max-time "$TIMEOUT" \
    --header 'Accept: text/plain' \
    "${APP_URL%/}/api/b2b/v1/metrics" > "$METRICS_RESPONSE"
grep -q 'bbb_b2b_info' "$METRICS_RESPONSE"
grep -q 'bbb_b2b_scheduler_heartbeat_fresh' "$METRICS_RESPONSE"
log "PASS public_metrics response=${METRICS_RESPONSE}"

cd "$APP_DIR"

log "Checking Laravel trusted proxy and shared-state configuration."
TRUSTED_PROXY_OUTPUT="$(config_show trustedproxy.proxies)"
QUEUE_OUTPUT="$(config_show queue.default)"
NONCE_OUTPUT="$(config_show b2b.nonce_cache_store)"
RATE_OUTPUT="$(config_show b2b.rate_limit_cache_store)"
GAME_CATALOG_OUTPUT="$(config_show b2b.game_catalog_cache_store)"
SCHEDULER_OUTPUT="$(config_show b2b.scheduler_heartbeat_cache_store)"

printf '%s\n%s\n%s\n%s\n%s\n%s\n' \
    "$TRUSTED_PROXY_OUTPUT" \
    "$QUEUE_OUTPUT" \
    "$NONCE_OUTPUT" \
    "$RATE_OUTPUT" \
    "$GAME_CATALOG_OUTPUT" \
    "$SCHEDULER_OUTPUT" \
    | sed 's/^/config /' | tee -a "$FINAL_TOPOLOGY_ARTIFACT" >/dev/null

if printf '%s\n' "$TRUSTED_PROXY_OUTPUT" | grep -Eq '\bnull\b'; then
    fail "trustedproxy.proxies is null; set TRUSTED_PROXIES to the final proxy IP/CIDR list."
fi
printf '%s\n' "$QUEUE_OUTPUT" | grep -Eq '\bredis\b' || fail "queue.default is not redis."
printf '%s\n' "$NONCE_OUTPUT" | grep -Eq '\bredis\b' || fail "b2b.nonce_cache_store is not redis."
printf '%s\n' "$RATE_OUTPUT" | grep -Eq '\bredis\b' || fail "b2b.rate_limit_cache_store is not redis."
printf '%s\n' "$GAME_CATALOG_OUTPUT" | grep -Eq '\bredis\b' || fail "b2b.game_catalog_cache_store is not redis."
printf '%s\n' "$SCHEDULER_OUTPUT" | grep -Eq '\bredis\b' || fail "b2b.scheduler_heartbeat_cache_store is not redis."
log "PASS laravel_topology_config trusted_proxy_and_redis"

"$PHP_BIN" artisan b2b:scheduler-heartbeat --source=final-topology-check --no-interaction >> "$FINAL_TOPOLOGY_ARTIFACT"
log "PASS scheduler_heartbeat"

"$PHP_BIN" artisan b2b:release-check --production --no-interaction >> "$FINAL_TOPOLOGY_ARTIFACT"
grep -q 'PASS queue_driver' "$FINAL_TOPOLOGY_ARTIFACT"
grep -q 'PASS game_catalog_cache' "$FINAL_TOPOLOGY_ARTIFACT"
grep -q 'PASS scheduler_heartbeat_cache' "$FINAL_TOPOLOGY_ARTIFACT"
grep -q 'PASS release_secret_files' "$FINAL_TOPOLOGY_ARTIFACT"
log "PASS production_release_gate"

WEBSOCKET_PUBLIC_URL="${WEBSOCKET_PUBLIC_URL:-wss://${TLS_HOST}:12096}"
WEBSOCKET_PUBLIC_ORIGIN="${WEBSOCKET_PUBLIC_ORIGIN:-${APP_URL%/}}"
WEBSOCKET_SMOKE_ARTIFACT_DIR="${FINAL_TOPOLOGY_ARTIFACT_DIR}" \
WEBSOCKET_PUBLIC_URL="$WEBSOCKET_PUBLIC_URL" \
WEBSOCKET_PUBLIC_ORIGIN="$WEBSOCKET_PUBLIC_ORIGIN" \
"$PNPM_BIN" --dir "$APP_DIR/PTWebSocket" run smoke:public-proxy >> "$FINAL_TOPOLOGY_ARTIFACT"
log "PASS websocket_public_proxy url=${WEBSOCKET_PUBLIC_URL} origin=${WEBSOCKET_PUBLIC_ORIGIN}"

log "Final topology validation passed. Artifact: ${FINAL_TOPOLOGY_ARTIFACT}"
