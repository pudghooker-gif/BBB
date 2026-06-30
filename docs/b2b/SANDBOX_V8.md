# B2B Sandbox v8

This patch adds a local sandbox wallet so the B2B flow can be tested without a real external casino operator.

## What it adds

- `/api/b2b/sandbox/health`
- `/api/b2b/sandbox/wallet`
- `/api/b2b/sandbox/wallet/{action}` where action is `balance`, `bet`, `win`, `refund`, `rollback`, `credit`, or `debit`
- protected operator tools:
  - `GET /api/b2b/v1/sandbox/wallet/{player_id}`
  - `GET /api/b2b/v1/sandbox/wallet/{player_id}/entries`
  - `POST /api/b2b/v1/sandbox/wallet/{player_id}/credit`
  - `POST /api/b2b/v1/sandbox/wallet/{player_id}/debit`
- commands:
  - `php artisan b2b:sandbox-health`
  - `php artisan b2b:sandbox-operator`
  - `php artisan b2b:sandbox-wallet`

## Setup

```bash
php artisan migrate
php artisan b2b:sandbox-health
php artisan b2b:sandbox-operator SandboxOperator --shop_id=1 --currency=USD --balance=1000 --player_id=demo_player --app_url=http://localhost
```

The command prints B2B API credentials and an example signed curl request using the current canonical HMAC headers, including `X-Body-Hash`.

## How it works

The created operator gets a `wallet_callback_url` like:

```text
http://localhost/api/b2b/sandbox/wallet?operator_uid=op_sandbox_xxxxxxxx
```

When `/api/b2b/v1/wallet/bet` or `/api/b2b/v1/wallet/win` is called, the normal B2B wallet layer calls this sandbox wallet endpoint. The sandbox wallet updates `b2b_sandbox_wallets` and writes entries to `b2b_sandbox_wallet_entries`.

## Safety

The sandbox is disabled by default. Keep it disabled in production:

```env
B2B_SANDBOX_ENABLED=false
```

For isolated local testing you may explicitly enable it:

```env
B2B_SANDBOX_ENABLED=true
```

## Typical local test

1. Run migrations.
2. Create sandbox operator.
3. Sync games:

```bash
php artisan b2b:sync-games --shop_id=1 --limit=20
```

4. Use the credentials printed by `b2b:sandbox-operator` to call:

```http
GET  /api/b2b/v1/games
POST /api/b2b/v1/games/launch
POST /api/b2b/v1/wallet/balance
POST /api/b2b/v1/wallet/bet
POST /api/b2b/v1/wallet/win
GET  /api/b2b/v1/reports/summary
```
