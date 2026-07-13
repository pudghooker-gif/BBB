#!/usr/bin/env bash
set -euo pipefail

LOG_SHIPPING_MARKER="${LOG_SHIPPING_MARKER:-}"
LOG_SHIPPING_EXPECTED_EVENT="${LOG_SHIPPING_EXPECTED_EVENT:-observability.log_shipping_check}"
LOG_SHIPPING_EXPORT_FILE="${LOG_SHIPPING_EXPORT_FILE:-}"
LOG_SHIPPING_QUERY_URL="${LOG_SHIPPING_QUERY_URL:-}"
LOG_SHIPPING_QUERY_METHOD="${LOG_SHIPPING_QUERY_METHOD:-GET}"
LOG_SHIPPING_QUERY_BODY="${LOG_SHIPPING_QUERY_BODY:-}"
LOG_SHIPPING_HEADER="${LOG_SHIPPING_HEADER:-}"
LOG_SHIPPING_BEARER_TOKEN="${LOG_SHIPPING_BEARER_TOKEN:-}"
LOG_SHIPPING_ARTIFACT_DIR="${LOG_SHIPPING_ARTIFACT_DIR:-storage/logs}"
LOG_SHIPPING_ARTIFACT="${LOG_SHIPPING_ARTIFACT:-${LOG_SHIPPING_ARTIFACT_DIR}/b2b-log-shipping-external-delivery.log}"
LOG_SHIPPING_TIMEOUT_SECONDS="${LOG_SHIPPING_TIMEOUT_SECONDS:-15}"
CURL_BIN="${CURL_BIN:-curl}"

mkdir -p "$LOG_SHIPPING_ARTIFACT_DIR" "$(dirname "$LOG_SHIPPING_ARTIFACT")"
: > "$LOG_SHIPPING_ARTIFACT"

TMP_RESPONSE=""

cleanup() {
    if [[ -n "$TMP_RESPONSE" && -f "$TMP_RESPONSE" ]]; then
        rm -f "$TMP_RESPONSE"
    fi
}
trap cleanup EXIT

log() {
    printf '%s %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$*" | tee -a "$LOG_SHIPPING_ARTIFACT"
}

fail() {
    log "FAIL $*"
    exit 1
}

sha256_of() {
    local file="$1"

    if command -v sha256sum >/dev/null 2>&1; then
        sha256sum "$file" | awk '{print $1}'
    elif command -v shasum >/dev/null 2>&1; then
        shasum -a 256 "$file" | awk '{print $1}'
    else
        printf 'unavailable'
    fi
}

bool_text() {
    if [[ -n "$1" ]]; then
        printf 'true'
    else
        printf 'false'
    fi
}

if [[ -z "$LOG_SHIPPING_MARKER" ]]; then
    fail "LOG_SHIPPING_MARKER is required. Reuse the marker printed by php artisan b2b:log-shipping-check."
fi

SOURCE_FILE=""
SOURCE_TYPE=""

if [[ -n "$LOG_SHIPPING_EXPORT_FILE" ]]; then
    if [[ ! -r "$LOG_SHIPPING_EXPORT_FILE" ]]; then
        fail "LOG_SHIPPING_EXPORT_FILE is not readable: $LOG_SHIPPING_EXPORT_FILE"
    fi

    SOURCE_FILE="$LOG_SHIPPING_EXPORT_FILE"
    SOURCE_TYPE="export_file"
    log "source_type=export_file"
    log "source_file=$(basename "$LOG_SHIPPING_EXPORT_FILE")"
elif [[ -n "$LOG_SHIPPING_QUERY_URL" ]]; then
    TMP_RESPONSE="$(mktemp)"
    CURL_ARGS=(--fail --silent --show-error --max-time "$LOG_SHIPPING_TIMEOUT_SECONDS")

    if [[ -n "$LOG_SHIPPING_BEARER_TOKEN" ]]; then
        CURL_ARGS+=(--header "Authorization: Bearer ${LOG_SHIPPING_BEARER_TOKEN}")
    fi

    if [[ -n "$LOG_SHIPPING_HEADER" ]]; then
        CURL_ARGS+=(--header "$LOG_SHIPPING_HEADER")
    fi

    case "$LOG_SHIPPING_QUERY_METHOD" in
        GET|get)
            ;;
        POST|post)
            CURL_ARGS+=(--request POST --header 'Content-Type: application/json' --data "$LOG_SHIPPING_QUERY_BODY")
            ;;
        *)
            fail "Unsupported LOG_SHIPPING_QUERY_METHOD: $LOG_SHIPPING_QUERY_METHOD"
            ;;
    esac

    "$CURL_BIN" "${CURL_ARGS[@]}" "$LOG_SHIPPING_QUERY_URL" > "$TMP_RESPONSE"
    SOURCE_FILE="$TMP_RESPONSE"
    SOURCE_TYPE="query_url"
    log "source_type=query_url"
    log "query_url_supplied=true"
    log "bearer_token_supplied=$(bool_text "$LOG_SHIPPING_BEARER_TOKEN")"
    log "extra_header_supplied=$(bool_text "$LOG_SHIPPING_HEADER")"
else
    fail "Set either LOG_SHIPPING_EXPORT_FILE or LOG_SHIPPING_QUERY_URL."
fi

log "expected_event=${LOG_SHIPPING_EXPECTED_EVENT}"
log "marker=${LOG_SHIPPING_MARKER}"
log "source_bytes=$(wc -c < "$SOURCE_FILE" | tr -d ' ')"
log "source_sha256=$(sha256_of "$SOURCE_FILE")"

if ! grep -Fq "$LOG_SHIPPING_MARKER" "$SOURCE_FILE"; then
    fail "external log source does not contain marker"
fi
log "PASS marker_found"

if ! grep -Fq "$LOG_SHIPPING_EXPECTED_EVENT" "$SOURCE_FILE"; then
    fail "external log source does not contain expected event"
fi
log "PASS expected_event_found"

for forbidden in \
    'log-shipping-secret-probe' \
    'Bearer log.shipping.secret' \
    'APP''_KEY=' \
    'BEGIN PRIVATE KEY' \
    'api_secret='
do
    if grep -Fq "$forbidden" "$SOURCE_FILE"; then
        fail "external log source contains forbidden marker"
    fi
done
log "PASS redaction_probe_absent"

log "status=passed"
log "external_delivery_verified=true"
log "raw_external_log_archived=false"
log "log_shipping external delivery check completed. Artifact: ${LOG_SHIPPING_ARTIFACT}"
