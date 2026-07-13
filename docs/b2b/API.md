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

Signed operators can also download the current JSON artifacts from the portal docs surface:

- `GET /api/b2b/v1/portal/docs/openapi.json`
- `GET /api/b2b/v1/portal/docs/postman_collection.json`

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

Timestamp skew is limited by `B2B_HMAC_REPLAY_WINDOW_SECONDS` (300 seconds by default). Nonces are cached for the same window to reduce replay risk. See `docs/api/HMAC_AUTHENTICATION.md` for the exact canonicalization rules and reproducible PHP, Node.js, and cURL signing examples.

API keys are scoped. Public health/readiness/metrics do not require HMAC, while signed routes require the matching key scope: `operator.read`, `portal.read`, `support.write`, `games.read`, `games.launch`, `sessions.read`, `sessions.close`, `wallet.balance`, `wallet.status`, `wallet.mutate`, `reports.read`, and, for settlement export only, `reports.export`. Sandbox operator tools use `sandbox.wallet.read` or `sandbox.wallet.mutate`.

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
GET  /api/b2b/v1/portal/games/{game_uid}
GET  /api/b2b/v1/portal/sessions
GET  /api/b2b/v1/portal/sessions/{session_uid}
GET  /api/b2b/v1/portal/transactions
GET  /api/b2b/v1/portal/transactions/{transaction_uid}
GET  /api/b2b/v1/portal/settlements
GET  /api/b2b/v1/portal/settlements/{settlement_uid}
GET  /api/b2b/v1/portal/cases
GET  /api/b2b/v1/portal/callbacks
GET  /api/b2b/v1/portal/diagnostics
GET  /api/b2b/v1/portal/diagnostics/{request_uid}
GET  /api/b2b/v1/portal/reports
GET  /api/b2b/v1/portal/support
GET  /api/b2b/v1/portal/logs
GET  /api/b2b/v1/portal/support/cases/{transaction_uid}
GET  /api/b2b/v1/portal/support/cases/{transaction_uid}/thread
POST /api/b2b/v1/portal/support/cases/{transaction_uid}/comments
POST /api/b2b/v1/portal/support/tickets
GET  /api/b2b/v1/portal/support/tickets/{ticket_uid}
GET  /api/b2b/v1/portal/support/tickets/{ticket_uid}/thread
POST /api/b2b/v1/portal/support/tickets/{ticket_uid}/comments
POST /api/b2b/v1/portal/support/tickets/{ticket_uid}/close
POST /api/b2b/v1/portal/support/tickets/{ticket_uid}/reopen
GET  /api/b2b/v1/portal/docs
GET  /api/b2b/v1/portal/docs/openapi.json
GET  /api/b2b/v1/portal/docs/postman_collection.json
GET  /api/b2b/v1/games
GET  /api/b2b/v1/games/{game_uid}
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

`GET /api/b2b/v1/readiness` checks database connectivity, critical B2B tables/columns, cache stores including the game-catalog cache, queue configuration, failed-job storage, scheduler heartbeat freshness, provider adapter health, storage writability, and production release configuration when running in production mode. Provider health verifies the local adapter/source surface, but it does not replace real provider credentials, wallet-contract certification, or legal approval evidence.

`GET /api/b2b/v1/metrics` returns Prometheus text format instead of the JSON envelope. It contains aggregate counts, latency gauges, scheduler/queue health, and `bbb_b2b_provider_health_up` provider-adapter health gauges only, without operator identifiers, raw payloads, or secrets. Restrict scraping at the edge in production.

## Operator portal

`GET /api/b2b/v1/portal` is a signed, read-only HTML operator portal page. It uses the same tenant-scoped data as the overview endpoint, including public API-key scope names for the current/recent keys, and intentionally omits API key secrets, raw wallet request/response payloads, URL query strings, and foreign-operator records. It accepts `from`, `to`, and `limit` (1-50) for the period-scoped summaries; invalid filters or inverted periods return `VALIDATION_FAILED`.

`GET /api/b2b/v1/portal/overview` is a signed, read-only bootstrap endpoint for an operator-facing portal. It returns tenant-scoped operator/API-key profile data, current/recent API-key scope names, wallet and session counters, credential/game-assignment/settlement/reconciliation summaries, provider launch diagnostics summaries, sanitized provider health summary, open and recent reconciliation cases with state-aware support-case comment endpoint paths, recent sessions with portal detail endpoint paths, recent wallet transactions with portal detail endpoint paths, recent settlements with portal detail endpoint paths, recent support tickets with message counts, latest redacted message summaries, JSON detail endpoint paths, HTML thread page paths, state-aware comment/close/reopen endpoint paths for support tickets, and recent redacted API/audit events. Operator base/callback URLs are returned as scheme/host/port/path only, without query strings. It accepts `from`, `to`, and `limit` (1-50) and intentionally omits API key secrets, raw wallet/provider request/response payloads, and foreign-operator records.

