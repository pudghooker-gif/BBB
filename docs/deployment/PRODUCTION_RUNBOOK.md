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

Required production environment values:

```env
APP_ENV=production
APP_DEBUG=false
CACHE_DRIVER=redis
QUEUE_DRIVER=redis
B2B_NONCE_CACHE_STORE=redis
B2B_RATE_LIMIT_CACHE_STORE=redis
B2B_SANDBOX_ENABLED=false
B2B_ALLOW_PRIVATE_WALLET_CALLBACKS=false
```

## Deployment

1. Build a new release under `/var/www/bbb/releases/<release-id>`.
2. Install Composer dependencies with `--no-dev`.
3. Copy a production `.env` from the deployment secret store, not from the repository.
4. Run `php artisan migrate --force` after taking a database backup.
5. Run `php artisan b2b:release-check --production`.
6. Switch `/var/www/bbb/current` to the new release with an atomic symlink update.
7. Reload PHP-FPM, restart B2B workers, and restart the WebSocket service.
8. Run `WEBSOCKET_TCP_HOST=127.0.0.1 WEBSOCKET_TCP_PORT=12097 bash deploy/scripts/healthcheck.sh`.

## Health Checks

Nginx exposes `/healthz`, which rewrites to `/api/b2b/v1/health`, `/readyz`, which rewrites to `/api/b2b/v1/readiness`, and `/metrics`, which rewrites to `/api/b2b/v1/metrics` for Prometheus-compatible aggregate scraping.

The read-only B2B operations dashboard is available to backend admins at `/backend/b2b`. Mutating B2B backend routes must be protected with `b2b.web_step_up:{action}` and use `/backend/b2b/step-up/{action}` for session-bound confirmation.

```bash
APP_URL=https://b2b.example.com bash deploy/scripts/healthcheck.sh
```

The health check validates the public B2B readiness endpoint, metrics scrape, optional WebSocket TCP reachability, and the production release gate. Readiness checks database connectivity, critical B2B tables, cache runtime, queue configuration, storage writability, and production-safe configuration. It does not validate real provider credentials or gambling certification.

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

## Rollback

Rollback switches the `current` symlink to a known previous release and clears Laravel caches:

```bash
bash deploy/scripts/rollback.sh /var/www/bbb/releases/<previous-release-id>
```

Before rollback, confirm whether the new release ran irreversible migrations. If a database rollback is needed, restore from a tested backup or run a reviewed corrective migration. Do not silently edit wallet ledger rows.

## External Launch Blockers

Production readiness still requires real provider credentials and documentation, production domains and TLS, WebSocket proxy validation through the final public domain, legal/certification approval, verified backup storage, load testing, and a staging migration rehearsal against a production copy.
