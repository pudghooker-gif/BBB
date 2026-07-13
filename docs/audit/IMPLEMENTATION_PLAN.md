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
- Expand tenant isolation tests beyond the current games, game assignments, launch, operator portal game detail, session detail/close, wallet mutation, sessions, reports, settlements, wallet attempts, and status lookup coverage.
- Add request validation tests for launch and wallet operations.
- Add idempotency conflict tests for duplicate wallet transaction IDs with changed payloads.
- Normalize JSON response/error format and keep envelope compatibility tests.
- Maintain OpenAPI and Postman artifacts from verified production routes.

## Stage 3: Wallet And Ledger

- Expand the explicit wallet state machine beyond the current transition-log foundation.
- Extend provider-specific status/rollback contracts beyond the current operator `transaction_status` lookup and bounded rollback recovery, then expand the authenticated web step-up UI beyond the current manual-action and settlement workflow screens.
- Keep the wallet ledger append-oriented with status transition history and maintain payload redaction on every new wallet persistence/output path.
- Keep duplicate bet/win/refund/rollback idempotency regression tests green across every wallet mutation endpoint.

## Stage 4: Admin And Operator Portal

- Keep extending the B2B-RBAC-protected `/backend/b2b` operations dashboard, operator configuration screen, credential lifecycle screen, manual wallet action screen, settlement workflow list/detail/scoped-action screens, raw payload review screen, case-management list/detail/timeline/staff-action screens, support-ticket list/thread/staff-action screens, and audit trail screen into a cohesive production backoffice.
- Keep expanding the tenant-facing signed `/api/b2b/v1/portal` UI beyond the current credentials, game assignments, sessions with tenant-scoped HTML drilldown, transactions with tenant-scoped HTML drilldown, settlements with tenant-scoped HTML drilldown, provider launch diagnostics with tenant-scoped drilldown, cases, callback settings/errors, report drilldowns, support incidents, redacted API/audit logs, docs workflow pages, audited support case comments/detail/thread readback with state-aware comment endpoint paths, and audited support ticket create/comment/close/reopen/detail/thread readback lifecycle with state-aware action endpoint paths into a richer production operator experience.
- Build the portal UX over the current audited credential rotation/revocation, successful-use audit, per-key rate-limit, and deny-by-default privileged-action guard foundation, then validate it in staging behind the final signing/proxy topology.
- Keep unified operator audit coverage for exports and dangerous admin actions; settlement export/submission/approval with redacted persisted reasons, credential lifecycle actions, denied privileged actions, manual wallet actions, case actions, and support-ticket lifecycle actions now write operator audit events.

## Stage 5: Deployment And Observability

- Keep deployment templates and runbook current; validate Nginx, PHP-FPM, systemd, cron, final topology/TLS/trusted-proxy/shared-state checks, WebSocket origin/session-cookie/heartbeat controls, public proxy smoke, backup/off-host verification, restore, migration rehearsal, healthcheck, smoke/load verification, release evidence package checks, and rollback on a staging host.
- Keep job-backed wallet retry/reconciliation/cleanup workflows wired to the B2B queue topology and validate worker execution in staging.
- Keep health/readiness/metrics endpoints, B2B structured JSON logs, the log-shipping marker/external-delivery checks, outbound wallet callback correlation IDs, wallet/provider correlation evidence checks, shipped Prometheus/Alertmanager alert-routing, Prometheus scrape/rule evidence tooling, synthetic notification smoke artifacts, and downstream receiver confirmation checks current, then add staging scrape/log-shipper/correlation/notification validation.
- Add and run `b2b:release-check --production` for Redis/shared-cache, queue, sandbox, debug, secure database-backed session cookies, production login throttling, password policy, credential-change session revocation, private callback, structured logging, locked Composer dependency audit, Laravel security mitigation coverage, and artifact-secret gates.
- Keep CI release verification current for Composer validate/install, syntax lint, PHPUnit, route boot/cache, dependency audit visibility, and production release-check. Direct `laravel/ui`, `fideloper/proxy`, SMS.to Laravel wrapper, debugbar, `laracasts/presenter`, `laravel/legacy-factories`, `anlutro/l4-settings`, `proengsoft/laravel-jsvalidation`, `pragmarx/google2fa-laravel`, `tymon/jwt-auth`, `jeremykenedy/laravel-roles`, `laravelcollective/html`, transitive `eklundkristoffer/seedster`, transitive `laravel/helpers`, and server-side Yajra DataTables dependencies are already removed, and the PHP 8.3 / Laravel 12 upgrade has turned the Composer audit gate green.
- Treat further framework/platform work as ongoing maintenance: keep PHP 8.3+, Laravel 12, Symfony/Flysystem/Mailer-era transitive packages, PHPUnit, and regression tests current. Roles/permissions, JWT auth, and the former Collective Form/HTML calls now run behind local compatibility surfaces.

## Stage 6: Release Gates

- Keep clean migration verification in tests, including B2B production lookup/reporting indexes, and run `deploy/scripts/migration-rehearsal.sh` against a restored production database copy on staging.
- Verify rollback where practical.
- Verify queues with `deploy/scripts/queue-runtime-drill.sh`, failed-job handling drills, scheduler-dispatched B2B jobs, WebSocket proxy, sandbox wallet flow, reports, settlements, backup/restore drills, smoke tests, measured smoke-load results, and a target-environment `release-evidence.json` package that passes `b2b:evidence-check --production`.
- Document all remaining external blockers before any production launch claim.