Signed read-only HTML workflow pages are available at `/portal/credentials`, `/portal/games`, `/portal/sessions`, `/portal/transactions`, `/portal/settlements`, `/portal/cases`, `/portal/callbacks`, `/portal/diagnostics`, `/portal/reports`, `/portal/support`, `/portal/logs`, and `/portal/docs`. They use the same HMAC authentication, `from`/`to`/`limit` validation, and tenant-scoped redacted data as `/portal/overview`. The credentials page shows key IDs, statuses, rate limits, and public scope names only. The games, sessions, transactions, settlements, and diagnostics pages include tenant-scoped detail endpoint paths. The callbacks page shows sanitized callback settings, status buckets, and recent callback attempts without query strings or raw payload bodies. The diagnostics page shows provider request status/action/provider buckets, recent provider launch/close attempts, and failed launch sessions without launch tokens, provider URLs, or raw payload bodies. The reports page links to the signed reporting endpoints and summarizes successful wallet amounts for the selected period. The cases/support pages show open and recent support cases plus tenant-scoped support case/ticket JSON detail endpoint paths, HTML thread page paths, and comment endpoint paths for commentable cases, without exposing foreign operators or raw payloads. The logs page shows the signed operator's own recent audit/API events with redacted reason and metadata summaries. The docs page links to signed downloadable OpenAPI and Postman JSON artifacts.

`GET /api/b2b/v1/portal/docs/openapi.json` and `GET /api/b2b/v1/portal/docs/postman_collection.json` download the static OpenAPI and Postman JSON artifacts through the same HMAC authentication and `portal.read` scope as `/portal/docs`.

`GET /api/b2b/v1/portal/games/{game_uid}` renders a signed, tenant-scoped game drilldown page. It accepts `limit` (1-50) for recent rows and shows catalog summary, operator assignment, real/demo availability, successful wallet amounts, recent sessions, and recent transaction links. It does not expose launch tokens, raw wallet payload bodies, URL query secrets, or foreign-operator rows.

`GET /api/b2b/v1/portal/sessions/{session_uid}` renders the signed operator's own session drilldown as HTML. It accepts `limit` (1-50, default 20), validates `session_uid` up to 191 characters, returns `SESSION_NOT_FOUND` for foreign or missing sessions, and shows session summary, successful wallet amounts, and related transaction links without launch tokens, launch URLs, raw wallet payloads, or foreign-operator records.

`GET /api/b2b/v1/portal/transactions/{transaction_uid}` renders the signed operator's own transaction drilldown as HTML. It accepts `limit` (1-50, default 20), validates `transaction_uid` up to 191 characters, returns `TRANSACTION_NOT_FOUND` for foreign or missing transactions, and shows transaction summary, status transitions, callback attempts/logs, reconciliation items, and manual actions without raw request/response bodies.

`GET /api/b2b/v1/portal/settlements/{settlement_uid}` renders the signed operator's own settlement drilldown as HTML. It validates `settlement_uid` up to 80 characters, returns `SETTLEMENT_NOT_FOUND` for foreign or missing settlements, and shows settlement summary, totals, transaction breakdown, approval trail, export metadata, and the JSON report detail path without export content, raw payload bodies, or foreign-operator records.

`GET /api/b2b/v1/portal/diagnostics/{request_uid}` renders the signed operator's own provider request diagnostic as HTML. It validates `request_uid` up to 191 characters, returns `PROVIDER_REQUEST_NOT_FOUND` for foreign or missing provider requests, and shows provider, game, session, action, status, duration, redacted error summary, redacted request/response summaries, and related portal session links without launch URLs, launch tokens, raw provider payloads, or foreign-operator records.

`GET /api/b2b/v1/portal/support/cases/{transaction_uid}` returns the signed operator's own reconciliation support case with a bounded chronological `comments` list, separate `latest_comment`, JSON detail/thread endpoint paths, and a `comment_endpoint` only while the case is `open` or `in_progress`. It accepts `limit` (1-100, default 50), validates `transaction_uid` up to 191 characters, redacts comment text and external references before output, and returns `CASE_NOT_FOUND` for foreign or missing cases. Internal backoffice step-up and permission metadata is not returned.

