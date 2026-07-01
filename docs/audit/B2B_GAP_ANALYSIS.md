# B2B Gap Analysis

Date: 2026-06-24

## Implemented Foundation

- B2B operator and API key models/migrations.
- HMAC middleware with timestamp, nonce, body hash, canonical request signing, encrypted secret, constant-time signature comparison, replay protection, request ID propagation, and exact/CIDR IP allowlist.
- Game catalog model and sync command.
- B2B launch session model, dedicated operator-game assignment model, operator-scoped game availability/session guard, provider adapter contract, explicit wallet action contract profile, provider-backed session close, and public launcher bridge to the legacy launcher.
- Shadow user foundation.
- Wallet transaction table, idempotency key, changed-payload conflict detection, callback attempt logging, recursive sensitive-field redaction for wallet payload persistence/output, append-only state transition log, status lookup endpoint, reconciliation item queue, operator `transaction_status` lookup for `unknown` reconciliation states, audited manual-action foundation, bounded retry budget to `dead_letter`, bounded rollback recovery to `reversed` or `manual_review`, retry command, and sandbox wallet.
- Reporting endpoints for summary, transactions, GGR, settlements, deterministic settlement export snapshots/detail, and reconciliation exposure/aging.
- Signed read-only operator portal endpoint at `/api/b2b/v1/portal`, JSON overview at `/api/b2b/v1/portal/overview`, and signed HTML workflow pages for credentials, games, sessions, transactions, settlements, cases, callbacks, reports, and docs, returning tenant-scoped profile, credential, game assignment, session, wallet, callback, settlement, and reconciliation summaries without secrets or raw payloads.
- Standard B2B JSON response envelope and error catalog for API routes, including request ID propagation.
- Feature tests now cover HMAC success/failure/replay, tenant isolation for sessions/reports/settlements/wallet attempts, operator-scoped games/launch/session detail/session close/wallet mutation flows, dedicated operator-game assignment allow/deny behavior, request validation for launch and wallet payloads, wallet idempotency conflicts, wallet status transition logging, status lookup scoping, reconciliation scanning/reporting, operator status lookup resolution for `unknown` wallet rows, rollback recovery, manual wallet action auditing, and settlement export/approval auditing.
- OpenAPI and Postman JSON artifacts cover the verified production `b2b/v1` routes.
- Unit tests verify clean SQLite migration application and no-op re-run for B2B tables/columns.
- B2B queue topology config, Supervisor worker template, and queue topology tests cover wallet-live, wallet-retry, provider-callbacks, reporting, settlement, reconciliation, notifications, and maintenance queues.
- Runtime job foundation dispatches wallet retry, rollback recovery, reconciliation, and stale-session cleanup work onto the configured B2B Redis queues, while preserving inline artisan execution for local/emergency operations.
- Public health/readiness foundation checks database connectivity, critical B2B tables, cache runtime, queue configuration, storage writability, and production-safe release configuration without exposing secrets. Prometheus-compatible aggregate metrics are exposed without operator IDs, payloads, or secrets.
- CI release-verification foundation is present in GitHub Actions for Composer validation/install, PHP syntax lint, Laravel route boot/cache, PHPUnit, Composer audit visibility, and the B2B production release-check.
- Operator credential lifecycle audit foundation: API key rotation/revocation CLI and web workflows require actor/reason, disable revoked keys, successful HMAC use writes throttled `api_key.used` events, and per-key `max_rps` is enforced by the shared resilience guard.
- Production deployment artifact foundation: Nginx, PHP-FPM, Supervisor, systemd scheduler/WebSocket, cron fallback, backup, restore, healthcheck, rollback templates, WebSocket Node manifest/lockfile, release runbook, and release-gate coverage are present.
- B2B admin authorization foundation: dedicated permission catalog, role map, B2B web RBAC middleware for the backend dashboard, deny-by-default privileged action guard, CLI step-up confirmation, session-bound web step-up middleware/routes, and audit events protect operator creation, credential rotation/revocation, manual wallet actions, settlement export/submission/approval, and denial paths.
- B2B backend operations dashboard is wired into the existing authenticated backend at `/backend/b2b`; web step-up confirmation routes are available under `/backend/b2b/step-up/{action}`; B2B-RBAC and web-step-up protected manual wallet, settlement workflow, credential lifecycle, operator configuration, raw payload review, and case-management screens are available at `/backend/b2b/wallet/manual-actions`, `/backend/b2b/settlements`, `/backend/b2b/credentials`, `/backend/b2b/operators`, `/backend/b2b/payloads`, and `/backend/b2b/cases`.
- Operator health/circuit breaker foundation.
- B2B console commands are registered.
- `b2b:release-check --production` verifies Redis/shared-cache, queue, sandbox, debug, private callback, provider wallet action contracts, web-surface and web step-up registration, Node/WebSocket release coverage, locked Composer dependency audit, and release file gates.

