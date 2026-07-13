#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/bbb/current}"
PHP_BIN="${PHP_BIN:-/usr/bin/php}"
SUPERVISORCTL_BIN="${SUPERVISORCTL_BIN:-supervisorctl}"
QUEUE_RUNTIME_ARTIFACT_DIR="${QUEUE_RUNTIME_ARTIFACT_DIR:-${APP_DIR}/storage/logs}"
QUEUE_RUNTIME_ARTIFACT="${QUEUE_RUNTIME_ARTIFACT:-${QUEUE_RUNTIME_ARTIFACT_DIR}/b2b-queue-runtime-drill.log}"
QUEUE_RUNTIME_JSON="${QUEUE_RUNTIME_JSON:-${QUEUE_RUNTIME_ARTIFACT_DIR}/b2b-queue-runtime-evidence.json}"
QUEUE_RUNTIME_ALLOW_MISSING_SUPERVISOR="${QUEUE_RUNTIME_ALLOW_MISSING_SUPERVISOR:-false}"
QUEUE_RUNTIME_MAX_FAILED="${QUEUE_RUNTIME_MAX_FAILED:-0}"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"

mkdir -p "$QUEUE_RUNTIME_ARTIFACT_DIR" "$(dirname "$QUEUE_RUNTIME_ARTIFACT")" "$(dirname "$QUEUE_RUNTIME_JSON")"
: > "$QUEUE_RUNTIME_ARTIFACT"

log() {
    printf '%s %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$*" | tee -a "$QUEUE_RUNTIME_ARTIFACT"
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

require_command "$PHP_BIN"

cd "$APP_DIR"

log "Starting B2B queue runtime drill"
log "app_dir=${APP_DIR}"
log "artifact=${QUEUE_RUNTIME_ARTIFACT}"
log "json_artifact=${QUEUE_RUNTIME_JSON}"
log "max_failed=${QUEUE_RUNTIME_MAX_FAILED}"

SUPERVISOR_STATUS_FILE=""
if command -v "$SUPERVISORCTL_BIN" >/dev/null 2>&1 || [[ -x "$SUPERVISORCTL_BIN" ]]; then
    SUPERVISOR_STATUS_FILE="${QUEUE_RUNTIME_ARTIFACT_DIR}/b2b-supervisor-status-${STAMP}.txt"
    "$SUPERVISORCTL_BIN" status 'bbb-b2b-*' > "$SUPERVISOR_STATUS_FILE"
    log "PASS supervisor_status status_file=${SUPERVISOR_STATUS_FILE}"
elif [[ "$QUEUE_RUNTIME_ALLOW_MISSING_SUPERVISOR" == "true" ]]; then
    log "WARN supervisorctl_missing allow_missing=true"
else
    fail "supervisorctl is missing. Set SUPERVISORCTL_BIN or QUEUE_RUNTIME_ALLOW_MISSING_SUPERVISOR=true only for non-production dry runs."
fi

"$PHP_BIN" artisan b2b:scheduler-heartbeat --source=queue-runtime-drill --no-interaction >> "$QUEUE_RUNTIME_ARTIFACT"
log "PASS scheduler_heartbeat"

ARTISAN_ARGS=(
    artisan
    b2b:queue-runtime-evidence
    --production
    --max-failed="$QUEUE_RUNTIME_MAX_FAILED"
    --artifact="$QUEUE_RUNTIME_JSON"
    --no-interaction
)

if [[ -n "$SUPERVISOR_STATUS_FILE" ]]; then
    ARTISAN_ARGS+=(--supervisor-status-file="$SUPERVISOR_STATUS_FILE")
else
    ARTISAN_ARGS+=(--allow-missing-supervisor)
fi

"$PHP_BIN" "${ARTISAN_ARGS[@]}" >> "$QUEUE_RUNTIME_ARTIFACT"

grep -q '"status": "passed"' "$QUEUE_RUNTIME_JSON"
log "PASS queue_runtime_evidence"
log "status=passed"
log "B2B queue runtime drill completed. Artifact: ${QUEUE_RUNTIME_ARTIFACT}"
