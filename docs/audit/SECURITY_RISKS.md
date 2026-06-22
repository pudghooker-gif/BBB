# Security Risks

Date: 2026-06-22

## P0

- Secret-bearing files are present in the repository working tree: `.env`, `.env_old`, `PTWebSocket/ssl/key.key`, `PTWebSocket/ssl/crt.crt`, and `totalbet365.sql`. Contents were not copied or inspected in this report.
- Production release artifacts must exclude local env files, SQL dumps, backups, development logs, and local TLS keys.
- If any secret-bearing file has been committed to public history, rotate the affected secrets and treat production launch as blocked until rotation is complete.
- Wallet callback URLs require SSRF protection. This audit pass adds scheme, localhost, private IP, reserved IP, and DNS resolution checks outside local/testing environments.
- Launch `return_url` requires an operator allowlist to reduce open redirect risk. This audit pass enforces operator `base_url` or `settings.return_url_allowlist`.

## P1

- B2B HMAC replay protection and rate limiting must use Redis/shared cache across API nodes. `php artisan b2b:release-check --production` now fails when production shared state is not Redis.
- API key rotation exists only as a foundation; production UX and audit events for rotation/revocation are incomplete.
- Raw wallet payload storage needs systematic redaction before broad admin/reporting exposure.
- Admin RBAC is inherited from existing app roles and is not yet a dedicated B2B deny-by-default permission model.
- Composer dependency audit could not be run with Composer 2.0.13.
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
