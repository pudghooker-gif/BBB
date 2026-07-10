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

GitHub Actions workflow `B2B Release Verification` runs Composer validation, dependency install, PHP syntax lint, Laravel route boot/cache, PHPUnit, Composer audit, WebSocket `pnpm audit --prod`, and the B2B production release-check. The dependency audit and production release-check jobs are hard release gates; production launch requires those blockers to be closed.

`php artisan b2b:release-check --production` also runs a locked Composer dependency audit, runs `pnpm audit --prod` for `PTWebSocket`, and verifies the Laravel security mitigation surface used by this PHP 8.3/Laravel 12 branch. In production mode it fails when `composer.lock` has known advisories/abandoned packages or the WebSocket lockfile has known vulnerabilities, so dependency security must stay green before a release can be promoted. Ensure Node.js and pnpm are available before running the release-check.

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
pnpm audit --prod
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
MIGRATION_REHEARSAL_ARTIFACT_DIR=/var/www/bbb/release-evidence/<release-id>/migration \
bash deploy/scripts/migration-rehearsal.sh
```

The script refuses to run when Laravel reports `APP_ENV=production`, clears caches, records migration status before/after, captures `migrate --pretend --force` SQL preview, applies `migrate --force`, verifies route/config cache boot, clears caches again, and writes an artifact log named `b2b-migration-rehearsal-<timestamp>.log` under `MIGRATION_REHEARSAL_ARTIFACT_DIR` (or the legacy `ARTIFACT_DIR` fallback). Archive that log with the release evidence before promoting the build. Review the SQL preview for the B2B production index migration because it adds operator-scoped lookup/reporting indexes and the wallet `transaction_id` column required by status lookup and rollback recovery.

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
B2B_WEB_STEP_UP_REQUIRES_PASSWORD=true
B2B_WEB_STEP_UP_TTL_SECONDS=300
B2B_API_KEY_DEFAULT_SCOPES=operator.read,portal.read,games.read,games.launch,sessions.read,sessions.close,wallet.balance,wallet.status,wallet.mutate,reports.read,support.write
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
5. Confirm the staging migration rehearsal artifact exists for the same release under `MIGRATION_REHEARSAL_ARTIFACT_DIR=/var/www/bbb/release-evidence/<release-id>/migration`.
6. Run `php artisan b2b:release-check --production`.
7. Switch `/var/www/bbb/current` to the new release with an atomic symlink update.
8. Reload PHP-FPM, restart B2B workers, and restart the WebSocket service.
9. Enable or restart `bbb-scheduler.timer`, then confirm `php artisan b2b:scheduler-heartbeat --source=deploy-check` records successfully.
10. Run `HEALTHCHECK_ARTIFACT_DIR=/var/www/bbb/release-evidence/<release-id>/preflight WEBSOCKET_TCP_HOST=127.0.0.1 WEBSOCKET_TCP_PORT=12097 WEBSOCKET_HEALTH_URL=http://127.0.0.1:12097/healthz bash deploy/scripts/healthcheck.sh`.
11. Run the B2B smoke script with staging or production-canary credentials from the secret store and `B2B_SMOKE_ARTIFACT_DIR=/var/www/bbb/release-evidence/<release-id>/smoke`, then archive the generated `b2b-smoke-*.log` artifact.
12. Assemble the redacted release evidence package from `php artisan b2b:evidence-template /var/www/bbb/release-evidence/<release-id> --release-id=<release-id>`, add the final clean `payload-redaction-final.json` artifact, run `php artisan b2b:evidence-hash /var/www/bbb/release-evidence/<release-id> --write`, then run `php artisan b2b:evidence-check /var/www/bbb/release-evidence/<release-id> --production` before broad traffic.

## Health Checks

Nginx exposes `/healthz`, which rewrites to `/api/b2b/v1/health`, `/readyz`, which rewrites to `/api/b2b/v1/readiness`, and `/metrics`, which rewrites to `/api/b2b/v1/metrics` for Prometheus-compatible aggregate scraping.

The read-only B2B operations dashboard is available to backend admins at `/backend/b2b` and requires the `b2b.reports.view` permission through B2B web RBAC middleware. The B2B audit trail is available at `/backend/b2b/audit` under `b2b.audit.view`. Mutating B2B backend routes must be protected with `b2b.admin:{permission}` plus `b2b.web_step_up:{action}` and use `/backend/b2b/step-up/{action}` for session-bound confirmation plus current-password verification.

