# BBB B2B Aggregator API - MVP

Base URL in a standard Laravel install:

```text
/api/b2b/v1
```

## Authentication

Every protected request must include:

```text
X-Operator-Id: op_demo
X-Api-Key: key_public_id
X-Timestamp: 1715450000
X-Nonce: random-string
X-Body-Hash: sha256-raw-body
X-Signature: hmac-sha256
```

Signature payload:

```text
METHOD
/path
canonical_query
body_hash
timestamp
nonce
```

Signature value:

```php
hash_hmac('sha256', $canonicalRequest, $operatorSecret)
```

Timestamp skew is limited by `B2B_HMAC_REPLAY_WINDOW_SECONDS` (300 seconds by default). Nonces are cached for the same window to reduce replay risk. See `docs/api/HMAC_AUTHENTICATION.md` for the exact canonicalization rules.

## Endpoints

```text
GET  /api/b2b/v1/health
GET  /api/b2b/v1/games
POST /api/b2b/v1/games/launch
POST /api/b2b/v1/wallet/balance
POST /api/b2b/v1/wallet/bet
POST /api/b2b/v1/wallet/win
POST /api/b2b/v1/wallet/refund
POST /api/b2b/v1/wallet/rollback
GET  /api/b2b/v1/reports/transactions
GET  /api/b2b/v1/reports/ggr
```

## Launch example

```json
{
  "player_id": "player_123",
  "game_id": "bookofdead",
  "currency": "USD",
  "language": "en",
  "country": "BR",
  "mode": "real",
  "return_url": "https://operator.example/casino"
}
```

Response:

```json
{
  "success": true,
  "data": {
    "session_id": "sess_xxx",
    "game_id": "bookofdead",
    "provider": "goldsvet_internal",
    "launch_url": "https://your-domain.test/launcher/bookofdead/token",
    "expires_at": "2026-05-11T12:00:00+00:00"
  }
}
```

## Wallet event example

```json
{
  "player_id": "player_123",
  "game_id": "bookofdead",
  "provider": "goldsvet_internal",
  "session_id": "sess_xxx",
  "round_id": "round_001",
  "transaction_id": "bet_001",
  "amount": 10.00,
  "currency": "USD"
}
```

This MVP stores every wallet event in `b2b_wallet_transactions` and forwards the payload to `b2b_operators.wallet_callback_url` when configured.

## Create demo operator manually

Run in `php artisan tinker` after migrations:

```php
use Illuminate\Support\Facades\Crypt;
use VanguardLTE\B2B\Models\B2BOperator;
use VanguardLTE\B2B\Models\B2BOperatorApiKey;

$op = B2BOperator::create([
    'operator_uid' => 'op_demo',
    'name' => 'Demo Operator',
    'shop_id' => 1,
    'status' => 'active',
    'default_currency' => 'USD',
    'wallet_callback_url' => null,
]);

$secret = 'change-me-demo-secret';
B2BOperatorApiKey::create([
    'operator_id' => $op->id,
    'key_id' => 'demo_key',
    'secret_encrypted' => Crypt::encryptString($secret),
    'status' => 'active',
]);
```
