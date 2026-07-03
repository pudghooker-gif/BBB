# Repository Audit

Date: 2026-06-22

## Scope

Audited the Laravel 12 / PHP 8.3 repository structure, B2B route files, B2B controllers/services/models, migrations, Composer metadata, current tests, scheduler registration, and Node/WebSocket artifact locations. Secret-bearing files were identified by path only; their contents were not copied into this report.

## Verified Commands

- `php -v`: PHP 8.3.32.
- `composer validate --strict`: passes after adding proprietary license metadata; unused `laravel/ui`, legacy `fideloper/proxy`, unused SMS.to Laravel wrapper, dev-only debugbar, legacy `laracasts/presenter`, unused legacy factory, legacy settings package, legacy roles package, LaravelCollective Form/HTML package, transitive seedster/helpers packages, and unused server-side Yajra DataTables direct dependencies were removed during dependency-trim work.
- `php composer.phar install --no-interaction --no-progress --prefer-dist`: succeeds outside the Codex filesystem sandbox.
- Scoped `php -l` over `app`, `routes`, `database`, `config`, and `tests`, excluding `app/Games`: passed.
- `php artisan list`: succeeds outside the filesystem sandbox and registers B2B commands.
- `php artisan route:list`: initially failed on the B2B launcher route namespace.
- `php vendor/phpunit/phpunit/phpunit --testdox`: initially failed because the default example feature test expected 200 but `/` redirects with 302; after the first hardening pass it passed.
- `composer audit --locked --no-dev --format=plain --abandoned=fail`: passes with no advisories or abandoned packages after the PHP 8.3 / Laravel 12 upgrade.

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

- Composer optimized autoload no longer reports the previously identified PSR-4 warnings after explicitly excluding legacy non-autoloadable paths from the classmap.
- Composer security audit is available and currently green. The PHP 8.3 / Laravel 12 upgrade removed the remaining Laravel framework advisories and replaced the legacy SwiftMailer dependency with Symfony Mailer; debug tooling has been removed from Composer dependencies, legacy Faker has been replaced, and unused `laravel/ui`, legacy `fideloper/proxy`, the unused SMS.to Laravel wrapper, legacy `laracasts/presenter`, legacy factory support, the Laravel 4-era settings package, unused server-side Yajra DataTables, the JS validation wrapper, the Laravel-specific Google2FA wrapper, `tymon/jwt-auth`, `jeremykenedy/laravel-roles`, `laravelcollective/html`, transitive `eklundkristoffer/seedster`, and transitive `laravel/helpers` have been removed.
- Trusted proxy handling now uses Laravel's built-in `TrustProxies` middleware with `TRUSTED_PROXIES` documented for production reverse-proxy deployments.
- SMS.to sending remains implemented through the existing direct Guzzle client and local `config/smsto.php`; the unused Laravel/Lumen wrapper provider and facade registration were removed.
- User view presenters now use a small local `VanguardLTE\Support\Presenter` implementation instead of the legacy Laracasts package.
- The unused Laravel legacy factory package and stale `database/factories` classmap entry were removed; current tests and seeders do not use the legacy `factory()` helper.
- Runtime settings now use a local JSON-backed `VanguardLTE\Support\Settings` store with compatibility bindings for the old `settings()` helper and `Settings::set/save()` backend writes.
- The unused server-side Yajra DataTables package and config were removed; remaining DataTables mentions are frontend jQuery assets/comments.
- Roles and permissions now run through local models, middleware, exceptions, Blade directives, and compatibility helpers instead of `jeremykenedy/laravel-roles`, which also removes transitive `eklundkristoffer/seedster` and `laravel/helpers`. Backend Blade form/script calls now run through local `Form`/`HTML` facades instead of `laravelcollective/html`. API JWT auth now runs through a local HS256 compatibility layer with revocable `api_tokens` storage instead of `tymon/jwt-auth`.
- The test suite had only example tests before this work. HMAC success/replay/body-hash/IP allowlist coverage, tenant isolation coverage for sessions/reports/wallet attempts, request validation, and wallet idempotency conflict coverage have now been added; launch, wallet mutation state transitions, and broader reporting edge-case coverage remain incomplete.
- B2B API JSON responses now use a shared envelope with `success`, `status`, `request_id`, `data` or `error`, backed by a central error catalog.
- Financial report code used floats before this audit pass.
- Sandbox wallet code still uses floats and should stay non-production.
- The provider adapter contract now covers provider code, health, wallet action capabilities, launch preparation, session refresh, and session close; only the internal Goldsvet adapter is implemented until real provider docs/credentials are available.
- Admin B2B backoffice and operator portal are not implemented as full production workflows. A B2B-RBAC-protected operations dashboard now boots inside the authenticated backend at `/backend/b2b`, step-up protected operator configuration, credential lifecycle, manual wallet, settlement workflow, raw payload review, case-management/support-ticket listing, and read-only audit trail screens are available at `/backend/b2b/operators`, `/backend/b2b/credentials`, `/backend/b2b/wallet/manual-actions`, `/backend/b2b/settlements`, `/backend/b2b/payloads`, `/backend/b2b/cases`, and `/backend/b2b/audit`, and a signed tenant-scoped operator portal is available at `/api/b2b/v1/portal` with JSON overview data, workflow/callback/report/support pages, audited support case comments, and audited support ticket create/comment/close endpoints under `/api/b2b/v1/portal/*`. Unified operator audit events now cover operator configuration, credential lifecycle actions, settlement export/submission/approval, denied privileged actions, manual wallet actions, raw payload review, case-management actions, operator support comments, and support-ticket lifecycle actions.
- Queue isolation, reconciliation jobs, settlement workflow, OpenAPI, readiness checks, aggregate metrics, B2B structured JSON logs, wallet callback correlation headers, Prometheus/Alertmanager alert artifacts, CI foundations, Node/WebSocket manifest/proxy preflight, backup/restore/migration-rehearsal/rollback artifacts, smoke/load verification scripts, release evidence package validation, and dependency-audit remediation are present; release-check covers Laravel security mitigation exposure and structured logging configuration. Staging, final WebSocket proxy validation, backup/restore drills, executed smoke/load evidence, notification delivery, provider gates, and a target-environment `release-evidence.json` package still need closure.
- `b2b:release-check --production` is available and passes locally with the CI-like production environment. B2B shared-state defaults now point at Redis, sandbox defaults to disabled, structured logging defaults to the JSON `b2b` channel, session/login/password security controls and credential-change revocation are release-gated, and previously tracked local secret-bearing files have been removed from the current tree; rotate any values that were committed to history before production launch.

### P2

- Documentation exists under `docs/b2b`, but production deployment/runbook documentation is incomplete.
- Scheduler output shows many jobs but no dedicated B2B queue topology.
- Large frontend/static/vendor assets make broad scans noisy and should be excluded from release artifacts where possible.

## Notes

- Previously tracked `.env`, `.env_old`, SQL dump, WebSocket key/cert files, `vendor`, `composer.phar`, and `composer-setup.php` have been removed from the current tree and are covered by ignore/export-ignore rules. Treat any values committed to history as compromised and rotate before production launch.
- The project must not be called production-ready until release gates are rerun on a clean Linux-like environment with database, Redis, queue workers, WebSocket proxy, SSL, production secrets, real provider/legal artifacts, and a redacted evidence package that passes `b2b:evidence-check --production`.
