# BBB B2B Casino Aggregator

BBB is a Laravel 8 / PHP 7.4 casino aggregation platform built around the
existing Goldsvet/VanguardLTE runtime. The current production target is a B2B
API behind Nginx with Redis-backed shared state, Laravel queues, a Node
WebSocket runtime, an internal backoffice, and a signed operator portal.

This repository is not a generic Laravel starter. Treat it as an operational
B2B system: secrets must stay outside the repo, wallet mutations must be
audited and idempotent, and production readiness is proven by repeatable
checks rather than by code presence alone.

## Main Surfaces

- B2B API base: `/api/b2b/v1`
- Health/readiness/metrics: `/api/b2b/v1/health`, `/api/b2b/v1/readiness`, `/api/b2b/v1/metrics`
- Signed operator portal: `/api/b2b/v1/portal`
- Backend B2B dashboard: `/backend/b2b`
- Backend audit trail: `/backend/b2b/audit`
- Public launcher bridge: `/b2b/launcher/{game}/{token}`
- Node/WebSocket runtime: `PTWebSocket/`, proxied by Nginx in production

## Documentation Map

- API overview: `docs/b2b/API.md`
- HMAC signing: `docs/api/HMAC_AUTHENTICATION.md`
- Backoffice controls: `docs/b2b/BACKOFFICE.md`
- Queue topology: `docs/b2b/QUEUE_TOPOLOGY.md`
- Wallet and reconciliation: `docs/b2b/WALLET_V7.md`
- Observability: `docs/b2b/OBSERVABILITY.md`
- Release checks: `docs/b2b/RELEASE_CHECKS.md`
- Production runbook: `docs/deployment/PRODUCTION_RUNBOOK.md`
- Current audit and blockers: `docs/audit/`
- OpenAPI/Postman artifacts: `docs/b2b/openapi.json`, `docs/b2b/postman_collection.json`

## Local Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan route:list
php vendor/phpunit/phpunit/phpunit
```

For local development, use non-production cache/session drivers as needed. For
production-like checks, set the required Redis, queue, session, password-policy,
logging, and B2B environment values documented in `docs/b2b/RELEASE_CHECKS.md`.

## Verification

Run the core verification set before handing off changes:

```bash
composer validate --strict
composer dump-autoload --no-interaction
php vendor/phpunit/phpunit/phpunit
php artisan list
php artisan route:list
php artisan b2b:release-check --production
composer audit --locked --no-dev --format=json --abandoned=report
```

`b2b:release-check --production` intentionally fails while Composer reports
known production dependency blockers. As of the latest local verification, the
remaining automated blocker is the Laravel 8 dependency audit: three
`laravel/framework` advisories plus abandoned `swiftmailer/swiftmailer`.
Laravel 8 requires SwiftMailer directly, so this needs a PHP/Laravel major
upgrade or a vendor-supported security-backport plan before launch.

## Production Shape

Production deployments are expected to use:

- Nginx TLS termination and Laravel `public/` as the web root;
- PHP-FPM for Laravel;
- Redis for cache, nonce replay protection, rate limiting, scheduler locks, and queues;
- database-backed Laravel sessions and failed jobs;
- Supervisor-managed B2B queue workers;
- one scheduler node running the Laravel scheduler every minute;
- Node/WebSocket bound to localhost and proxied through Nginx;
- Prometheus/Alertmanager artifacts from `deploy/prometheus/`;
- off-host backup storage and rehearsed restore/rollback procedures.

Deployment templates live under `deploy/`. Do not put real `.env` files,
provider credentials, TLS private keys, SQL dumps, or production smoke-test
secrets into this repository.

## Current Launch Blockers

Do not call the project production-ready until these are closed and evidenced:

- Composer audit is green, including Laravel framework advisories and SwiftMailer;
- staging migration rehearsal has been run on a restored production database copy;
- real provider credentials, provider documentation, certificates, and legal approvals are available;
- final production domains, TLS, trusted proxy values, Redis, queues, workers, scheduler, and WebSocket proxy are validated;
- backup/restore/rollback drills and smoke/load evidence are archived for the target environment.
