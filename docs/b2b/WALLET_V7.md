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
- Wallet status lookup endpoint with attempts, transitions, and reconciliation items
- Wallet reconciliation queue table: `b2b_wallet_reconciliation_items`
- Operator `transaction_status` lookup during reconciliation for `unknown` wallet states
- Audited manual wallet action log: `b2b_wallet_manual_actions`
- Stale session close command
- API endpoint to check wallet health
- API endpoint to inspect callback attempts for one transaction

## New API endpoints

```http
GET /api/b2b/v1/wallet/health
GET /api/b2b/v1/wallet/transactions/{transaction_uid}/status
GET /api/b2b/v1/wallet/transactions/{transaction_uid}/attempts
```

Both endpoints use B2B HMAC middleware.

## New artisan commands

```bash
php artisan b2b:retry-wallet --limit=50
php artisan b2b:reconcile-wallet --limit=100 --pending-minutes=5
php artisan b2b:wallet-manual-action {transaction_uid} {action} --operator-id=1 --actor=ops_user --reason="Case reference" --permission=b2b.wallet.manual_action --confirm=MANUAL_WALLET_ACTION
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
transaction_status
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
- Reconciliation scan for stale `pending`, `unknown`, `dead_letter`, `manual_review`, `rollback_required`, and exhausted retry states
- Conservative operator status lookup for `unknown` wallet rows before opening or updating a reconciliation item
- Manual review/reversal control foundation with required actor and reason
- Duplicate transaction protection foundation

## Idempotency conflicts

Wallet mutation endpoints derive an idempotency key from operator, action, `transaction_id`, and `round_id`.

- Repeating the exact same payload returns the stored transaction result with `duplicate: true`.
- Reusing the same idempotency key with a changed payload returns HTTP `409` and code `IDEMPOTENCY_CONFLICT`.
- Conflicts do not create a second ledger row and do not call the operator wallet again.

## Payload redaction

Wallet raw request/response persistence, callback attempt logs, transition contexts, manual-action contexts, reconciliation contexts, and status/report output pass through recursive sensitive-field redaction. Operational fields such as `player_id`, `game_id`, `session_id`, `round_id`, `transaction_id`, `amount`, and `currency` remain available for retry and investigation, while keys such as tokens, passwords, secrets, signatures, API keys, card numbers, PANs, CVV/CVC, IBAN, and SSN are replaced with `[REDACTED]`.

## State transitions

Wallet status changes are recorded in `b2b_wallet_transaction_transitions`.

- New transactions append `null -> pending`.
- Operator callback results append `pending -> success`, `pending -> failed`, or `pending -> timeout`.
- Retry results append from the previous retryable state to the new state.
- Retryable states are `failed`, `timeout`, and `unknown`.
- When `attempts >= B2B_WALLET_RETRY_MAX_ATTEMPTS`, retry processing appends `failed|timeout|unknown -> dead_letter` and does not call the operator again.

The transition table is append-only at the application layer; wallet code inserts transition rows and never updates them.

## Status lookup

Operators can inspect one of their own wallet transactions with:

```http
GET /api/b2b/v1/wallet/transactions/{transaction_uid}/status
```

The response includes a compact transaction summary, status transition history, recent callback attempts, open reconciliation items, recent manual actions, and suggested next actions. Lookup is operator-scoped and accepts internal `transaction_uid`, operator `transaction_id`, or numeric row ID.

## Reconciliation scan

`php artisan b2b:reconcile-wallet` scans wallet rows that need operational follow-up.

- Stale `pending` rows are moved to `unknown` through the state machine and then checked with the operator wallet `transaction_status` action.
- Existing `unknown` rows also call the operator wallet `transaction_status` action before opening or updating an item.
- Lookup payloads include operational identifiers such as `transaction_uid`, operator `transaction_id`, `round_id`, `session_id`, `game_uid`, `type`, `amount`, `currency`, and current status.
- Accepted final lookup statuses are intentionally conservative: `success`/`accepted`/`settled`/`processed`/`confirmed` resolve to `success`; `failed`/`declined`/`rejected`/`canceled` resolve to `failed`; `reversed`/`rolled_back` resolve to `reversed`; `rollback_required`/`reversal_required` resolve to `rollback_required`.
- Ambiguous lookup statuses such as `pending`, `processing`, `unknown`, `not_found`, or malformed responses do not change the wallet transaction and are stored in the reconciliation item context.
- Final `success`, `failed`, or `reversed` lookup results close existing open reconciliation items for the transaction. `rollback_required` opens or updates a high-priority follow-up item.
- `failed` or `timeout` rows with attempts greater than or equal to `B2B_WALLET_RETRY_MAX_ATTEMPTS` get an open `retry_budget_exhausted` item.
- Existing open items are updated instead of duplicated.

This is still a reconciliation foundation. Final production readiness still needs provider-specific status contracts/certification, web manual-review workflows, reversal recovery controls, and settlement-grade reconciliation reports.

## Manual wallet actions

Manual state transitions are available through a CLI-only foundation:

```bash
php artisan b2b:wallet-manual-action tx_123 mark-review --operator-id=1 --actor=ops_user --reason="Provider case ABC-123 is unresolved" --permission=b2b.wallet.manual_action --confirm=MANUAL_WALLET_ACTION
```

Supported actions:

- `mark-review` -> `manual_review`
- `resolve-success` -> `success`
- `resolve-failed` -> `failed`
- `mark-rollback-required` -> `rollback_required`
- `mark-reversed` -> `reversed`
- `dead-letter` -> `dead_letter`

Every manual action requires `--actor`, `--reason`, exact `--permission=b2b.wallet.manual_action`, and exact `--confirm=MANUAL_WALLET_ACTION`, writes `b2b_wallet_manual_actions`, appends a wallet transition, and opens or resolves reconciliation items where appropriate. Denied attempts write `privileged_action.denied` when the operator audit table exists.

This is not yet the final production backoffice. Full production readiness still needs authenticated web step-up challenges, confirmation dialogs, raw payload permissions wired into B2B UI, and operator-visible case workflow.
