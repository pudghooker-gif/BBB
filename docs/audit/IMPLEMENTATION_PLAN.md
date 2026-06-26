# Implementation Plan

Date: 2026-06-24

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
- Expand tenant isolation tests beyond the current games, game assignments, launch, session detail/close, wallet mutation, sessions, reports, settlements, wallet attempts, and status lookup coverage.
- Add request validation tests for launch and wallet operations.
- Add idempotency conflict tests for duplicate wallet transaction IDs with changed payloads.
- Normalize JSON response/error format and keep envelope compatibility tests.
- Maintain OpenAPI and Postman artifacts from verified production routes.

## Stage 3: Wallet And Ledger

- Expand the explicit wallet state machine beyond the current transition-log foundation.
- Extend provider-specific status/rollback contracts beyond the current operator `transaction_status` lookup and bounded rollback recovery, then add authenticated web step-up UI over the current CLI manual-action and settlement export/approval foundations.
- Keep the wallet ledger append-oriented with status transition history and maintain payload redaction on every new wallet persistence/output path.
- Add duplicate bet/win/refund/rollback tests.

## Stage 4: Admin And Operator Portal

- Add dedicated B2B backoffice routes/controllers/views with server-side RBAC.
- Add operator portal with tenant-scoped dashboard, credentials, callback settings, game assignments, sessions, transactions, reports, and docs.
- Build the portal UX over the current audited credential rotation/revocation, successful-use audit, per-key rate-limit, and deny-by-default privileged-action guard foundation.
- Add remaining audit events for exports and dangerous admin actions.

## Stage 5: Deployment And Observability

- Keep deployment templates and runbook current; validate Nginx, PHP-FPM, systemd, cron, WebSocket, backup, healthcheck, and rollback on a staging host.
- Keep job-backed wallet retry/reconciliation/cleanup workflows wired to the B2B queue topology and validate worker execution in staging.
- Keep health/readiness endpoints current, then add structured logs, correlation IDs, metrics, and alerting.
- Add and run `b2b:release-check --production` for Redis/shared-cache, queue, sandbox, debug, private callback, and artifact-secret gates.
- Add CI for composer validate/install, syntax lint, PHPUnit, route boot, migration test, security scan, and optional frontend/Node checks.

## Stage 6: Release Gates

- Keep clean migration verification in tests and run upgraded-database migrations on a staging copy.
- Verify rollback where practical.
- Verify queues, scheduler-dispatched B2B jobs, WebSocket proxy, sandbox wallet flow, reports, settlements, backups, and smoke tests.
- Document all remaining external blockers before any production launch claim.
