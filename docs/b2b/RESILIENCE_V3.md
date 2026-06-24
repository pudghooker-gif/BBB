# B2B Resilience v3

This update adds the first anti-cascade-failure layer for the B2B aggregator.

It does not delete `.env`, `vendor`, SQL dumps, or other local/public-repository files.

## Added

### Operator circuit breaker

New fields in `b2b_operators`:

- `failure_count`
- `last_failure_at`
- `last_success_at`
- `circuit_open_until`
- `circuit_breaker_threshold`
- `circuit_breaker_cooldown_seconds`
- `max_rps`
- `wallet_timeout_ms`
- `connect_timeout_ms`

When an operator wallet callback fails repeatedly, the operator becomes `degraded` and its circuit opens temporarily. During this time live wallet and launch traffic returns a controlled `OPERATOR_CIRCUIT_OPEN` response instead of blocking the whole platform.

### Per-operator and per-key rate limits

`B2BResilienceGuard` checks `max_rps` per operator and per API key via Laravel cache. API keys can set their own `b2b_operator_api_keys.max_rps`; otherwise `B2B_API_KEY_DEFAULT_MAX_RPS` is used when configured, falling back to the operator `max_rps`.

Launch and wallet mutation endpoints use the same guard and return `RATE_LIMITED` with `meta.rate_scope` set to `operator` or `api_key`.

This is a soft app-level limit. Later it should be combined with Nginx and Redis-based limits.

### Wallet callback timeout

`OperatorWalletClient` now uses per-operator timeout values:

- `wallet_timeout_ms`, default 5000 ms
- `connect_timeout_ms`, default 1500 ms

A slow operator callback will be marked as `timeout` instead of holding the PHP worker for too long.

### Wallet transaction resilience

New fields in `b2b_wallet_transactions`:

- `attempts`
- `last_attempt_at`
- `locked_until`
- `processed_at`

The wallet flow is now:

1. Validate HMAC and operator status.
2. Check rate limit.
3. Check circuit breaker.
4. Check idempotency.
5. Create `pending` transaction.
6. Release DB transaction.
7. Call operator wallet with timeout.
8. Mark transaction `accepted`, `rejected`, or `timeout`.
9. Record operator health event.

### Operator health events

New table:

- `b2b_operator_health_events`

This stores callback failures, timeouts, circuit-breaker events, and recovery events.

### Session resilience fields

New fields in `b2b_game_sessions`:

- `last_seen_at`
- `closed_at`
- `heartbeat_timeout_seconds`
- `failure_code`
- `failure_message`

These are preparation for the next update: session heartbeat, stale-session closing, and launcher integration.

## Apply

From project root:

```bash
unzip bbb_b2b_resilience_v3_nocleanup.zip
chmod +x bbb_b2b_resilience_v3_nocleanup/scripts/*.sh
./bbb_b2b_resilience_v3_nocleanup/scripts/apply_resilience_v3.sh
./bbb_b2b_resilience_v3_nocleanup/scripts/verify_resilience_v3.sh
composer dump-autoload
php artisan migrate
php artisan route:list | grep b2b
```

On Windows Git Bash, if `grep` does not work:

```bash
php artisan route:list | findstr b2b
```

## Notes

This update intentionally does not remove secrets or repository files. It is a no-cleanup patch.

For production, secrets should still be removed from public Git history before going live.
