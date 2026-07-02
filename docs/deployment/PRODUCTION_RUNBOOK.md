# BBB B2B Production Runbook

This runbook documents the production deployment shape. It intentionally uses placeholder paths and domains and must not contain real secrets.

## Required Host Services

- Nginx terminates TLS and serves `/var/www/bbb/current/public`.
- PHP-FPM runs the Laravel app through `deploy/php-fpm/bbb-b2b.pool.conf.example`.
- Redis is the shared cache, nonce, rate-limit, lock, and queue backend.
- Supervisor runs the B2B queue workers from `deploy/supervisor/b2b-workers.conf.example`.
- Systemd timer `bbb-scheduler.timer` runs Laravel scheduler every minute.
- Systemd service `bbb-websocket.service` runs the Node/WebSocket runtime from `PTWebSocket/package.json`.
- Nginx terminates public WebSocket TLS on port `12096` and proxies to the Node process on `127.0.0.1:12097`.
- Backups run `deploy/scripts/backup.sh` with credentials stored outside the repository in `/etc/bbb/mysql-backup.cnf`.

## Preflight

GitHub Actions workflow `B2B Release Verification` runs Composer validation, dependency install, PHP syntax lint, Laravel route boot/cache, PHPUnit, dependency audit, and the B2B production release-check. The dependency audit and production release-check jobs are currently allowed to report known blockers without hiding them; production launch still requires those blockers to be closed.

`php artisan b2b:release-check --production` also runs a locked Composer dependency audit and verifies the local Laravel advisory mitigations used by this PHP 7.4/Laravel 8 branch. In production mode it still fails when `composer.lock` has known advisories or abandoned packages, so dependency security must be green before a release can be promoted.

```bash
cd /var/www/bbb/current
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan key:generate --show
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan b2b:release-check --production
```

Install and syntax-check the WebSocket runtime before switching traffic:

```bash
cd /var/www/bbb/current/PTWebSocket
corepack enable
corepack prepare pnpm@11.7.0 --activate
pnpm install --frozen-lockfile --ignore-scripts
pnpm run check:syntax
cp ../deploy/websocket/socket_config2.production.example.json ../public/socket_config2.json
```

The production WebSocket config should keep `ssl=false` and bind Node to localhost through `listen_host=127.0.0.1` and `listen_port=12097`; Nginx owns the public TLS endpoint on `12096`.
Keep `allowed_origins` pinned to the final HTTPS application origin, keep `require_session_cookie=true`, and keep `log_json=true` so the Node runtime rejects cross-origin upgrades, requires the legacy Laravel session handshake payload, and avoids logging cookies/raw game payloads. `BBB_WEBSOCKET_AUTH_TOKENS` may be supplied from the host secret store if a deployment adds a token to the WebSocket URL or header, but do not place tokens in `socket_config2.json`.

Load the monitoring artifacts into the production Prometheus/Alertmanager stack before switching traffic:

```bash
cp deploy/prometheus/b2b-alerts.yml /etc/prometheus/rules/bbb-b2b.yml
cp deploy/prometheus/alertmanager-routes.example.yml /etc/alertmanager/bbb-b2b-routes.example.yml
```

Replace the placeholder Alertmanager webhook URLs with secret-store-managed incident endpoints and verify one non-production test alert reaches both the `b2b-ops` and critical `b2b-pager` routes.

Set `TRUSTED_PROXIES` to the exact Nginx or load-balancer IP/CIDR list that is allowed to supply forwarded request headers. Use `*` only when the direct upstream proxy address is dynamic and cannot be pinned.

## Staging Migration Rehearsal

Before production deployment, restore the latest production backup into staging and run the migration rehearsal script against that staging database copy:

```bash
CONFIRM_STAGING_MIGRATION=STAGING_MIGRATION_REHEARSAL \
APP_DIR=/var/www/bbb/current \
PHP_BIN=/usr/bin/php \
bash deploy/scripts/migration-rehearsal.sh
```

The script refuses to run when Laravel reports `APP_ENV=production`, clears caches, records migration status before/after, captures `migrate --pretend --force` SQL preview, applies `migrate --force`, verifies route/config cache boot, clears caches again, and writes an artifact log named `storage/logs/b2b-migration-rehearsal-<timestamp>.log`. Archive that log with the release evidence before promoting the build. Review the SQL preview for the B2B production index migration because it adds operator-scoped lookup/reporting indexes and the wallet `transaction_id` column required by status lookup and rollback recovery.

Required production environment values:

