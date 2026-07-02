# BBB B2B Aggregator API - MVP

Base URL in a standard Laravel install:

```text
/api/b2b/v1
```

## Authentication

Machine-readable artifacts:

- `docs/b2b/openapi.json`
- `docs/b2b/postman_collection.json`
- `docs/b2b/GAME_ASSIGNMENTS.md`

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
GET  /api/b2b/v1/readiness
GET  /api/b2b/v1/metrics
GET  /api/b2b/v1/operator/me
GET  /api/b2b/v1/portal
GET  /api/b2b/v1/portal/overview
GET  /api/b2b/v1/portal/credentials
GET  /api/b2b/v1/portal/games
GET  /api/b2b/v1/portal/sessions
GET  /api/b2b/v1/portal/transactions
GET  /api/b2b/v1/portal/settlements
GET  /api/b2b/v1/portal/cases
GET  /api/b2b/v1/portal/callbacks
GET  /api/b2b/v1/portal/reports
GET  /api/b2b/v1/portal/support
GET  /api/b2b/v1/portal/docs
GET  /api/b2b/v1/games
POST /api/b2b/v1/games/launch
GET  /api/b2b/v1/sessions
GET  /api/b2b/v1/sessions/{session_uid}
POST /api/b2b/v1/sessions/{session_uid}/close
POST /api/b2b/v1/wallet/balance
POST /api/b2b/v1/wallet/bet
POST /api/b2b/v1/wallet/win
POST /api/b2b/v1/wallet/refund
POST /api/b2b/v1/wallet/rollback
GET  /api/b2b/v1/wallet/transactions/{transaction_uid}/status
GET  /api/b2b/v1/wallet/transactions/{transaction_uid}/attempts
GET  /api/b2b/v1/reports/summary
GET  /api/b2b/v1/reports/transactions
GET  /api/b2b/v1/reports/ggr
GET  /api/b2b/v1/reports/settlements
POST /api/b2b/v1/reports/settlements/export
GET  /api/b2b/v1/reports/settlements/{settlement_uid}
GET  /api/b2b/v1/reports/reconciliation
GET  /api/b2b/v1/reports/transactions/{transaction_uid}
```

## Response envelope

Successful JSON responses use:

```json
{
  "success": true,
  "status": "success",
  "request_id": "uuid-or-client-request-id",
  "data": {},
  "meta": {}
}
```

Error JSON responses use:

```json
{
  "success": false,
  "status": "error",
  "request_id": "uuid-or-client-request-id",
  "error": {
    "code": "VALIDATION_FAILED",
    "message": "Request validation failed.",
    "details": {}
  }
}
```

`meta` and `error.details` are present only when relevant. Every B2B JSON response also returns `X-Request-Id`.

`GET /api/b2b/v1/metrics` returns Prometheus text format instead of the JSON envelope. It contains aggregate counts and latency gauges only, without operator identifiers, raw payloads, or secrets. Restrict scraping at the edge in production.

## Operator portal

`GET /api/b2b/v1/portal` is a signed, read-only HTML operator portal page. It uses the same tenant-scoped data as the overview endpoint and intentionally omits API key secrets, raw wallet request/response payloads, and foreign-operator records.

`GET /api/b2b/v1/portal/overview` is a signed, read-only bootstrap endpoint for an operator-facing portal. It returns tenant-scoped operator/API-key profile data, wallet and session counters, credential/game-assignment/settlement/reconciliation summaries, recent sessions, recent wallet transactions, and links to the underlying B2B API routes. It intentionally omits API key secrets, raw wallet request/response payloads, and foreign-operator records.

Signed read-only HTML workflow pages are available at `/portal/credentials`, `/portal/games`, `/portal/sessions`, `/portal/transactions`, `/portal/settlements`, `/portal/cases`, `/portal/callbacks`, `/portal/reports`, `/portal/support`, and `/portal/docs`. They use the same HMAC authentication and tenant-scoped redacted data as `/portal/overview`. The callbacks page shows sanitized callback settings, status buckets, and recent callback attempts without query strings or raw payload bodies. The reports page links to the signed reporting endpoints and summarizes successful wallet amounts for the selected period. The support page shows tenant-scoped health incidents and open reconciliation cases without exposing foreign operators or raw payloads.

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

Launch checks the signed operator's game availability before creating a session. Dedicated `b2b_operator_game_assignments` rows are enforced first and can allow, block, or limit games per provider, currency, country, and mode. If an operator has any active `allowed` assignment, unassigned games are denied by default. Without assignments, Goldsvet/internal fallback games must belong to the operator's mapped `shop_id` and be visible; legacy `settings.enabled_games` and `settings.disabled_games` still apply.

## Session close example

```json
{
  "reason": "player_logout"
}
```

Session list, detail, and close endpoints are scoped to the signed operator. Detail and close accept a `session_uid`; numeric database IDs are accepted only when the ID belongs to the signed operator. Closing a session runs through the provider close contract, stores `close_reason` when the column is present, and is idempotent for already closed sessions.

## Wallet event example

```json
{
  "player_id": "player_123",
  "game_id": "bookofdead",
  "provider": "goldsvet_internal",
  "session_id": "sess_xxx",
  "round_id": "round_001",
  "transaction_id": "bet_001",
  "amount": "10.00000000",
  "currency": "USD"
}
```

This MVP stores every wallet event in `b2b_wallet_transactions` and forwards the payload to `b2b_operators.wallet_callback_url` when configured.

Wallet mutation requests with `session_id` must reference an active session owned by the signed operator, matching the requested game and currency. A foreign or stale session is rejected before ledger creation or callback delivery.

Wallet status lookup returns the current status, recent callback attempts, transition history, open reconciliation items, recent manual actions, and suggested operational next actions for the signed operator only.

## Settlement export example

```json
{
  "from": "2026-06-01",
  "to": "2026-06-30",
  "currency": "USD",
  "format": "csv"
}
```

`POST /reports/settlements/export` creates or returns a deterministic operator-scoped settlement snapshot for one period/currency. The export uses successful wallet transactions only, freezes totals in `b2b_settlements`, stores a SHA-256 hash, writes `settlement.exported` to the B2B audit log, and returns the export content inline for the MVP. Internal finance approval is handled by privileged artisan commands documented in `docs/b2b/B2B_RBAC.md`.

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
