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

- Extend the read-only `/backend/b2b` operations dashboard into dedicated B2B backoffice routes/controllers/views with server-side RBAC and authenticated step-up for dangerous actions.
- Build the tenant-facing operator portal UI over the signed read-only `/api/b2b/v1/portal/overview` bootstrap, then expand it into credentials, callback settings, game assignments, sessions, transactions, reports, and docs.
- Build the portal UX over the current audited credential rotation/revocation, successful-use audit, per-key rate-limit, and deny-by-default privileged-action guard foundation.
- Add remaining audit events for exports and dangerous admin actions.

## Stage 5: Deployment And Observability

- Keep deployment templates and runbook current; validate Nginx, PHP-FPM, systemd, cron, WebSocket, backup, healthcheck, and rollback on a staging host.
- Keep job-backed wallet retry/reconciliation/cleanup workflows wired to the B2B queue topology and validate worker execution in staging.
- Keep health/readiness/metrics endpoints current, then add structured logs, deeper correlation IDs, alert routing, and staging scrape validation.
- Add and run `b2b:release-check --production` for Redis/shared-cache, queue, sandbox, debug, private callback, locked Composer dependency audit, Laravel advisory mitigations, and artifact-secret gates.
- Keep CI release verification current for Composer validate/install, syntax lint, PHPUnit, route boot/cache, dependency audit visibility, and production release-check. Keep trimming legacy upgrade blockers as they are proven unused or easy to localize; direct `laravel/ui`, `fideloper/proxy`, SMS.to Laravel wrapper, debugbar, and `laracasts/presenter` dependencies are already removed. Close the remaining Laravel/SwiftMailer findings through a PHP/Laravel major-upgrade or supported security-backport plan so the blocking audit gate can turn green.

## Stage 6: Release Gates

- Keep clean migration verification in tests and run upgraded-database migrations on a staging copy.
- Verify rollback where practical.
- Verify queues, scheduler-dispatched B2B jobs, WebSocket proxy, sandbox wallet flow, reports, settlements, backups, and smoke tests.
- Document all remaining external blockers before any production launch claim.
