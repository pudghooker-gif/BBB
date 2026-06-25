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
- Operator credential lifecycle audit foundation: API key rotation/revocation CLI commands require actor/reason, disable revoked keys, successful HMAC use writes throttled `api_key.used` events, and per-key `max_rps` is enforced by the shared resilience guard.
- Production deployment artifact foundation: Nginx, PHP-FPM, Supervisor, systemd scheduler/WebSocket, cron fallback, backup, healthcheck, rollback templates, release runbook, and release-gate coverage are present.
- B2B admin authorization foundation: dedicated permission catalog, role map, deny-by-default privileged action guard, CLI step-up confirmation, and denial audit events protect operator creation, credential rotation/revocation, and manual wallet actions.
- Operator health/circuit breaker foundation.
- B2B console commands are registered.
- `b2b:release-check --production` verifies Redis/shared-cache, queue, sandbox, debug, private callback, and release file gates.

## Missing Or Incomplete

### P0/P1 Before Production

- Upgraded production database migration verification remains required on a staging copy.
- Production environment must pass `b2b:release-check --production`; current local workspace still contains release-blocking local files and non-Redis shared-state defaults.
- External provider adapters still require real provider-specific implementations and certification docs.
- Production-grade wallet state machine still needs provider/operator status lookup contracts, authenticated web step-up for manual actions, settlement-grade reconciliation reports, and full rollback-required recovery flows. The current foundation has explicit transition validation, append-only transition logging, recursive sensitive-field redaction, retryable `unknown`, retry-budget `dead_letter`, operator-scoped status lookup, reconciliation item scanning, privileged CLI guard, and audited CLI manual actions.
- Dedicated B2B admin backoffice and operator portal, including web UI over the deny-by-default RBAC/step-up foundation.
- Runtime job implementations are still needed for workflows that currently run as artisan commands or inline request work.
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
