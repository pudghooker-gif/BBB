# B2B Wallet v7

This update adds a more resilient wallet layer for the B2B aggregator.

## Added

- Wallet callback attempt logging: `b2b_wallet_transaction_attempts`
- Idempotency key and request hash support on wallet transactions
- Operator circuit breaker fields
- Wallet timeout fields
- Retry command for failed/time-out callbacks
- Append-only wallet state transition log: `b2b_wallet_transaction_transitions`
- Configurable retry budget before `dead_letter`: `B2B_WALLET_RETRY_MAX_ATTEMPTS`
- Stale session close command
- API endpoint to check wallet health
- API endpoint to inspect callback attempts for one transaction

## New API endpoints

```http
GET /api/b2b/v1/wallet/health
GET /api/b2b/v1/wallet/transactions/{transaction_uid}/attempts
```

Both endpoints use B2B HMAC middleware.

## New artisan commands

```bash
php artisan b2b:retry-wallet --limit=50
php artisan b2b:close-stale-sessions --minutes=30
```

## Operator wallet callback

The system resolves callback URL in this order:

1. `wallet_{action}_url`, if present in the database
2. `wallet_callback_url`
3. `callback_url`

The action is one of:

```text
balance
bet
win
refund
rollback
```

If `wallet_secret` is configured for the operator, outbound wallet callbacks include:

```http
X-B2B-Signature: hmac-sha256(json_body, wallet_secret)
```

## Why this matters

A single stuck operator wallet should not block all operators. This update adds:

- Short timeouts
- Attempt logs
- Status transition history
- Failure counting
- Circuit breaker fields
- Retry command with a bounded retry budget
- Duplicate transaction protection foundation

## Idempotency conflicts

Wallet mutation endpoints derive an idempotency key from operator, action, `transaction_id`, and `round_id`.

- Repeating the exact same payload returns the stored transaction result with `duplicate: true`.
- Reusing the same idempotency key with a changed payload returns HTTP `409` and code `IDEMPOTENCY_CONFLICT`.
- Conflicts do not create a second ledger row and do not call the operator wallet again.

## State transitions

Wallet status changes are recorded in `b2b_wallet_transaction_transitions`.

- New transactions append `null -> pending`.
- Operator callback results append `pending -> success`, `pending -> failed`, or `pending -> timeout`.
- Retry results append from the previous retryable state to the new state.
- Retryable states are `failed`, `timeout`, and `unknown`.
- When `attempts >= B2B_WALLET_RETRY_MAX_ATTEMPTS`, retry processing appends `failed|timeout|unknown -> dead_letter` and does not call the operator again.

The transition table is append-only at the application layer; wallet code inserts transition rows and never updates them.
