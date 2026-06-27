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
- scrape collection error count.

Production Nginx should expose `/metrics` only to the monitoring network or local scraper. The example virtual host keeps `/metrics` as a short alias to `/api/b2b/v1/metrics`; tighten `allow`/`deny` rules for the final host topology.
