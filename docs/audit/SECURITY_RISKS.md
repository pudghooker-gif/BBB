# Security Risks

Date: 2026-06-22

## P0

- Secret-bearing files were previously tracked in the repository working tree: `.env`, `.env_old`, `PTWebSocket/ssl/key.key`, `PTWebSocket/ssl/crt.crt`, and `totalbet365.sql`. They have been removed from the current tree; contents were not copied or inspected in this report.
- Production release artifacts must exclude local env files, SQL dumps, backups, development logs, and local TLS keys.
- Because these secret-bearing paths were tracked, rotate the affected secrets and treat production launch as blocked until rotation is complete.
- Wallet callback URLs require SSRF protection. This audit pass adds scheme, localhost, private IP, reserved IP, and DNS resolution checks outside local/testing environments.
- Launch `return_url` requires an operator allowlist to reduce open redirect risk. This audit pass enforces operator `base_url` or `settings.return_url_allowlist`.
- Launch URLs are returned only in the session-create response. Persisted sessions keep the launch token hash, and signed session list/detail APIs omit token-bearing launch fields and recursively redact session metadata.

## P1

- B2B HMAC replay protection and rate limiting must use Redis/shared cache across API nodes. `php artisan b2b:release-check --production` now fails when production shared state is not Redis.
- API key rotation/revocation now has audited CLI coverage with mandatory actor/reason, permission, step-up confirmation, explicit scopes, throttled successful-use audit events, and per-key app-level rate limits. Web credential rotation/revocation routes are protected by backend auth, 2FA middleware, B2B RBAC, session-bound confirmation, and current-password verification.
- Settlement export requires a signed API key with the dedicated `reports.export` scope; production release checks fail if the default API-key scope set grants `reports.export` or `*`.
- Raw wallet payload persistence and status/report output now redact sensitive fields and known token patterns recursively. Before broad admin exposure, run `php artisan b2b:payload-redaction-audit` and keep the clean dry-run artifact; if findings exist, run the approved `--write` remediation and rerun the dry-run.
- B2B privileged CLI actions now use a dedicated deny-by-default permission model, and the B2B backend dashboard is protected by B2B web RBAC middleware. Mutating B2B web screens use authenticated B2B RBAC plus session-bound web step-up with 2FA middleware and current-password verification before the privileged action session marker is accepted.
- Session cookies now default to HTTP-only and SameSite=Lax, default to secure cookies in `APP_ENV=production`, and are enforced by the production release gate.
- Backend and frontend login throttling is now forcibly enabled in production with a release-gated maximum attempt budget, even if mutable admin settings are weaker.
- Password creation, reset, profile/admin updates, shop bootstrap users, API agent signup, SMS invites, and bulk player generation now use a centralized production password policy with release-gate coverage. The default floor is 12 characters, mixed case, numbers, no whitespace, bcrypt-safe maximum length, and cryptographically random temporary credentials.
- Password resets and password changes now emit a credential-change event that revokes database-backed sessions, remember tokens, and local API tokens. Production release checks require `SESSION_DRIVER=database`, the `sessions` and `api_tokens` runtime migrations, and listener wiring so stale sessions/tokens cannot survive a password change.
- Production deployment templates, runbook, queue runtime drill, and release evidence checker are now release-gate checked, but host secret storage, off-host backups, TLS/domain setup, target-environment evidence, and rollback rehearsal must be verified per environment.
- Observability release artifacts now include secret-scanned Prometheus scrape/rule smoke tooling, local and external structured-log shipping validation, wallet/provider correlation validation, Alertmanager notification smoke tooling, and downstream receiver confirmation tooling, but target-environment scrape delivery, rule loading, external log delivery, correlation samples, and downstream receiver confirmation still must be verified with redacted evidence before launch.
- Node/WebSocket now has a private package manifest, pnpm lockfile without the deprecated `request` dependency, green `pnpm audit --prod`, syntax check, Nginx proxy template, production socket config validator, public proxy smoke tooling, origin allowlist, optional token env, session-cookie handshake validation, idle heartbeat, JSON lifecycle logs without cookie/body output, and release-gate coverage. Final public-domain proxy/auth smoke execution is still required before launch.
- Composer dependency audit and WebSocket pnpm audit now run inside `b2b:release-check --production` and are green on the PHP 8.3 / Laravel 12 and `PTWebSocket` lockfiles. The major PHP upgrade moved `laravel/framework` to `v12.62.0`, replaced the legacy SwiftMailer dependency with Symfony Mailer, and removed the remaining Laravel framework advisories and abandoned-package finding from the production audit. Legacy wrappers (`proengsoft/laravel-jsvalidation`, `pragmarx/google2fa-laravel`, `tymon/jwt-auth`, `jeremykenedy/laravel-roles`, and `laravelcollective/html`) remain replaced by local compatibility layers; the roles removal also dropped transitive `eklundkristoffer/seedster` and `laravel/helpers`, with the used helper functions now provided locally. Keep Composer and pnpm audits as hard production gates so new advisories cannot be promoted accidentally.
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
- Production domains, SSL, production secrets, infrastructure, verified backup storage, load-test environment, and a redacted `release-evidence.json` package that passes `b2b:evidence-check --production`.
