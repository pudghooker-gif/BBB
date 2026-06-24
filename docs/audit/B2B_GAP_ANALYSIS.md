# B2B Gap Analysis

Date: 2026-06-24

## Implemented Foundation

- B2B operator and API key models/migrations.
- HMAC middleware with timestamp, nonce, body hash, canonical request signing, encrypted secret, constant-time signature comparison, replay protection, request ID propagation, and exact/CIDR IP allowlist.
- Game catalog model and sync command.
- B2B launch session model, dedicated operator-game assignment model, operator-scoped game availability/session guard, provider adapter contract, provider-backed session close, and public launcher bridge to the legacy launcher.
- Shadow user foundation.
- Wallet transaction table, idempotency key, changed-payload conflict detection, callback attempt logging, recursive sensitive-field redaction for wallet payload persistence/output, append-only state transition log, status lookup endpoint, reconciliation item queue, audited manual-action foundation, bounded retry budget to `dead_letter`, retry command, and sandbox wallet.
- Reporting endpoints for summary, transactions, GGR, and settlements.
- Standard B2B JSON response envelope and error catalog for API routes, including request ID propagation.
- Feature tests now cover HMAC success/failure/replay, tenant isolation for sessions/reports/settlements/wallet attempts, operator-scoped games/launch/session detail/session close/wallet mutation flows, dedicated operator-game assignment allow/deny behavior, request validation for launch and wallet payloads, wallet idempotency conflicts, wallet status transition logging, status lookup scoping, reconciliation scanning, and manual wallet action auditing.
- OpenAPI and Postman JSON artifacts cover the verified production `b2b/v1` routes.
- Unit tests verify clean SQLite migration application and no-op re-run for B2B tables/columns.
- B2B queue topology config, Supervisor worker template, and queue topology tests cover wallet-live, wallet-retry, provider-callbacks, reporting, settlement, reconciliation, notifications, and maintenance queues.
- Operator credential lifecycle audit foundation: API key rotation/revocation CLI commands require actor/reason, disable revoked keys, and write append-only operator audit events.
- Operator health/circuit breaker foundation.
- B2B console commands are registered.
- `b2b:release-check --production` verifies Redis/shared-cache, queue, sandbox, debug, private callback, and release file gates.

## Missing Or Incomplete

### P0/P1 Before Production

- Upgraded production database migration verification remains required on a staging copy.
- Production environment must pass `b2b:release-check --production`; current local workspace still contains release-blocking local files and non-Redis shared-state defaults.
- External provider adapters still require real provider-specific implementations and certification docs.
- Production-grade wallet state machine still needs provider/operator status lookup contracts, deny-by-default RBAC and step-up controls for manual actions, settlement-grade reconciliation reports, and full rollback-required recovery flows. The current foundation has explicit transition validation, append-only transition logging, recursive sensitive-field redaction, retryable `unknown`, retry-budget `dead_letter`, operator-scoped status lookup, reconciliation item scanning, and audited CLI manual actions.
- Dedicated B2B admin backoffice and operator portal, including deny-by-default RBAC and step-up approval for credential and manual-wallet actions.
- Runtime job implementations are still needed for workflows that currently run as artisan commands or inline request work.
- Deployment configs for Nginx, PHP-FPM, Supervisor/systemd, cron, backups, rollback, and health checks.

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