## Missing Or Incomplete

### P0/P1 Before Production

- Upgraded production database migration verification remains required on a staging copy.
- Production environment must pass `b2b:release-check --production`; current local workspace still has Composer audit blockers. B2B shared-state defaults now point at Redis, sandbox defaults to disabled, and previously tracked local secret files have been removed from the current tree, but any secrets committed to history still require rotation before launch.
- Production Composer dependency audit has been reduced to Laravel framework advisories plus the Laravel 8 `swiftmailer/swiftmailer` transitive dependency after removing debug tooling from Composer dependencies, replacing legacy Faker, localizing runtime settings/presenters, and removing unused/legacy direct dependencies such as `laravel/ui`, `fideloper/proxy`, the SMS.to Laravel wrapper, `laravel/legacy-factories`, and server-side Yajra DataTables. The remaining Laravel advisories and SwiftMailer dependency require a PHP/Laravel major-upgrade plan or vendor-supported security backports before production.
- External provider adapters still require real provider-specific implementations and certification docs against the explicit wallet action contract profile.
- Production-grade wallet state machine still needs external provider certification and provider-certified reversal semantics. The current foundation has explicit transition validation, append-only transition logging, recursive sensitive-field redaction, retryable `unknown`, retry-budget `dead_letter`, operator-scoped status lookup, provider-declared status/rollback contracts, reconciliation item scanning/reporting with conservative operator `transaction_status` resolution, bounded rollback recovery, privileged CLI guard, audited CLI and web manual actions, deterministic settlement exports, audited CLI and web settlement submit/approve/reject actions, and session-bound web step-up confirmation.
- Dedicated B2B admin backoffice and full operator portal UX remain incomplete. A B2B-RBAC-protected backend operations dashboard now boots at `/backend/b2b`, step-up protected manual wallet, settlement workflow, credential lifecycle, operator configuration, raw payload review, and case-management screens are available at `/backend/b2b/wallet/manual-actions`, `/backend/b2b/settlements`, `/backend/b2b/credentials`, `/backend/b2b/operators`, `/backend/b2b/payloads`, and `/backend/b2b/cases`, signed tenant-facing read-only operator portal pages are available at `/api/b2b/v1/portal/*`, JSON overview data is available at `/api/b2b/v1/portal/overview`, and web step-up confirmation is available. Portal production UX/staging validation and operator-visible mutating support flows still need implementation, but successful CLI/web credential, settlement, manual wallet, operator configuration, raw payload review, and case-management actions now leave unified operator audit events.
- Runtime job implementations now cover scheduled wallet retry/reconciliation/cleanup workflows; production still needs staging validation of worker counts, scheduler locking, failed-job handling, and observability under real traffic.
- Health/readiness/metrics endpoints are present; production still needs external uptime checks, Prometheus scrape wiring, alert routing, and staging validation behind the final Nginx/TLS topology.
- Production domains, host-level secret store, off-host backup storage, final WebSocket TLS/proxy validation, and backup/restore/rollback drills remain environment-specific launch blockers.

### P2 After Stable MVP

- Load/resilience scenarios with measured RPS instead of guessed capacity.
- Horizon evaluation.
- Full B2B UI/UX polish after security and functional gates pass.

## Conflicts And Duplicates Found

- Duplicate B2B route includes in `routes/api.php` and `routes/web.php`.
- Mismatched route namespace for B2B launcher.
- Mismatched route action for game launch.
- Wallet v7 route prefix duplicated the `/api` segment.
- Reporting migration used `game_id` while schema used `game_uid`.
- B2B docs claimed capabilities that were only partial foundations in code.
