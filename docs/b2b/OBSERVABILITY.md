# B2B Observability

The B2B runtime exposes public health and readiness endpoints plus a Prometheus-compatible aggregate metrics endpoint:

```text
GET /api/b2b/v1/health
GET /api/b2b/v1/readiness
GET /api/b2b/v1/metrics
```

`/metrics` intentionally emits aggregate platform metrics only. It does not include operator IDs, API keys, callback payloads, raw wallet payloads, player IDs, or monetary amount totals.

Current metric groups:

- service/environment info;
- operator counts by status and open circuit-breaker count;
- session counts by status and active-session count;
- wallet transaction counts by status and type;
- wallet callback success/failure counts and average callback latency;
- provider request counts and average provider latency where duration data exists;
- provider adapter health gauges by provider/status;
- reconciliation item backlog by state/status;
- settlement counts by status;
- B2B queue depth and oldest queued job age when the configured queue backend exposes them;
- B2B failed-job count and oldest failed-job age from Laravel `failed_jobs` storage;
- scheduler heartbeat age/freshness from the cache store used by Laravel schedule;
- scrape collection error count.

Production Nginx should expose `/metrics` only to the monitoring network or local scraper. The example virtual host keeps `/metrics` as a short alias to `/api/b2b/v1/metrics`; tighten `allow`/`deny` rules for the final host topology.

## Structured Logs

B2B structured logging is enabled by default through `B2B_STRUCTURED_LOGGING_ENABLED=true` and writes JSON-formatted records to the `b2b` channel (`storage/logs/b2b.log` by default). The channel is configured with `VanguardLTE\Logging\B2BJsonFormatter`, so log shippers can parse each record as JSON.

Current event families:

- `api.request` for successful signed operator API requests;
- `api.auth_failed` for missing headers, body-hash mismatch, invalid signatures, replay detection, expired credentials, and IP allowlist denials;
- `audit.event` for operator audit events, including credential lifecycle, manual wallet actions, settlements, case management, privileged-action denials, API-key usage sampling, operator support comments, and operator support ticket lifecycle events.

Every B2B structured log includes `component=b2b`, `event`, `level`, and, when available, `request_id`, HTTP method/path/status, operator ID/UID, API key ID, actor, subject, IP, user agent, and redacted metadata. The logger uses the same recursive payload redactor as wallet persistence and raw-payload review, and additionally redacts known token/secret text patterns before writing free-form strings.

Signed wallet API requests propagate the same `request_id` into outbound wallet callbacks through `X-Request-Id`. Wallet callbacks also receive `X-B2B-Transaction-Uid` after the internal transaction row is created, and callback attempt logs store correlation fields under `_context` next to the redacted payload for incident triage.

For release evidence after a canary wallet/provider flow, run `php artisan b2b:correlation-evidence --artifact=/var/www/bbb/release-evidence/<release-id>/observability/b2b-correlation-validation.json`. The command inspects recent wallet attempt/callback rows and provider diagnostics, verifies correlation fields are present, scans the sampled sources for common secret markers, and writes counts plus SHA-256 hashes of sample IDs instead of raw request IDs, transaction IDs, provider request IDs, or payloads.

The Node/WebSocket runtime writes JSON logs when `log_json=true` in `socket_config2.json`. These events include connection lifecycle, denied upgrades, handshake success/failure, PHP bridge request/response status, and idle heartbeat closures. They intentionally do not log session cookies, raw WebSocket frames, or PHP response bodies.

For release evidence, run `php artisan b2b:log-shipping-check --artifact=/var/www/bbb/release-evidence/<release-id>/observability/b2b-log-shipping-validation.log --marker=<release-marker>` on the target host after the log shipper is configured. The command writes a synthetic `observability.log_shipping_check` marker to the B2B JSON channel, verifies the marker can be read back as redacted JSON, and leaves a secret-free local artifact. Then run `deploy/scripts/log-shipping-external-check.sh` with the same `LOG_SHIPPING_MARKER` plus either `LOG_SHIPPING_EXPORT_FILE` pointing at a redacted external log export or `LOG_SHIPPING_QUERY_URL` pointing at the external log platform search endpoint. The script writes `b2b-log-shipping-external-delivery.log` without archiving raw external log lines.

Production release checks require structured logging to remain enabled and pointed at a JSON-formatted channel. Override only when the replacement channel preserves JSON records:

```env
B2B_STRUCTURED_LOGGING_ENABLED=true
B2B_STRUCTURED_LOG_CHANNEL=b2b
B2B_LOG_LEVEL=info
B2B_LOG_DAYS=14
```

## Alerting

Prometheus alert rules are shipped in `deploy/prometheus/b2b-alerts.yml`. The rules cover:

- metric collector failures;
- open operator circuit breakers;
- failed provider adapter health;
- wallet callback failures;
- wallet transactions requiring manual review, rollback, or dead-letter handling;
- open reconciliation backlog;
- B2B queue depth, stale queued jobs, and Laravel failed jobs;
- stale Laravel scheduler heartbeat.

`deploy/prometheus/alertmanager-routes.example.yml` provides a secret-free Alertmanager routing example for `service="bbb-b2b"`, with critical alerts routed to `b2b-pager` and all B2B alerts routed to `b2b-ops`. Replace the placeholder webhook URLs with the production incident-management endpoints from the host secret store.

`deploy/scripts/alertmanager-smoke.sh` posts a synthetic `BBBB2BSmokeNotification` alert to the target Alertmanager API and writes `alertmanager-delivery-test.log` for release evidence. Set `ALERTMANAGER_URL` to the final Alertmanager endpoint and set `ALERTMANAGER_BEARER_TOKEN` from the host secret store only when required. After the incident-management receiver shows the synthetic alert, run `deploy/scripts/alertmanager-receiver-check.sh` with either `ALERTMANAGER_RECEIVER_EXPORT_FILE` or `ALERTMANAGER_RECEIVER_QUERY_URL`; it verifies the downstream receiver/export contains the smoke alert and writes `alertmanager-receiver-delivery-confirmation.log` without archiving raw receiver data.

`deploy/scripts/prometheus-smoke.sh` fetches the target metrics endpoint, verifies the B2B metric families used by shipped alert rules, scans the scrape snapshot for common secret markers, and validates `deploy/prometheus/b2b-alerts.yml` with `promtool check rules` when `promtool` is available. Run it with `PROMETHEUS_ARTIFACT_DIR=/var/www/bbb/release-evidence/<release-id>/observability` so `prometheus-scrape-and-rule-test.log` lands directly in the release evidence package.

Production release checks verify that monitoring artifacts and smoke tooling are present. Staging still must validate scrape labels, Prometheus rule loading, Alertmanager routing, and notification delivery against the final Prometheus/Alertmanager topology.