`GET /api/b2b/v1/portal/support/cases/{transaction_uid}/thread` renders the same signed, tenant-scoped, redacted support case readback as an HTML thread page for operator portal workflows. It accepts the same `limit` and `transaction_uid` validation as the JSON detail endpoint.

`POST /api/b2b/v1/portal/support/cases/{transaction_uid}/comments` appends an operator follow-up comment to the signed operator's own open or in-progress reconciliation case. The `transaction_uid` path value is validated up to 191 characters. The endpoint redacts sensitive text before persistence, writes `case.operator_commented` to the B2B audit trail, and does not change wallet transaction state, settlement state, or case assignment.

`POST /api/b2b/v1/portal/support/tickets` creates an operator-owned support ticket with `subject`, `message`, optional `priority` (`low`, `normal`, `high`, `urgent`), `category`, and `external_reference`. The ticket subject, message, context, and audit metadata are redacted before storage and the action writes `support_ticket.created`.

`GET /api/b2b/v1/portal/support/tickets/{ticket_uid}` returns the signed operator's own support ticket summary and a bounded chronological `messages` list. It accepts `limit` (1-100, default 50), validates `ticket_uid` up to 80 characters, redacts legacy message text/metadata before output, and returns `SUPPORT_TICKET_NOT_FOUND` for foreign or missing tickets.

`GET /api/b2b/v1/portal/support/tickets/{ticket_uid}/thread` renders the same signed, tenant-scoped, redacted support ticket readback as an HTML thread page for operator portal workflows. It accepts the same `limit` and `ticket_uid` validation as the JSON detail endpoint.

`POST /api/b2b/v1/portal/support/tickets/{ticket_uid}/comments` appends a redacted operator comment to the signed operator's own open or in-progress support ticket, moves it to `in_progress`, and writes `support_ticket.operator_commented`. The `ticket_uid` path value is validated up to 80 characters.

`POST /api/b2b/v1/portal/support/tickets/{ticket_uid}/close` closes the signed operator's own open or in-progress support ticket with a required redacted `reason` and writes `support_ticket.closed`. The `ticket_uid` path value is validated up to 80 characters.