The signed operator portal is available at `/api/b2b/v1/portal`, with the same HMAC headers as other operator API calls. Its JSON source remains available at `/api/b2b/v1/portal/overview`, workflow pages are available under `/api/b2b/v1/portal/*` for credentials, games, sessions, transactions, settlements, cases, callbacks, reports, support, and docs, and signed support case comments can be posted to `/api/b2b/v1/portal/support/cases/{transaction_uid}/comments`.

Operator API keys are scoped. The default production scope list must omit `reports.export`; settlement export at `/api/b2b/v1/reports/settlements/export` requires a deliberately issued key containing `reports.export`, plus the normal HMAC headers.

B2B structured logs write JSON records to the configured `B2B_STRUCTURED_LOG_CHANNEL` (`b2b` by default, `storage/logs/b2b.log` for the template). Ship this log alongside Laravel application logs and preserve the `request_id`, `operator_uid`, `key_id`, `event`, and `status_code` fields for incident triage. Outbound wallet callbacks receive the same `request_id` as `X-Request-Id` plus `X-B2B-Transaction-Uid`, so operator-side logs can be joined to BBB wallet attempts.

Before enabling broad raw-payload backoffice access, run the legacy payload redaction audit on staging and production. Store the final clean dry-run artifact in the release-evidence package:

```bash
PAYLOAD_REDACTION_ARTIFACT=/var/www/bbb/release-evidence/<release-id>/payload-redaction-final.json
php artisan b2b:payload-redaction-audit --artifact="$PAYLOAD_REDACTION_ARTIFACT"
```

If the dry-run reports findings, schedule an approved maintenance window and run `php artisan b2b:payload-redaction-audit --write --artifact=/var/www/bbb/release-evidence/<release-id>/payload-redaction-write.json`, then rerun the dry-run. The command prints and stores counts only; it does not print payload values. The final clean artifact is required as the `payload_redaction_audit` entry in `release-evidence.json`.

Prometheus alert rules live at `deploy/prometheus/b2b-alerts.yml`; Alertmanager routing example lives at `deploy/prometheus/alertmanager-routes.example.yml`. Production release checks verify both files are present, but staging must still confirm final scrape labels and notification delivery.

```bash
HEALTHCHECK_ARTIFACT_DIR=/var/www/bbb/release-evidence/<release-id>/preflight \
APP_URL=https://b2b.example.com \
bash deploy/scripts/healthcheck.sh
```

The health check validates the public B2B readiness endpoint, metrics scrape, optional WebSocket TCP reachability, optional WebSocket `/healthz` JSON response, and the production release gate. It writes a timestamped `b2b-healthcheck-*.log`, readiness/metrics snapshots, optional WebSocket health snapshot, and `b2b-release-check-*.log` under `HEALTHCHECK_ARTIFACT_DIR` for the release evidence package. Readiness checks database connectivity, critical B2B tables and columns, cache runtime, queue configuration, failed-job storage, fresh scheduler heartbeat, storage writability, and production-safe configuration. It does not validate real provider credentials or gambling certification.

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
B2B_SMOKE_ARTIFACT_DIR=/var/www/bbb/release-evidence/<release-id>/smoke \
bash deploy/scripts/b2b-smoke.sh
```

The script does not print the API secret. It writes request evidence under `B2B_SMOKE_ARTIFACT_DIR` as `b2b-smoke-<timestamp>.log` plus response snapshots in the same artifact directory. Archive those artifacts with the release evidence.

Run the k6 smoke-load scenario on staging or a production canary window after the release gate and smoke script pass:

```bash
BASE_URL=https://b2b-staging.example.com \
B2B_OPERATOR_ID=op_canary \
B2B_API_KEY=key_canary \
B2B_API_SECRET="$B2B_CANARY_SECRET" \
K6_SUMMARY_PATH=/var/www/bbb/release-evidence/<release-id>/load/k6-b2b-smoke-load-summary.json \
k6 run deploy/k6/b2b-smoke-load.js
```

The default scenario checks public readiness/metrics and signed read-only operator/portal requests with conservative thresholds, then writes a redacted JSON summary to `K6_SUMMARY_PATH`. Increase `K6_PUBLIC_VUS`, `K6_SIGNED_VUS`, and duration variables only in a dedicated load-test environment with agreed traffic limits.

## Release Evidence Package

Every production promotion must have a redacted evidence directory containing `release-evidence.json`. Generate the manifest from the current checked requirements or start from `deploy/evidence/release-evidence.example.json`, copy the logs or approval references into subdirectories under the evidence directory, and validate it with:

```bash
php artisan b2b:evidence-template /var/www/bbb/release-evidence/<release-id> --release-id=<release-id>
php artisan b2b:evidence-hash /var/www/bbb/release-evidence/<release-id> --write
php artisan b2b:evidence-check /var/www/bbb/release-evidence/<release-id> --production
```

The evidence template command writes placeholder artifact paths and zero SHA-256 values from the same required evidence list used by the checker; rerun it with `--force` only when you intentionally want to replace an existing manifest. The evidence checker requires non-empty artifacts for staging migration rehearsal, the production release gate, final clean payload redaction audit, healthcheck, smoke, smoke-load, public WebSocket proxy validation, backup, restore rehearsal, rollback rehearsal, Prometheus scrape/rule validation, Alertmanager notification delivery, B2B log shipping, final domains/TLS/proxy/Redis/queue/scheduler validation, provider credentials, provider certification, and legal approval. Approval items must include an `approved_by` owner. Every production artifact must have a SHA-256 hash; `b2b:evidence-hash --write` calculates and fills `sha256` for single `artifact` entries and `artifact_hashes` for entries with multiple `artifacts`. The checker scans the manifest and artifact files for common inline secret patterns. Keep real credentials, provider tokens, TLS keys, private certificates, `.env` values, and SQL dumps outside the evidence directory; store only redacted logs or references.

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
DB_NAME=bbb \
BACKUP_DIR=/var/backups/bbb \
BACKUP_ARTIFACT_DIR=/var/www/bbb/release-evidence/<release-id>/backup \
MYSQL_CNF=/etc/bbb/mysql-backup.cnf \
bash deploy/scripts/backup.sh
```

