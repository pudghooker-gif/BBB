# B2B Queue Topology

Date: 2026-06-24

Production B2B deployments must use Redis for queue transport and shared B2B state:

```env
QUEUE_DRIVER=redis
B2B_QUEUE_CONNECTION=redis
B2B_NONCE_CACHE_STORE=redis
B2B_RATE_LIMIT_CACHE_STORE=redis
```

`config/b2b_queues.php` is the source of truth for queue names and worker defaults.

## Queues

| Key | Default queue | Purpose |
| --- | --- | --- |
| `wallet_live` | `b2b-wallet-live` | Latency-sensitive wallet traffic when wallet callbacks are moved off request threads. |
| `wallet_retry` | `b2b-wallet-retry` | Retrying failed or unknown wallet transactions. |
| `provider_callbacks` | `b2b-provider-callbacks` | Provider-originated callbacks and session events. |
| `reporting` | `b2b-reporting` | Heavy report generation and exports. |
| `settlement` | `b2b-settlement` | Settlement generation, approval, and export work. |
| `reconciliation` | `b2b-reconciliation` | Wallet reconciliation scans and item updates. |
| `notifications` | `b2b-notifications` | Operator/admin notifications. |
| `maintenance` | `b2b-maintenance` | Stale session closing and housekeeping. |

## Workers

Start from `deploy/supervisor/b2b-workers.conf.example` and adjust:

- project path;
- Unix user;
- `numprocs` per traffic profile;
- log paths;
- memory and process restart policy if managed outside Supervisor.

Live wallet workers use short timeout and single try by default. Retry, reporting, settlement, reconciliation, notification, and maintenance workers are separated so slow jobs cannot starve live wallet traffic.

## Scheduled Commands

The current codebase exposes retry/reconciliation/session cleanup as artisan commands:

```text
b2b:retry-wallet --limit=50
b2b:reconcile-wallet --limit=100 --pending-minutes=5
b2b:close-stale-sessions --minutes=30
```

Run these from the Laravel scheduler or an external cron on one scheduler node only. The queue topology already reserves `wallet_retry`, `reconciliation`, and `maintenance` queues for the future job-backed versions of the same workflows.

## Release Checks

`php artisan b2b:release-check --production` must pass before launch. The gate intentionally fails when shared state or the queue driver is not Redis, when sandbox is enabled, or when local secret-bearing artifacts are still present in the release bundle.
