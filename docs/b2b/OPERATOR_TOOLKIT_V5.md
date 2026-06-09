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
php artisan b2b:make-operator "Test Operator" --shop_id=1 --currency=USD --max_rps=50 --wallet_timeout_ms=3000
```

The command prints:

```text
X-Operator-Id
X-Api-Key
Secret
```

Save the secret immediately. It is stored encrypted and will not be printed again.

Sync games from the existing `games` table into the B2B catalog:

```bash
php artisan b2b:sync-games --shop_id=1
```

Generate HMAC headers and a curl example:

```bash
php artisan b2b:show-hmac op_xxx key_xxx secret_xxx GET /api/b2b/v1/operator/me
```

Show B2B summary:

```bash
php artisan b2b:health
```

## Recommended smoke test

```bash
php artisan migrate
php artisan b2b:make-operator "Test Operator" --shop_id=1 --currency=USD
php artisan b2b:sync-games --shop_id=1 --limit=20
php artisan b2b:health
php artisan route:list | grep b2b
```

On Windows Git Bash, if `grep` is unavailable:

```bash
php artisan route:list | findstr b2b
```

This package does not delete `.env`, `vendor`, SQL dumps, composer files or any local project files.
