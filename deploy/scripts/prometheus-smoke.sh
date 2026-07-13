#!/usr/bin/env bash
set -euo pipefail

APP_URL="${APP_URL:-https://b2b.example.com}"
METRICS_URL="${METRICS_URL:-${APP_URL%/}/api/b2b/v1/metrics}"
METRICS_FILE="${METRICS_FILE:-}"
CURL_BIN="${CURL_BIN:-curl}"
PROMTOOL_BIN="${PROMTOOL_BIN:-promtool}"
PROMETHEUS_RULES_FILE="${PROMETHEUS_RULES_FILE:-deploy/prometheus/b2b-alerts.yml}"
PROMETHEUS_ARTIFACT_DIR="${PROMETHEUS_ARTIFACT_DIR:-storage/logs}"
PROMETHEUS_ARTIFACT="${PROMETHEUS_ARTIFACT:-${PROMETHEUS_ARTIFACT_DIR}/prometheus-scrape-and-rule-test.log}"
PROMETHEUS_TIMEOUT_SECONDS="${PROMETHEUS_TIMEOUT_SECONDS:-10}"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"

mkdir -p "$PROMETHEUS_ARTIFACT_DIR" "$(dirname "$PROMETHEUS_ARTIFACT")"
: > "$PROMETHEUS_ARTIFACT"

log() {
    printf '%s %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$*" | tee -a "$PROMETHEUS_ARTIFACT"
}

fail() {
    log "FAIL $*"
    exit 1
}

assert_contains() {
    local file="$1"
    local needle="$2"
    local label="$3"

    if ! grep -Fq "$needle" "$file"; then
        fail "$label missing expected marker: $needle"
    fi
}

assert_not_contains() {
    local file="$1"
    local needle="$2"
    local label="$3"

    if grep -Fqi "$needle" "$file"; then
        fail "$label contains forbidden marker: $needle"
    fi
}

METRICS_SNAPSHOT="${PROMETHEUS_ARTIFACT_DIR}/prometheus-scrape-${STAMP}.txt"

log "Starting Prometheus scrape/rule smoke"
log "rules_file=${PROMETHEUS_RULES_FILE}"

if [[ -n "$METRICS_FILE" ]]; then
    if [[ ! -r "$METRICS_FILE" ]]; then
        fail "METRICS_FILE is not readable: $METRICS_FILE"
    fi
    cp "$METRICS_FILE" "$METRICS_SNAPSHOT"
    log "PASS metrics_snapshot_source file=${METRICS_FILE}"
else
    "$CURL_BIN" --fail --silent --show-error --max-time "$PROMETHEUS_TIMEOUT_SECONDS" \
        --header 'Accept: text/plain' \
        "$METRICS_URL" > "$METRICS_SNAPSHOT"
    log "PASS metrics_scrape url=${METRICS_URL} snapshot=${METRICS_SNAPSHOT}"
fi

for marker in \
    'bbb_b2b_info' \
    'bbb_b2b_metrics_collection_errors' \
    'bbb_b2b_provider_health_up' \
    'bbb_b2b_scheduler_heartbeat_fresh' \
    'bbb_b2b_queue_failed_jobs_total' \
    'bbb_b2b_wallet_transactions_status_total' \
    'bbb_b2b_reconciliation_items_open_total'
do
    assert_contains "$METRICS_SNAPSHOT" "$marker" metrics
    log "PASS metrics_marker ${marker}"
done

for forbidden in \
    'api_key' \
    'authorization' \
    'bearer ' \
    'password' \
    'secret' \
    'token=' \
    'operator_uid'
do
    assert_not_contains "$METRICS_SNAPSHOT" "$forbidden" metrics
done
log "PASS metrics_secret_scan"

if [[ ! -r "$PROMETHEUS_RULES_FILE" ]]; then
    fail "PROMETHEUS_RULES_FILE is not readable: $PROMETHEUS_RULES_FILE"
fi

if command -v "$PROMTOOL_BIN" >/dev/null 2>&1 || [[ -x "$PROMTOOL_BIN" ]]; then
    "$PROMTOOL_BIN" check rules "$PROMETHEUS_RULES_FILE" >> "$PROMETHEUS_ARTIFACT"
    log "PASS promtool_check_rules"
else
    log "WARN promtool_missing; running static rule marker checks"
fi

for marker in \
    'groups:' \
    'alert: BBBB2BMetricsCollectionErrors' \
    'alert: BBBB2BProviderHealthDown' \
    'alert: BBBB2BQueueFailedJobs' \
    'alert: BBBB2BSchedulerHeartbeatStale' \
    'service: bbb-b2b' \
    'route: b2b-pager' \
    'route: b2b-ops' \
    'bbb_b2b_provider_health_up' \
    'bbb_b2b_scheduler_heartbeat_fresh' \
    'bbb_b2b_queue_failed_jobs_total'
do
    assert_contains "$PROMETHEUS_RULES_FILE" "$marker" rules
    log "PASS rules_marker ${marker}"
done

log "Prometheus scrape/rule smoke completed. Artifact: ${PROMETHEUS_ARTIFACT}"
