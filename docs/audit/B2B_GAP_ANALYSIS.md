# B2B Gap Analysis

Date: 2026-06-22

## Implemented Foundation

- B2B operator and API key models/migrations.
- HMAC middleware with timestamp, nonce, body hash, canonical request signing, encrypted secret, constant-time signature comparison, replay protection, request ID propagation, and exact/CIDR IP allowlist.
- Game catalog model and sync command.
- B2B launch session model and public launcher bridge to the legacy launcher.
- Shadow user foundation.
- Wallet transaction table, idempotency key, callback attempt logging, retry command, and sandbox wallet.
- Reporting endpoints for summary, transactions, GGR, and settlements.
- Feature tests now cover HMAC success/failure/replay, tenant isolation for sessions/reports/settlements/wallet attempts, and request validation for launch and wallet payloads.
- Operator health/circuit breaker foundation.
- B2B console commands are registered.

## Missing Or Incomplete

### P0/P1 Before Production

- Full migration verification on clean and upgraded databases.
- Redis-backed nonce/rate-limit/circuit state confirmation.
- Dedicated API response envelope and error catalog across every endpoint.
- Wallet idempotency conflicts with the same transaction ID and changed payload need explicit rejection tests and handling.
- Provider adapter contract for all required operations.
- Production-grade wallet state machine: unknown, rollback_required, reversed, dead-letter/manual_review, status lookup, reconciliation, and safe retry budget.
- Append-oriented immutable ledger with status transitions.
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
