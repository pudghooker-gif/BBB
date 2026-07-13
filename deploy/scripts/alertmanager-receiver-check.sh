#!/usr/bin/env bash
set -euo pipefail

ALERTMANAGER_RECEIVER_EXPORT_FILE="${ALERTMANAGER_RECEIVER_EXPORT_FILE:-}"
ALERTMANAGER_RECEIVER_QUERY_URL="${ALERTMANAGER_RECEIVER_QUERY_URL:-}"
ALERTMANAGER_RECEIVER_QUERY_METHOD="${ALERTMANAGER_RECEIVER_QUERY_METHOD:-GET}"
ALERTMANAGER_RECEIVER_QUERY_BODY="${ALERTMANAGER_RECEIVER_QUERY_BODY:-}"
ALERTMANAGER_RECEIVER_HEADER="${ALERTMANAGER_RECEIVER_HEADER:-}"
ALERTMANAGER_RECEIVER_BEARER_TOKEN="${ALERTMANAGER_RECEIVER_BEARER_TOKEN:-}"
ALERTMANAGER_RECEIVER_ARTIFACT_DIR="${ALERTMANAGER_RECEIVER_ARTIFACT_DIR:-storage/logs}"
ALERTMANAGER_RECEIVER_ARTIFACT="${ALERTMANAGER_RECEIVER_ARTIFACT:-${ALERTMANAGER_RECEIVER_ARTIFACT_DIR}/alertmanager-receiver-delivery-confirmation.log}"
ALERTMANAGER_RECEIVER_TIMEOUT_SECONDS="${ALERTMANAGER_RECEIVER_TIMEOUT_SECONDS:-15}"
ALERTMANAGER_SMOKE_ALERTNAME="${ALERTMANAGER_SMOKE_ALERTNAME:-BBBB2BSmokeNotification}"
ALERTMANAGER_SMOKE_RELEASE_ID="${ALERTMANAGER_SMOKE_RELEASE_ID:-}"
ALERTMANAGER_RECEIVER_EXPECTED_ROUTE="${ALERTMANAGER_RECEIVER_EXPECTED_ROUTE:-}"
CURL_BIN="${CURL_BIN:-curl}"

mkdir -p "$ALERTMANAGER_RECEIVER_ARTIFACT_DIR" "$(dirname "$ALERTMANAGER_RECEIVER_ARTIFACT")"
: > "$ALERTMANAGER_RECEIVER_ARTIFACT"

TMP_RESPONSE=""

cleanup() {
    if [[ -n "$TMP_RESPONSE" && -f "$TMP_RESPONSE" ]]; then
        rm -f "$TMP_RESPONSE"
    fi
}
trap cleanup EXIT

log() {
    printf '%s %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$*" | tee -a "$ALERTMANAGER_RECEIVER_ARTIFACT"
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

SOURCE_FILE=""

if [[ -n "$ALERTMANAGER_RECEIVER_EXPORT_FILE" ]]; then
    if [[ ! -r "$ALERTMANAGER_RECEIVER_EXPORT_FILE" ]]; then
        fail "ALERTMANAGER_RECEIVER_EXPORT_FILE is not readable: $ALERTMANAGER_RECEIVER_EXPORT_FILE"
    fi

    SOURCE_FILE="$ALERTMANAGER_RECEIVER_EXPORT_FILE"
    log "source_type=export_file"
    log "source_file=$(basename "$ALERTMANAGER_RECEIVER_EXPORT_FILE")"
elif [[ -n "$ALERTMANAGER_RECEIVER_QUERY_URL" ]]; then
    TMP_RESPONSE="$(mktemp)"
    CURL_ARGS=(--fail --silent --show-error --max-time "$ALERTMANAGER_RECEIVER_TIMEOUT_SECONDS")

    if [[ -n "$ALERTMANAGER_RECEIVER_BEARER_TOKEN" ]]; then
        CURL_ARGS+=(--header "Authorization: Bearer ${ALERTMANAGER_RECEIVER_BEARER_TOKEN}")
    fi

    if [[ -n "$ALERTMANAGER_RECEIVER_HEADER" ]]; then
        CURL_ARGS+=(--header "$ALERTMANAGER_RECEIVER_HEADER")
    fi

    case "$ALERTMANAGER_RECEIVER_QUERY_METHOD" in
        GET|get)
            ;;
        POST|post)
            CURL_ARGS+=(--request POST --header 'Content-Type: application/json' --data "$ALERTMANAGER_RECEIVER_QUERY_BODY")
            ;;
        *)
            fail "Unsupported ALERTMANAGER_RECEIVER_QUERY_METHOD: $ALERTMANAGER_RECEIVER_QUERY_METHOD"
            ;;
    esac

    "$CURL_BIN" "${CURL_ARGS[@]}" "$ALERTMANAGER_RECEIVER_QUERY_URL" > "$TMP_RESPONSE"
    SOURCE_FILE="$TMP_RESPONSE"
    log "source_type=query_url"
    log "query_url_supplied=true"
    log "bearer_token_supplied=$(bool_text "$ALERTMANAGER_RECEIVER_BEARER_TOKEN")"
    log "extra_header_supplied=$(bool_text "$ALERTMANAGER_RECEIVER_HEADER")"
else
    fail "Set either ALERTMANAGER_RECEIVER_EXPORT_FILE or ALERTMANAGER_RECEIVER_QUERY_URL."
fi

log "alertname=${ALERTMANAGER_SMOKE_ALERTNAME}"
log "release_id_required=$(bool_text "$ALERTMANAGER_SMOKE_RELEASE_ID")"
log "route_required=$(bool_text "$ALERTMANAGER_RECEIVER_EXPECTED_ROUTE")"
log "source_bytes=$(wc -c < "$SOURCE_FILE" | tr -d ' ')"
log "source_sha256=$(sha256_of "$SOURCE_FILE")"

if ! grep -Fq "$ALERTMANAGER_SMOKE_ALERTNAME" "$SOURCE_FILE"; then
    fail "receiver source does not contain smoke alert name"
fi
log "PASS alertname_found"

if [[ -n "$ALERTMANAGER_SMOKE_RELEASE_ID" ]]; then
    if ! grep -Fq "$ALERTMANAGER_SMOKE_RELEASE_ID" "$SOURCE_FILE"; then
        fail "receiver source does not contain release id"
    fi
    log "PASS release_id_found"
fi

if [[ -n "$ALERTMANAGER_RECEIVER_EXPECTED_ROUTE" ]]; then
    if ! grep -Fq "$ALERTMANAGER_RECEIVER_EXPECTED_ROUTE" "$SOURCE_FILE"; then
        fail "receiver source does not contain expected route/receiver"
    fi
    log "PASS receiver_route_found"
fi

for forbidden in \
    'BEGIN PRIVATE KEY' \
    'api_secret=' \
    'APP''_KEY=' \
    'ALERTMANAGER_BEARER_TOKEN='
do
    if grep -Fq "$forbidden" "$SOURCE_FILE"; then
        fail "receiver source contains forbidden marker"
    fi
done
log "PASS receiver_secret_scan"

log "status=passed"
log "receiver_delivery_verified=true"
log "raw_receiver_export_archived=false"
log "Alertmanager receiver delivery confirmation completed. Artifact: ${ALERTMANAGER_RECEIVER_ARTIFACT}"