The backup script writes `b2b-backup-*.log` and `b2b-backup-*.sha256` under `BACKUP_ARTIFACT_DIR`. Backups must be copied to verified off-host storage. Do not store `.env`, local TLS keys, SQL dumps, or private provider documents in the release artifact; store only the redacted backup log, SHA-256 manifest, and off-host storage verification note.

## Restore

Restore is destructive and must be rehearsed on staging before production use. Confirm the selected backup timestamp, pause traffic, and keep the operator/provider incident notes outside the repository.

```bash
CONFIRM_RESTORE=RESTORE_BBB \
DB_NAME=bbb \
RESTORE_ARTIFACT_DIR=/var/www/bbb/release-evidence/<release-id>/restore \
MYSQL_CNF=/etc/bbb/mysql-backup.cnf \
bash deploy/scripts/restore.sh \
    /var/backups/bbb/database/bbb-<timestamp>.sql.gz \
    /var/backups/bbb/storage/bbb-storage-<timestamp>.tar.gz
```

The restore script puts Laravel into maintenance mode, records SHA-256 hashes for the selected input backups, restores the gzip-compressed SQL dump with the external MySQL defaults file, optionally restores `storage/app` and `public`, clears Laravel caches, runs the production release gate, and brings the app back up through a trap. It writes `b2b-restore-*.log` and `b2b-restore-release-check-*.log` under `RESTORE_ARTIFACT_DIR`. If only code rollback is required, use rollback instead of restoring the database. Never silently edit wallet ledger rows to simulate a restore.

## Rollback

Rollback switches the `current` symlink to a known previous release and clears Laravel caches:

```bash
ROLLBACK_ARTIFACT_DIR=/var/www/bbb/release-evidence/<release-id>/rollback \
bash deploy/scripts/rollback.sh /var/www/bbb/releases/<previous-release-id>
```

Before rollback, confirm whether the new release ran irreversible migrations. The rollback script writes `b2b-rollback-*.log` and `b2b-rollback-release-check-*.log` under `ROLLBACK_ARTIFACT_DIR`. If a database rollback is needed, restore from a tested backup or run a reviewed corrective migration. Do not silently edit wallet ledger rows.

## External Launch Blockers

Production readiness still requires real provider credentials and documentation, production domains and TLS, WebSocket proxy validation through the final public domain, provider certification, legal approval, verified backup storage, executed smoke/load evidence from the target environment, a completed staging migration rehearsal artifact from a production-copy database, and a redacted `release-evidence.json` package that passes `b2b:evidence-check --production`.