```env
APP_ENV=production
APP_DEBUG=false
TRUSTED_PROXIES=10.0.0.10,10.0.0.11
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
LOGIN_THROTTLE_PRODUCTION_ENFORCED=true
LOGIN_THROTTLE_MAX_ATTEMPTS=10
LOGIN_THROTTLE_LOCKOUT_MINUTES=1
PASSWORD_POLICY_MIN_LENGTH=12
PASSWORD_POLICY_MAX_LENGTH=72
PASSWORD_POLICY_REQUIRE_MIXED_CASE=true
PASSWORD_POLICY_REQUIRE_NUMBERS=true
PASSWORD_POLICY_REQUIRE_SYMBOLS=false
PASSWORD_POLICY_DISALLOW_WHITESPACE=true
PASSWORD_POLICY_TEMPORARY_LENGTH=16
CACHE_DRIVER=redis
QUEUE_DRIVER=redis
QUEUE_FAILED_DRIVER=database-uuids
B2B_NONCE_CACHE_STORE=redis
B2B_RATE_LIMIT_CACHE_STORE=redis
B2B_SCHEDULER_HEARTBEAT_CACHE_STORE=redis
B2B_SCHEDULER_HEARTBEAT_MAX_AGE_SECONDS=180
B2B_SANDBOX_ENABLED=false
B2B_ALLOW_PRIVATE_WALLET_CALLBACKS=false
B2B_STRUCTURED_LOGGING_ENABLED=true
B2B_STRUCTURED_LOG_CHANNEL=b2b
B2B_LOG_LEVEL=info
```

## Deployment

1. Build a new release under `/var/www/bbb/releases/<release-id>`.
2. Install Composer dependencies with `--no-dev`.
3. Copy a production `.env` from the deployment secret store, not from the repository.
4. Run `php artisan migrate --force` after taking a database backup.
5. Confirm the staging migration rehearsal artifact exists for the same release.
6. Run `php artisan b2b:release-check --production`.
7. Switch `/var/www/bbb/current` to the new release with an atomic symlink update.
8. Reload PHP-FPM, restart B2B workers, and restart the WebSocket service.
9. Enable or restart `bbb-scheduler.timer`, then confirm `php artisan b2b:scheduler-heartbeat --source=deploy-check` records successfully.
10. Run `WEBSOCKET_TCP_HOST=127.0.0.1 WEBSOCKET_TCP_PORT=12097 WEBSOCKET_HEALTH_URL=http://127.0.0.1:12097/healthz bash deploy/scripts/healthcheck.sh`.
11. Run the B2B smoke script with staging or production-canary credentials from the secret store, then archive the generated `b2b-smoke-*.log` artifact.

## Health Checks

Nginx exposes `/healthz`, which rewrites to `/api/b2b/v1/health`, `/readyz`, which rewrites to `/api/b2b/v1/readiness`, and `/metrics`, which rewrites to `/api/b2b/v1/metrics` for Prometheus-compatible aggregate scraping.

The read-only B2B operations dashboard is available to backend admins at `/backend/b2b` and requires the `b2b.reports.view` permission through B2B web RBAC middleware. The B2B audit trail is available at `/backend/b2b/audit` under `b2b.audit.view`. Mutating B2B backend routes must be protected with `b2b.admin:{permission}` plus `b2b.web_step_up:{action}` and use `/backend/b2b/step-up/{action}` for session-bound confirmation.

The signed operator portal is available at `/api/b2b/v1/portal`, with the same HMAC headers as other operator API calls. Its JSON source remains available at `/api/b2b/v1/portal/overview`, workflow pages are available under `/api/b2b/v1/portal/*` for credentials, games, sessions, transactions, settlements, cases, callbacks, reports, support, and docs, and signed support case comments can be posted to `/api/b2b/v1/portal/support/cases/{transaction_uid}/comments`.

B2B structured logs write JSON records to the configured `B2B_STRUCTURED_LOG_CHANNEL` (`b2b` by default, `storage/logs/b2b.log` for the template). Ship this log alongside Laravel application logs and preserve the `request_id`, `operator_uid`, `key_id`, `event`, and `status_code` fields for incident triage. Outbound wallet callbacks receive the same `request_id` as `X-Request-Id` plus `X-B2B-Transaction-Uid`, so operator-side logs can be joined to BBB wallet attempts.

Prometheus alert rules live at `deploy/prometheus/b2b-alerts.yml`; Alertmanager routing example lives at `deploy/prometheus/alertmanager-routes.example.yml`. Production release checks verify both files are present, but staging must still confirm final scrape labels and notification delivery.

```bash
APP_URL=https://b2b.example.com bash deploy/scripts/healthcheck.sh
```

