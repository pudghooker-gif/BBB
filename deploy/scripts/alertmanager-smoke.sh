#!/usr/bin/env bash
set -euo pipefail

ALERTMANAGER_URL="${ALERTMANAGER_URL:-https://alertmanager.example.invalid}"
CURL_BIN="${CURL_BIN:-curl}"
ALERTMANAGER_SMOKE_TIMEOUT_SECONDS="${ALERTMANAGER_SMOKE_TIMEOUT_SECONDS:-10}"
ALERTMANAGER_ARTIFACT_DIR="${ALERTMANAGER_ARTIFACT_DIR:-storage/logs}"
ALERTMANAGER_ARTIFACT="${ALERTMANAGER_ARTIFACT:-${ALERTMANAGER_ARTIFACT_DIR}/alertmanager-delivery-test.log}"
ALERTMANAGER_SMOKE_RELEASE_ID="${ALERTMANAGER_SMOKE_RELEASE_ID:-manual}"
ALERTMANAGER_SMOKE_ROUTE="${ALERTMANAGER_SMOKE_ROUTE:-b2b-ops}"
ALERTMANAGER_SMOKE_SEVERITY="${ALERTMANAGER_SMOKE_SEVERITY:-warning}"
ALERTMANAGER_SMOKE_ALERTNAME="${ALERTMANAGER_SMOKE_ALERTNAME:-BBBB2BSmokeNotification}"

mkdir -p "$(dirname "$ALERTMANAGER_ARTIFACT")"
: > "$ALERTMANAGER_ARTIFACT"

log() {
    printf '%s %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$*" | tee -a "$ALERTMANAGER_ARTIFACT"
}

json_escape() {
    php -r 'echo json_encode($argv[1], JSON_UNESCAPED_SLASHES);' "$1"
}

if [[ "$ALERTMANAGER_URL" == *"example.invalid"* ]]; then
    log "FAIL ALERTMANAGER_URL must point to the target Alertmanager, not the placeholder."
    exit 1
fi

STARTS_AT="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
ENDS_AT="$(date -u -d '+5 minutes' +%Y-%m-%dT%H:%M:%SZ 2>/dev/null || php -r 'echo gmdate("Y-m-d\\TH:i:s\\Z", time() + 300);')"
POST_URL="${ALERTMANAGER_URL%/}/api/v2/alerts"
RESPONSE_FILE="${ALERTMANAGER_ARTIFACT_DIR}/alertmanager-smoke-response-$(date -u +%Y%m%dT%H%M%SZ).json"
AUTH_ARGS=()

if [[ -n "${ALERTMANAGER_BEARER_TOKEN:-}" ]]; then
    AUTH_ARGS+=(--header "Authorization: Bearer ${ALERTMANAGER_BEARER_TOKEN}")
fi

PAYLOAD="$(cat <<JSON
[
  {
    "labels": {
      "alertname": $(json_escape "$ALERTMANAGER_SMOKE_ALERTNAME"),
      "service": "bbb-b2b",
      "severity": $(json_escape "$ALERTMANAGER_SMOKE_SEVERITY"),
      "route": $(json_escape "$ALERTMANAGER_SMOKE_ROUTE"),
      "release_id": $(json_escape "$ALERTMANAGER_SMOKE_RELEASE_ID"),
      "source": "deploy/scripts/alertmanager-smoke.sh"
    },
    "annotations": {
      "summary": "BBB B2B synthetic Alertmanager delivery check",
      "description": "Synthetic release-evidence alert. Confirm downstream receiver delivery outside this artifact."
    },
    "startsAt": $(json_escape "$STARTS_AT"),
    "endsAt": $(json_escape "$ENDS_AT")
  }
]
JSON
)"

log "Starting Alertmanager smoke for ${ALERTMANAGER_URL%/}"
log "alertname=${ALERTMANAGER_SMOKE_ALERTNAME} route=${ALERTMANAGER_SMOKE_ROUTE} severity=${ALERTMANAGER_SMOKE_SEVERITY} release_id=${ALERTMANAGER_SMOKE_RELEASE_ID}"

"$CURL_BIN" --fail --silent --show-error --max-time "$ALERTMANAGER_SMOKE_TIMEOUT_SECONDS" \
    --request POST \
    --header 'Content-Type: application/json' \
    --header 'Accept: application/json' \
    "${AUTH_ARGS[@]}" \
    --data "$PAYLOAD" \
    "$POST_URL" > "$RESPONSE_FILE"

log "PASS alert_posted response=${RESPONSE_FILE}"
log "Confirm downstream delivery to the configured b2b-ops/b2b-pager receiver outside this artifact, then attach the redacted receiver reference to release evidence."
log "Alertmanager smoke completed. Artifact: ${ALERTMANAGER_ARTIFACT}"
