# B2B Queue Topology

Date: 2026-06-24

Production B2B deployments must use Redis for queue transport and shared B2B state:

```env
QUEUE_DRIVER=redis
B2B_QUEUE_CONNECTION=redis
B2B_NONCE_CACHE_STORE=redis
B2B_RATE_LIMIT_CACHE_STORE=redis
B2B_GAME_CATALOG_CACHE_ENABLED=true
B2B_GAME_CATALOG_CACHE_STORE=redis
B2B_SCHEDULER_HEARTBEAT_CACHE_STORE=redis
QUEUE_FAILED_DRIVER=database-uuids
```

`config/b2b_queues.php` is the source of truth for queue names and worker defaults.

## Queues

| Key | Default queue | Purpose |
| --- | --- | --- |
| `wallet_live` | `b2b-wallet-live` | Latency-sensitive wallet traffic when wallet callbacks are moved off request threads. |
| `wallet_retry` | `b2b-wallet-retry` | Retrying failed/unknown wallet transactions and rollback recovery callbacks. |
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

## Failed Jobs

Laravel failed-job storage is part of the production queue contract. Run `php artisan migrate --force` before starting workers so the `failed_jobs` table exists, and keep `QUEUE_FAILED_DRIVER=database-uuids` unless the replacement provider is covered by equivalent monitoring.

Operational flow:

```bash
php artisan queue:failed
php artisan queue:retry <uuid-or-id>
php artisan queue:forget <uuid-or-id>
```

Only retry a failed B2B job after the root cause is fixed and the affected operator/transaction state has been reviewed. Use `queue:failed` together with structured logs, `request_id`, wallet attempt rows, reconciliation items, and `/backend/b2b/cases` before retrying mutation-related jobs.

## Scheduled Commands And Jobs

The current codebase exposes retry/reconciliation/session cleanup as artisan commands. In production, run the dispatching form from one scheduler node so the scheduler only enqueues work and B2B workers execute it:

```text
b2b:scheduler-heartbeat --source=scheduler
b2b:retry-wallet --limit=50 --dispatch
b2b:recover-rollbacks --limit=50 --dispatch
b2b:reconcile-wallet --limit=100 --pending-minutes=5 --dispatch
b2b:close-stale-sessions --minutes=30 --dispatch
```

`b2b:scheduler-heartbeat` writes a Redis-backed heartbeat every minute so readiness and Prometheus can prove that the Laravel scheduler is actually running. The dispatching commands enqueue `RetryWalletTransactionsJob`, `RecoverWalletRollbacksJob`, `ReconcileWalletTransactionsJob`, and `CloseStaleB2BSessionsJob` onto `wallet_retry`, `reconciliation`, and `maintenance` queues. The same commands still support inline execution without `--dispatch` for local development and emergency single-node operations.

`app/Console/Kernel.php` reads `config/b2b_queues.scheduled_commands` and registers the dispatching commands with `withoutOverlapping()`. Keep only one scheduler node active, or use Laravel's standard scheduler locking on shared cache.

Production readiness requires a fresh heartbeat by default. Tune the allowed freshness window with `B2B_SCHEDULER_HEARTBEAT_MAX_AGE_SECONDS` only when the scheduler interval is intentionally changed.

## Runtime Evidence Drill

Run the target-host drill after Supervisor workers and the scheduler are active:

```bash
QUEUE_RUNTIME_ARTIFACT_DIR=/var/www/bbb/release-evidence/<release-id>/operations \
bash deploy/scripts/queue-runtime-drill.sh
```

The script captures `supervisorctl status 'bbb-b2b-*'`, records `b2b:scheduler-heartbeat`, and runs `php artisan b2b:queue-runtime-evidence --production`. The JSON artifact checks configured worker process counts, scheduler heartbeat/locking coverage, and B2B failed-job counts against `QUEUE_RUNTIME_MAX_FAILED` before launch. Store `b2b-queue-runtime-drill.log` and `b2b-queue-runtime-evidence.json` in the `queue_runtime_drill` release evidence entry.

## Release Checks

`php artisan b2b:release-check --production` must pass before launch. The gate intentionally fails when shared state or the queue driver is not Redis, when failed-job storage, migration, worker retry limits, or runbook coverage are missing, when sandbox is enabled, or when local secret-bearing artifacts are still present in the release bundle.