The health check validates the public B2B readiness endpoint, metrics scrape, optional WebSocket TCP reachability, optional WebSocket `/healthz` JSON response, and the production release gate. Readiness checks database connectivity, critical B2B tables and columns, cache runtime, queue configuration, failed-job storage, fresh scheduler heartbeat, storage writability, and production-safe configuration. It does not validate real provider credentials or gambling certification.

## Queue Failure Handling

Production workers must write failed jobs into the database-backed `failed_jobs` table. Confirm `QUEUE_FAILED_DRIVER=database-uuids`, run migrations before workers start, and keep the Supervisor worker `--tries`, `--timeout`, and `--max-time` values aligned with `deploy/supervisor/b2b-workers.conf.example`.

Use these commands during incidents:

```bash
php artisan queue:failed
php artisan queue:retry <uuid-or-id>
php artisan queue:forget <uuid-or-id>
```

Do not retry mutation-related B2B jobs until the root cause is fixed and the affected wallet transaction, reconciliation item, and operator support/case context have been reviewed. The `BBBB2BQueueFailedJobs` alert fires from `bbb_b2b_queue_failed_jobs_total` when any configured B2B queue has failed jobs.

## Smoke And Load Verification

Run the production smoke script after deployment and before broad traffic. Public health/readiness/metrics checks always run; signed read-only operator checks run only when the operator credentials are provided from the environment:

```bash
APP_URL=https://b2b.example.com \
B2B_SMOKE_OPERATOR_ID=op_canary \
B2B_SMOKE_API_KEY=key_canary \
B2B_SMOKE_API_SECRET="$B2B_CANARY_SECRET" \
bash deploy/scripts/b2b-smoke.sh
```

The script does not print the API secret. It writes request evidence under `storage/logs/b2b-smoke-<timestamp>.log` plus response snapshots in the same artifact directory. Archive those artifacts with the release evidence.

Run the k6 smoke-load scenario on staging or a production canary window after the release gate and smoke script pass:

```bash
BASE_URL=https://b2b-staging.example.com \
B2B_OPERATOR_ID=op_canary \
B2B_API_KEY=key_canary \
B2B_API_SECRET="$B2B_CANARY_SECRET" \
k6 run deploy/k6/b2b-smoke-load.js
```

The default scenario checks public readiness/metrics and signed read-only operator/portal requests with conservative thresholds. Increase `K6_PUBLIC_VUS`, `K6_SIGNED_VUS`, and duration variables only in a dedicated load-test environment with agreed traffic limits.

## Backup

Database backup credentials must live in a root-owned MySQL defaults file outside the repository:

```ini
[client]
user=backup_user
password=replace-in-secret-store
host=127.0.0.1
```

Run:

```bash
DB_NAME=bbb BACKUP_DIR=/var/backups/bbb MYSQL_CNF=/etc/bbb/mysql-backup.cnf bash deploy/scripts/backup.sh
```

Backups must be copied to verified off-host storage. Do not store `.env`, local TLS keys, SQL dumps, or private provider documents in the release artifact.

## Restore

Restore is destructive and must be rehearsed on staging before production use. Confirm the selected backup timestamp, pause traffic, and keep the operator/provider incident notes outside the repository.

```bash
CONFIRM_RESTORE=RESTORE_BBB \
DB_NAME=bbb \
MYSQL_CNF=/etc/bbb/mysql-backup.cnf \
bash deploy/scripts/restore.sh \
    /var/backups/bbb/database/bbb-<timestamp>.sql.gz \
    /var/backups/bbb/storage/bbb-storage-<timestamp>.tar.gz
```

The restore script puts Laravel into maintenance mode, restores the gzip-compressed SQL dump with the external MySQL defaults file, optionally restores `storage/app` and `public`, clears Laravel caches, runs the production release gate, and brings the app back up through a trap. If only code rollback is required, use rollback instead of restoring the database. Never silently edit wallet ledger rows to simulate a restore.

## Rollback

Rollback switches the `current` symlink to a known previous release and clears Laravel caches:

```bash
bash deploy/scripts/rollback.sh /var/www/bbb/releases/<previous-release-id>
```

Before rollback, confirm whether the new release ran irreversible migrations. If a database rollback is needed, restore from a tested backup or run a reviewed corrective migration. Do not silently edit wallet ledger rows.

## External Launch Blockers

Production readiness still requires real provider credentials and documentation, production domains and TLS, WebSocket proxy validation through the final public domain, legal/certification approval, verified backup storage, executed smoke/load evidence from the target environment, and a completed staging migration rehearsal artifact from a production-copy database.
