#!/usr/bin/env bash
set -euo pipefail

APP_URL="${APP_URL:-https://b2b.example.com}"
PHP_BIN="${PHP_BIN:-php}"
CURL_BIN="${CURL_BIN:-curl}"
B2B_SMOKE_TIMEOUT_SECONDS="${B2B_SMOKE_TIMEOUT_SECONDS:-10}"
B2B_SMOKE_ARTIFACT_DIR="${B2B_SMOKE_ARTIFACT_DIR:-storage/logs}"

mkdir -p "$B2B_SMOKE_ARTIFACT_DIR"
SMOKE_LOG="$B2B_SMOKE_ARTIFACT_DIR/b2b-smoke-$(date -u +%Y%m%dT%H%M%SZ).log"

log() {
    printf '%s %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$*" | tee -a "$SMOKE_LOG"
}

url() {
    local path="$1"
    printf '%s/%s' "${APP_URL%/}" "${path#/}"
}

assert_contains() {
    local file="$1"
    local needle="$2"
    local label="$3"

    if ! grep -Fq "$needle" "$file"; then
        log "FAIL $label missing expected marker: $needle"
        return 1
    fi
}

curl_get() {
    local label="$1"
    local path="$2"
    local output="$B2B_SMOKE_ARTIFACT_DIR/$label.json"

    log "GET $path"
    "$CURL_BIN" --fail --silent --show-error --max-time "$B2B_SMOKE_TIMEOUT_SECONDS" \
        --header 'Accept: application/json' \
        "$(url "$path")" > "$output"
    printf '%s' "$output"
}

signed_get() {
    local label="$1"
    local path="$2"
    local query="${3:-}"
    local output="$B2B_SMOKE_ARTIFACT_DIR/$label.json"
    local timestamp nonce body_hash canonical signature request_path

    timestamp="$(date +%s)"
    nonce="smoke-$(date -u +%Y%m%dT%H%M%SZ)-$RANDOM"
    body_hash="$("$PHP_BIN" -r 'echo hash("sha256", "");')"
    canonical="$(METHOD=GET PATH_INFO="$path" QUERY_STRING="$query" BODY_HASH="$body_hash" TS="$timestamp" NONCE="$nonce" "$PHP_BIN" -r 'echo implode("\n", [getenv("METHOD"), "/" . ltrim(getenv("PATH_INFO"), "/"), getenv("QUERY_STRING"), strtolower(getenv("BODY_HASH")), getenv("TS"), getenv("NONCE")]);')"
    signature="$(SIGN_SECRET="$B2B_SMOKE_API_SECRET" CANONICAL="$canonical" "$PHP_BIN" -r 'echo hash_hmac("sha256", getenv("CANONICAL"), getenv("SIGN_SECRET"));')"
    request_path="$path"
    if [ -n "$query" ]; then
        request_path="$path?$query"
    fi

    log "SIGNED GET $request_path"
    "$CURL_BIN" --fail --silent --show-error --max-time "$B2B_SMOKE_TIMEOUT_SECONDS" \
        --header 'Accept: application/json' \
        --header "X-Operator-Id: $B2B_SMOKE_OPERATOR_ID" \
        --header "X-Api-Key: $B2B_SMOKE_API_KEY" \
        --header "X-Timestamp: $timestamp" \
        --header "X-Nonce: $nonce" \
        --header "X-Body-Hash: $body_hash" \
        --header "X-Signature: $signature" \
        "$(url "$request_path")" > "$output"
    printf '%s' "$output"
}

log "Starting B2B smoke checks for $APP_URL"

health_file="$(curl_get health /api/b2b/v1/health)"
assert_contains "$health_file" '"success":true' health

readiness_file="$(curl_get readiness /api/b2b/v1/readiness)"
assert_contains "$readiness_file" '"status":"ready"' readiness

metrics_file="$B2B_SMOKE_ARTIFACT_DIR/metrics.txt"
log "GET /api/b2b/v1/metrics"
"$CURL_BIN" --fail --silent --show-error --max-time "$B2B_SMOKE_TIMEOUT_SECONDS" \
    --header 'Accept: text/plain' \
    "$(url /api/b2b/v1/metrics)" > "$metrics_file"
assert_contains "$metrics_file" 'bbb_b2b_info' metrics

if [ -n "${B2B_SMOKE_OPERATOR_ID:-}" ] && [ -n "${B2B_SMOKE_API_KEY:-}" ] && [ -n "${B2B_SMOKE_API_SECRET:-}" ]; then
    operator_file="$(signed_get operator-me /api/b2b/v1/operator/me)"
    assert_contains "$operator_file" '"success":true' operator-me

    portal_file="$(signed_get portal-overview /api/b2b/v1/portal/overview limit=1)"
    assert_contains "$portal_file" '"success":true' portal-overview
else
    log "Skipping signed operator smoke checks; B2B_SMOKE_OPERATOR_ID, B2B_SMOKE_API_KEY, and B2B_SMOKE_API_SECRET were not all provided."
fi

log "B2B smoke checks completed. Artifact log: $SMOKE_LOG"
