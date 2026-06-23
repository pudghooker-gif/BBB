# B2B Gap Analysis

Date: 2026-06-23

## Implemented Foundation

- B2B operator and API key models/migrations.
- HMAC middleware with timestamp, nonce, body hash, canonical request signing, encrypted secret, constant-time signature comparison, replay protection, request ID propagation, and exact/CIDR IP allowlist.
- Game catalog model and sync command.
- B2B launch session model, provider adapter contract, and public launcher bridge to the legacy launcher.
- Shadow user foundation.
- Wallet transaction table, idempotency key, changed-payload conflict detection, callback attempt logging, append-only state transition log, bounded retry budget to `dead_letter`, retry command, and sandbox wallet.
- Reporting endpoints for summary, transactions, GGR, and settlements.
- Standard B2B JSON response envelope and error catalog for API routes, including request ID propagation.
- Feature tests now cover HMAC success/failure/replay, tenant isolation for sessions/reports/settlements/wallet attempts, request validation for launch and wallet payloads, wallet idempotency conflicts, and wallet status transition logging.
- Operator health/circuit breaker foundation.
- B2B console commands are registered.
- `b2b:release-check --production` verifies Redis/shared-cache, queue, sandbox, debug, private callback, and release file gates.

## Missing Or Incomplete

### P0/P1 Before Production

- Full migration verification on clean and upgraded databases.
- Production environment must pass `b2b:release-check --production`; current local workspace still contains release-blocking local files and non-Redis shared-state defaults.
- External provider adapters still require real provider-specific implementations and certification docs.
- Production-grade wallet state machine still needs status lookup, reconciliation jobs, manual review operations, reversal controls, and full rollback-required recovery flows. The current foundation has explicit transition validation, append-only transition logging, retryable `unknown`, and retry-budget `dead_letter`.
- Operator-scoped tests for games, launch, wallet mutation flows, and remaining close/detail edge cases.
- Dedicated B2B admin backoffice and operator portal.
- OpenAPI/Postman generated from verified routes.
- Queue topology and worker configs for wallet-live, wallet-retry, provider-callbacks, reporting, settlement, reconciliation, notifications, and maintenance.
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
