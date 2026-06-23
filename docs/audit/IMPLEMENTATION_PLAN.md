# Implementation Plan

Date: 2026-06-23

## Stage 1: Bootstrap And P0 Hardening

- Deduplicate B2B route includes.
- Fix B2B launcher namespace and launch action.
- Register B2B HMAC middleware alias.
- Fix wallet v7 API prefix.
- Guard reporting indexes by actual columns and use `game_uid`.
- Enforce operator context in session/report services.
- Add SSRF guard for wallet callback URLs.
- Add return URL allowlist check for launches.
- Add focused tests for route config and decimal handling.

## Stage 2: Verified B2B API MVP

- Add feature tests for HMAC success/failure/replay.
- Document and enforce canonical request signing with `X-Body-Hash`.
- Add tenant isolation tests for sessions, reports, and wallet attempts.
- Add tenant isolation tests for games, launch, and wallet mutation flows.
- Add request validation tests for launch and wallet operations.
- Add idempotency conflict tests for duplicate wallet transaction IDs with changed payloads.
- Normalize JSON response/error format and keep envelope compatibility tests.
- Add OpenAPI and Postman artifacts from verified routes.

## Stage 3: Wallet And Ledger

- Expand the explicit wallet state machine beyond the current transition-log foundation.
- Add provider/operator status lookup contracts, manual review operations, reversal controls, rollback-required recovery, and settlement-grade reconciliation reports.
- Keep the wallet ledger append-oriented with status transition history and add stronger payload redaction.
- Add duplicate bet/win/refund/rollback tests.

## Stage 4: Admin And Operator Portal

- Add dedicated B2B backoffice routes/controllers/views with server-side RBAC.
- Add operator portal with tenant-scoped dashboard, credentials, callback settings, games, sessions, transactions, reports, and docs.
- Add audit events for credential rotation, manual transaction actions, exports, and dangerous actions.

## Stage 5: Deployment And Observability

- Add Nginx, PHP-FPM, Supervisor/systemd, cron, queue worker, and WebSocket proxy configs.
- Add health/readiness endpoints, structured logs, correlation IDs, metrics, and release runbooks.
- Add and run `b2b:release-check --production` for Redis/shared-cache, queue, sandbox, debug, private callback, and artifact-secret gates.
- Add CI for composer validate/install, syntax lint, PHPUnit, route boot, migration test, security scan, and optional frontend/Node checks.

## Stage 6: Release Gates

- Run migrations on clean and upgraded databases.
- Verify rollback where practical.
- Verify queues, scheduler, WebSocket proxy, sandbox wallet flow, reports, settlements, backups, and smoke tests.
- Document all remaining external blockers before any production launch claim.