`POST /api/b2b/v1/portal/support/tickets/{ticket_uid}/reopen` reopens the signed operator's own closed support ticket with a required redacted `reason`, clears `closed_at`, returns it to `open`, and writes `support_ticket.reopened`. The `ticket_uid` path value is validated up to 80 characters.

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
    "launch_url": "https://your-domain.test/launcher/bookofdead/one-time-token",
    "expires_at": "2026-05-11T12:00:00+00:00"
  }
}
```

Launch checks the signed operator's game availability before creating a session. Dedicated `b2b_operator_game_assignments` rows are enforced first and can allow, block, or limit games per provider, currency, country, and mode. If an operator has any active `allowed` assignment, unassigned games are denied by default. Without assignments, Goldsvet/internal fallback games must belong to the operator's mapped `shop_id` and be visible; legacy `settings.enabled_games` and `settings.disabled_games` still apply. The public launch URL is returned only in the create response; stored sessions keep only the launch token hash and session list/detail responses omit `token_hash`, `launch_url`, `legacy_launch_token`, and `legacy_launch_url`.

`GET /api/b2b/v1/games` returns a bounded signed-operator catalog. It accepts `limit` (1-500, default 100), `provider`, `category`, `platform`, `search`, `currency`, `country`, `mode` (`real` or `demo`, default `real`), and `sort` (`title`, `-title`, `provider`, `-provider`, `category`, `-category`, `game_uid`, `-game_uid`). Catalog rows include `game_uid`, `provider_game_id`, `canonical_game_id`, `provider`, `slug`, `title`, `category`, `platform`, image URL, launch config, mode/currency/country support, status, and metadata. The response keeps the game list in `data` and adds `meta.limit`, `meta.count`, `meta.available_count`, `meta.filters`, `meta.sort`, and `meta.source`. Only `active` catalog games are listed. The index response is cached per operator/filter set via `B2B_GAME_CATALOG_CACHE_STORE`; production release checks require the cache to be enabled on Redis, and catalog sync or assignment changes invalidate the catalog cache version.

`GET /api/b2b/v1/games/{game_uid}` returns the same redacted catalog shape for one signed-operator-owned game. It accepts optional `currency`, `country`, and `mode` query filters and returns `GAME_NOT_AVAILABLE` for foreign-shop legacy games, blocked assignments, unassigned games when assignments are restrictive, disabled catalog rows, or unsupported currency/country/mode combinations. Catalog rows with `status=maintenance` return `GAME_UNDER_MAINTENANCE` with HTTP 503; launch uses the same availability decision and does not create a session while maintenance is active.

## Session close example

```json
{
  "reason": "player_logout"
}
```

Session list, detail, and close endpoints are scoped to the signed operator. `GET /api/b2b/v1/sessions` accepts `limit` (1-1000, default 100), `status`, `player_id`, `game_id`, and `sort` (`created_at`, `-created_at`, `updated_at`, `-updated_at`, `expires_at`, `-expires_at`, `status`, `-status`, `game_id`, `-game_id`, `session_uid`, `-session_uid`). The list response includes `meta.limit`, `meta.count`, `meta.matched_count`, `meta.filters`, and `meta.sort`. Session metadata is redacted recursively before output, and token-bearing launch fields are never returned by list/detail. Detail and close accept a `session_uid` up to 191 characters; numeric database IDs are accepted only when the ID belongs to the signed operator. Invalid identifiers return `VALIDATION_FAILED`. Closing a session runs through the provider close contract, stores `close_reason` when the column is present, and is idempotent for already closed sessions.

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

Wallet status lookup returns the current status, recent callback attempts, transition history, open reconciliation items, recent manual actions, and suggested operational next actions for the signed operator only. Overlong transaction UIDs return `VALIDATION_FAILED` before lookup.

`GET /api/b2b/v1/wallet/transactions/{transaction_uid}/attempts` returns recent callback attempts for the signed operator only. It accepts `limit` (1-100, default 100); invalid limits or overlong transaction UIDs return `VALIDATION_FAILED`.

`GET /api/b2b/v1/reports/summary` and `GET /api/b2b/v1/reports/ggr` accept `from`, `to`, `status`, `type`, `player_id`, `game_id`, `round_id`, and `currency`. Invalid filters or inverted periods return `VALIDATION_FAILED` instead of silently falling back to defaults.

`GET /api/b2b/v1/reports/transactions` returns a bounded tenant-scoped transaction list. It accepts `from`, `to`, `limit` (1-1000, default 100), `status`, `type`, `player_id`, `game_id`, `round_id`, `currency`, and `sort` (`created_at`, `-created_at`, `amount`, `-amount`, `type`, `-type`, `status`, `-status`, `currency`, `-currency`, `game_id`, `-game_id`, `transaction_uid`, `-transaction_uid`). Invalid filters or inverted periods return `VALIDATION_FAILED` instead of silently falling back to defaults.

`GET /api/b2b/v1/reports/settlements` returns a bounded tenant-scoped settlement list. It accepts `from`, `to`, `limit` (1-1000, default 100), `status`, `currency`, and `sort` (`created_at`, `-created_at`, `period_start`, `-period_start`, `period_end`, `-period_end`, `status`, `-status`, `currency`, `-currency`, `net_amount`, `-net_amount`, `settlement_uid`, `-settlement_uid`). Invalid filters, invalid detail identifiers, or inverted periods return `VALIDATION_FAILED`.

`GET /api/b2b/v1/reports/reconciliation` accepts `from`, `to`, `limit` (1-100), `state` (`open`, `in_progress`, `resolved`), `reason`, `priority` (`low`, `normal`, `medium`, `high`, `urgent`), `currency`, `game_id`, and `round_id`. Invalid filters or inverted periods return `VALIDATION_FAILED`. Transaction report detail accepts transaction UIDs up to 191 characters; overlong identifiers return `VALIDATION_FAILED`.

## Settlement export example

```json
{
  "from": "2026-06-01",
  "to": "2026-06-30",
  "currency": "USD",
  "format": "csv"
}
```

`POST /reports/settlements/export` creates or returns a deterministic operator-scoped settlement snapshot for one period/currency. The signed API key must include the dedicated `reports.export` scope; default newly provisioned keys intentionally omit it. The export uses successful wallet transactions only, freezes totals in `b2b_settlements`, stores a SHA-256 hash, writes `settlement.exported` to the B2B audit log, and returns the export content inline for the MVP. Internal finance approval is handled by privileged artisan commands documented in `docs/b2b/B2B_RBAC.md`.

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
