# B2B Operator Toolkit v5

This update adds operator provisioning and local smoke-test helpers.

## New API endpoint

```http
GET /api/b2b/v1/operator/me
```

This endpoint requires the normal B2B HMAC headers and returns operator status, limits, circuit-breaker fields and counters.

## New Artisan commands

Create a test operator:

```bash
php artisan b2b:make-operator "Test Operator" --shop_id=1 --currency=USD --max_rps=50 --api_key_max_rps=25 --wallet_timeout_ms=3000 --actor=integration_manager --reason="Onboarding ticket B2B-123" --permission=b2b.operators.create --confirm=CREATE_OPERATOR
```

Backoffice web workflow: `/backend/b2b/operators` updates non-secret operator settings and records suspend/resume decisions through B2B RBAC plus session-bound web step-up.

The command prints:

```text
X-Operator-Id
X-Api-Key
Secret
Scopes
```

Save the secret immediately. It is stored encrypted and will not be printed again. Default key scopes come from `B2B_API_KEY_DEFAULT_SCOPES` and intentionally omit `reports.export`; issue a separate scoped key when finance settlement export is needed.

Sync games from the existing `games` table into the B2B catalog:

```bash
php artisan b2b:sync-games --shop_id=1
php artisan b2b:sync-games --shop_id=1 --soft-disable-missing
```

Generate HMAC headers and a curl example:

```bash
php artisan b2b:show-hmac op_xxx key_xxx secret_xxx GET /api/b2b/v1/operator/me
```

Rotate an operator API key and audit who did it and why:

Backoffice web workflow: `/backend/b2b/credentials` rotates and revokes keys through B2B RBAC plus session-bound web step-up. The generated plaintext secret is shown once and is not stored in plaintext.

```bash
php artisan b2b:rotate-api-key op_xxx --max-rps=25 --scopes=operator.read,portal.read,reports.read --actor=security_user --reason="Quarterly API key rotation" --permission=b2b.credentials.rotate --confirm=ROTATE_API_KEY --revoke-existing
```

The command prints the new `X-Api-Key`, one-time secret, and scopes. Existing active keys are disabled only when `--revoke-existing` is passed. If `--max-rps` is omitted, the key inherits the default per-key limit, which falls back to the operator `max_rps`.

Revoke one API key and keep an audit event:

```bash
php artisan b2b:revoke-api-key op_xxx key_xxx --actor=security_user --reason="Partner requested revocation" --permission=b2b.credentials.revoke --confirm=REVOKE_API_KEY
```

Revocation stores `disabled` in `b2b_operator_api_keys.status`; the HMAC middleware accepts only `active` keys.

Show B2B summary:

```bash
php artisan b2b:health
```

## Recommended smoke test

```bash
php artisan migrate
php artisan b2b:make-operator "Test Operator" --shop_id=1 --currency=USD --actor=integration_manager --reason="Initial smoke provisioning" --permission=b2b.operators.create --confirm=CREATE_OPERATOR
php artisan b2b:rotate-api-key op_xxx --scopes=operator.read,portal.read,games.read,games.launch,sessions.read,sessions.close,wallet.balance,wallet.status,wallet.mutate,reports.read --actor=security_user --reason="Initial smoke rotation" --permission=b2b.credentials.rotate --confirm=ROTATE_API_KEY
php artisan b2b:sync-games --shop_id=1 --limit=20
php artisan b2b:health
php artisan route:list | grep b2b
```

On Windows Git Bash, if `grep` is unavailable:

```bash
php artisan route:list | findstr b2b
```

This package does not delete `.env`, `vendor`, SQL dumps, composer files or any local project files.
