# Repository Audit

Date: 2026-06-22

## Scope

Audited the Laravel 8 / PHP 7.4 repository structure, B2B route files, B2B controllers/services/models, migrations, Composer metadata, current tests, scheduler registration, and Node/WebSocket artifact locations. Secret-bearing files were identified by path only; their contents were not copied into this report.

## Verified Commands

- `php -v`: PHP 7.4.33.
- `php composer.phar validate --strict`: valid schema with warnings.
- `php composer.phar install --no-interaction --no-progress --prefer-dist`: succeeds outside the Codex filesystem sandbox.
- Scoped `php -l` over `app`, `routes`, `database`, `config`, and `tests`, excluding `app/Games`: passed.
- `php artisan list`: succeeds outside the filesystem sandbox and registers B2B commands.
- `php artisan route:list`: initially failed on the B2B launcher route namespace.
- `php vendor/phpunit/phpunit/phpunit --testdox`: initially failed because the default example feature test expected 200 but `/` redirects with 302; after the first hardening pass it passed.
- `php composer.phar audit`: unavailable in Composer 2.0.13.

## Current Structure

- Core Laravel app: `app/`, `routes/`, `config/`, `database/`, `resources/`, `public/`.
- B2B code: `app/B2B`, `app/Http/Controllers/Api/B2B`, `app/Http/Middleware/VerifyB2BSignature.php`, `routes/b2b*.php`, B2B migrations.
- Existing admin/B2C routes remain in `routes/web.php` and backend controllers.
- WebSocket/runtime files exist under `PTWebSocket/` and `sports/`.
- Existing B2B docs exist under `docs/b2b/`.

## Findings

### P0

- B2B launcher route was resolved through the web controller namespace and broke `route:list`.
- `routes/api.php` loaded `routes/b2b.php` multiple times.
- `routes/web.php` loaded `routes/b2b_web.php` multiple times.
- `routes/b2b_wallet_v7.php` used `api/b2b/v1` while already loaded under the API route prefix, creating an `/api/api/...` effective prefix.
- `routes/b2b.php` pointed `/games/launch` at `GameLaunchController@launch`, but the controller method is `store`.
- `b2b.signature` middleware alias was used but not registered in `app/Http/Kernel.php`.
- `2026_06_09_000060_add_reporting_indexes_to_b2b_tables.php` indexed `game_id` even though the B2B tables use `game_uid`.
- Session/report queries allowed an operatorless fallback path that could return cross-tenant data if called outside the intended middleware context.
- Wallet callback URLs had no SSRF guard.
- Return URLs on launch had no allowlist check.
- Runtime logging cannot be written from the default Codex sandbox; Laravel checks must be run outside that sandbox in this workspace.
- HMAC initially signed `timestamp.nonce.body` without a body hash header. The second hardening pass introduced canonical request signing and feature tests.

### P1

- Composer reports PSR-4 warnings for nested game classes under `app/Games/AmazingSevensGT/...`, `app/Http/Controllers/Web/Backend/ShopsController_original.php`, `tests/unit/ExampleTest.php`, and one vendor Monolog path.
- Composer security audit is blocked by the old Composer version.
- The test suite had only example tests before this work. HMAC success/replay/body-hash/IP allowlist coverage, tenant isolation coverage for sessions/reports/wallet attempts, request validation, and wallet idempotency conflict coverage have now been added; launch, wallet mutation state transitions, and broader reporting edge-case coverage remain incomplete.
- Financial report code used floats before this audit pass.
- Sandbox wallet code still uses floats and should stay non-production.
- The provider adapter contract is minimal and does not cover the full target provider lifecycle.
- Admin B2B backoffice and operator portal are not implemented as full production workflows.
- Queue isolation, reconciliation jobs, settlement workflow, OpenAPI, and CI are incomplete.

### P2

- Documentation exists under `docs/b2b`, but production deployment/runbook documentation is incomplete.
- Scheduler output shows many jobs but no dedicated B2B queue topology.
- Large frontend/static/vendor assets make broad scans noisy and should be excluded from release artifacts where possible.

## Notes

- Existing `.env`, `.env_old`, SQL dump, and WebSocket key/cert files are present and must not be removed automatically. They are covered in `SECURITY_RISKS.md`.
- The project must not be called production-ready until release gates are rerun on a clean Linux-like environment with database, Redis, queue workers, WebSocket proxy, SSL, production secrets, and real provider/legal artifacts.
