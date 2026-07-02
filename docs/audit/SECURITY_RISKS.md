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
- B2B privileged CLI actions now use a dedicated deny-by-default permission model, and the B2B backend dashboard is protected by B2B web RBAC middleware. Mutating B2B web screens and the broader operator/admin portal still need authenticated B2B RBAC plus session step-up before broad exposure.
- Session cookies now default to HTTP-only and SameSite=Lax, default to secure cookies in `APP_ENV=production`, and are enforced by the production release gate.
- Backend and frontend login throttling is now forcibly enabled in production with a release-gated maximum attempt budget, even if mutable admin settings are weaker.
- Password creation, reset, profile/admin updates, shop bootstrap users, API agent signup, SMS invites, and bulk player generation now use a centralized production password policy with release-gate coverage. The default floor is 12 characters, mixed case, numbers, no whitespace, bcrypt-safe maximum length, and cryptographically random temporary credentials.
- Password resets and password changes now emit a credential-change event that revokes database-backed sessions, remember tokens, and local API tokens. Production release checks require `SESSION_DRIVER=database`, the `sessions` and `api_tokens` runtime migrations, and listener wiring so stale sessions/tokens cannot survive a password change.
- Production deployment templates and runbook are now release-gate checked, but host secret storage, off-host backups, TLS/domain setup, and rollback rehearsal must be verified per environment.
- Node/WebSocket now has a private package manifest, pnpm lockfile, syntax check, Nginx proxy template, origin allowlist, optional token env, session-cookie handshake validation, idle heartbeat, JSON lifecycle logs without cookie/body output, and release-gate coverage. The legacy runtime still uses deprecated `request` dependencies and needs final public-domain proxy/auth validation before launch.
- Composer dependency audit now runs inside `b2b:release-check --production`. The PHP 7.4-compatible dependency refresh reduced findings from 39 advisories across 16 packages to 3 remaining Laravel framework advisories on `laravel/framework v8.83.29`: GHSA-crmm-hgp2-wgrp temporary signed URL path confusion, GHSA-5vg9-5847-vvmq CRLF injection in the default email rule, and CVE-2025-27515/GHSA-78fx-h6xr-vch4 file validation bypass. The app backports CRLF email validation hardening, blocks `php7`/`php8` upload extensions, and release-gates standard Laravel signed-route plus temporary signed URL exposure while this Laravel 8 branch is still in use. `swiftmailer/swiftmailer v6.3.0` remains abandoned because Laravel 8 requires it directly; replacing it with `symfony/mailer` requires a Laravel major upgrade. `proengsoft/laravel-jsvalidation` has been removed and replaced by a local no-op compatibility facade so that server-side Laravel validation remains authoritative without carrying a Laravel-8-only package; `pragmarx/google2fa-laravel` has been replaced by local 2FA middleware/facade bindings backed by the framework-independent `pragmarx/google2fa-qrcode` package. Treat the remaining Composer advisories, abandoned package, and the PHP/Laravel major-upgrade plan as production blockers until the audit gate is clean.
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
