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
- reconciliation item backlog by state/status;
- settlement counts by status;
- B2B queue depth and oldest queued job age when the configured queue backend exposes them;
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

The Node/WebSocket runtime writes JSON logs when `log_json=true` in `socket_config2.json`. These events include connection lifecycle, denied upgrades, handshake success/failure, PHP bridge request/response status, and idle heartbeat closures. They intentionally do not log session cookies, raw WebSocket frames, or PHP response bodies.

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
- wallet callback failures;
- wallet transactions requiring manual review, rollback, or dead-letter handling;
- open reconciliation backlog;
- B2B queue depth and stale queued jobs.
- stale Laravel scheduler heartbeat.

`deploy/prometheus/alertmanager-routes.example.yml` provides a secret-free Alertmanager routing example for `service="bbb-b2b"`, with critical alerts routed to `b2b-pager` and all B2B alerts routed to `b2b-ops`. Replace the placeholder webhook URLs with the production incident-management endpoints from the host secret store.

Production release checks verify that both monitoring artifacts are present. Staging still must validate scrape labels, Alertmanager routing, and notification delivery against the final Prometheus/Alertmanager topology.
