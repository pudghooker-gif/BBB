# B2B Gap Analysis

Date: 2026-06-24

## Implemented Foundation

- B2B operator and API key models/migrations.
- HMAC middleware with timestamp, nonce, body hash, canonical request signing, encrypted secret, constant-time signature comparison, replay protection, request ID propagation, and exact/CIDR IP allowlist.
- Game catalog model and sync command.
- B2B launch session model, dedicated operator-game assignment model, operator-scoped game availability/session guard, provider adapter contract, provider-backed session close, and public launcher bridge to the legacy launcher.
- Shadow user foundation.
- Wallet transaction table, idempotency key, changed-payload conflict detection, callback attempt logging, recursive sensitive-field redaction for wallet payload persistence/output, append-only state transition log, status lookup endpoint, reconciliation item queue, operator `transaction_status` lookup for `unknown` reconciliation states, audited manual-action foundation, bounded retry budget to `dead_letter`, bounded rollback recovery to `reversed` or `manual_review`, retry command, and sandbox wallet.
- Reporting endpoints for summary, transactions, GGR, settlements, deterministic settlement export snapshots/detail, and reconciliation exposure/aging.
- Standard B2B JSON response envelope and error catalog for API routes, including request ID propagation.
- Feature tests now cover HMAC success/failure/replay, tenant isolation for sessions/reports/settlements/wallet attempts, operator-scoped games/launch/session detail/session close/wallet mutation flows, dedicated operator-game assignment allow/deny behavior, request validation for launch and wallet payloads, wallet idempotency conflicts, wallet status transition logging, status lookup scoping, reconciliation scanning/reporting, operator status lookup resolution for `unknown` wallet rows, rollback recovery, manual wallet action auditing, and settlement export/approval auditing.
- OpenAPI and Postman JSON artifacts cover the verified production `b2b/v1` routes.
- Unit tests verify clean SQLite migration application and no-op re-run for B2B tables/columns.
- B2B queue topology config, Supervisor worker template, and queue topology tests cover wallet-live, wallet-retry, provider-callbacks, reporting, settlement, reconciliation, notifications, and maintenance queues.
- Runtime job foundation dispatches wallet retry, rollback recovery, reconciliation, and stale-session cleanup work onto the configured B2B Redis queues, while preserving inline artisan execution for local/emergency operations.
- Public health/readiness foundation checks database connectivity, critical B2B tables, cache runtime, queue configuration, storage writability, and production-safe release configuration without exposing secrets.
- CI release-verification foundation is present in GitHub Actions for Composer validation/install, PHP syntax lint, Laravel route boot/cache, PHPUnit, Composer audit visibility, and the B2B production release-check.
- Operator credential lifecycle audit foundation: API key rotation/revocation CLI commands require actor/reason, disable revoked keys, successful HMAC use writes throttled `api_key.used` events, and per-key `max_rps` is enforced by the shared resilience guard.
- Production deployment artifact foundation: Nginx, PHP-FPM, Supervisor, systemd scheduler/WebSocket, cron fallback, backup, healthcheck, rollback templates, release runbook, and release-gate coverage are present.
- B2B admin authorization foundation: dedicated permission catalog, role map, deny-by-default privileged action guard, CLI step-up confirmation, and denial audit events protect operator creation, credential rotation/revocation, manual wallet actions, and settlement approval actions.
- Operator health/circuit breaker foundation.
- B2B console commands are registered.
- `b2b:release-check --production` verifies Redis/shared-cache, queue, sandbox, debug, private callback, locked Composer dependency audit, and release file gates.

## Missing Or Incomplete

### P0/P1 Before Production

- Upgraded production database migration verification remains required on a staging copy.
- Production environment must pass `b2b:release-check --production`; current local workspace still contains release-blocking local files, non-Redis shared-state defaults, and Composer audit blockers.
- Composer dependency audit has been reduced to 3 Laravel framework advisories after a PHP 7.4-compatible dependency refresh. The remaining Laravel advisories require a PHP/Laravel major-upgrade plan or vendor-supported security backports before production.
- External provider adapters still require real provider-specific implementations and certification docs.
- Production-grade wallet state machine still needs provider-specific status/rollback contracts and certification plus authenticated web step-up over manual actions and settlement approval. The current foundation has explicit transition validation, append-only transition logging, recursive sensitive-field redaction, retryable `unknown`, retry-budget `dead_letter`, operator-scoped status lookup, reconciliation item scanning/reporting with conservative operator `transaction_status` resolution, bounded rollback recovery, privileged CLI guard, audited CLI manual actions, deterministic settlement exports, and audited settlement submit/approve/reject commands.
- Dedicated B2B admin backoffice and operator portal, including web UI over the deny-by-default RBAC/step-up foundation.
- Runtime job implementations now cover scheduled wallet retry/reconciliation/cleanup workflows; production still needs staging validation of worker counts, scheduler locking, failed-job handling, and observability under real traffic.
- Health/readiness endpoints are present; production still needs external uptime checks, metrics scraping, alert routing, and staging validation behind the final Nginx/TLS topology.
- Production domains, host-level secret store, off-host backup storage, WebSocket TLS/proxy validation, and rollback drill remain environment-specific launch blockers.

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
