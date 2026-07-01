# Security Risks

Date: 2026-06-22

## P0

- Secret-bearing files were previously tracked in the repository working tree: `.env`, `.env_old`, `PTWebSocket/ssl/key.key`, `PTWebSocket/ssl/crt.crt`, and `totalbet365.sql`. They have been removed from the current tree; contents were not copied or inspected in this report.
- Production release artifacts must exclude local env files, SQL dumps, backups, development logs, and local TLS keys.
- Because these secret-bearing paths were tracked, rotate the affected secrets and treat production launch as blocked until rotation is complete.
- Wallet callback URLs require SSRF protection. This audit pass adds scheme, localhost, private IP, reserved IP, and DNS resolution checks outside local/testing environments.
- Launch `return_url` requires an operator allowlist to reduce open redirect risk. This audit pass enforces operator `base_url` or `settings.return_url_allowlist`.

## P1

- B2B HMAC replay protection and rate limiting must use Redis/shared cache across API nodes. `php artisan b2b:release-check --production` now fails when production shared state is not Redis.
- API key rotation/revocation now has audited CLI coverage with mandatory actor/reason, permission, step-up confirmation, throttled successful-use audit events, and per-key app-level rate limits. Production UX and real authenticated step-up controls are still required before broad admin exposure.
- Raw wallet payload persistence and status/report output now redact sensitive fields recursively. Before broad admin exposure, run a one-time review of any production rows created before this redaction existed.
- B2B privileged CLI actions now use a dedicated deny-by-default permission model, but the web admin/portal still needs authenticated B2B RBAC middleware and real session step-up before broad exposure.
- Production deployment templates and runbook are now release-gate checked, but host secret storage, off-host backups, TLS/domain setup, and rollback rehearsal must be verified per environment.
- Node/WebSocket now has a private package manifest, pnpm lockfile, syntax check, Nginx proxy template, and release-gate coverage. The legacy runtime still uses deprecated `request` dependencies and needs final public-domain proxy/auth validation before launch.
- Composer dependency audit now runs inside `b2b:release-check --production`. The PHP 7.4-compatible dependency refresh reduced findings from 39 advisories across 16 packages to 3 remaining Laravel framework advisories. The app backports CRLF email validation hardening, blocks `php7`/`php8` upload extensions, and release-gates standard Laravel signed-route plus temporary signed URL exposure while this Laravel 8 branch is still in use. Treat the remaining Composer advisories, abandoned packages, and the PHP/Laravel major-upgrade plan as production blockers until the audit gate is clean.
- Existing B2C balance flows use floats and direct balance mutation in legacy code. Do not reuse those flows as B2B financial ledger logic.

## Rotation Plan

1. Inventory all secret-bearing files by path without copying values into tickets or docs.
2. Rotate APP_KEY, DB credentials, JWT secrets, WebSocket TLS material, provider/API secrets, and any credentials present in SQL dumps if they ever reached shared history.
3. Replace local TLS files with deployment-managed certificates.
4. Keep `.env.example` sanitized and document required variables.
5. Exclude `.env`, `.env_old`, SQL dumps, backups, logs, and local key material from production packaging.

## External Launch Blockers

- Real provider API credentials and technical documentation.
- Game certificates and gambling/software licenses.
- Jurisdiction approvals.
- Production domains, SSL, production secrets, infrastructure, verified backup storage, and load-test environment.
