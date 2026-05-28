# BBB B2B Resilience v3 No-Cleanup Patch

This patch adds the first resilience layer for the B2B aggregator:

- operator circuit breaker
- per-operator rate limit
- wallet callback timeout
- wallet idempotency flow improvements
- transaction attempt/lock fields
- operator health event logging
- session heartbeat preparation fields

It does not remove `.env`, `.env_old`, `vendor`, `composer.phar`, `totalbet365.sql`, or any other repository files.

## Windows Git Bash usage

Place this folder inside the Laravel project root, for example:

```text
C:\pro\casino\bbb_b2b_resilience_v3_nocleanup
```

Then run:

```bash
cd /c/pro/casino
chmod +x bbb_b2b_resilience_v3_nocleanup/scripts/*.sh
./bbb_b2b_resilience_v3_nocleanup/scripts/apply_resilience_v3.sh
./bbb_b2b_resilience_v3_nocleanup/scripts/verify_resilience_v3.sh
composer dump-autoload
php artisan migrate
php artisan route:list | grep b2b
```

If `grep` is not available:

```bash
php artisan route:list | findstr b2b
```

## Commit message

```text
Add B2B resilience layer
```

## Next update after this

The next update should connect `b2b_game_sessions` to the existing `/launcher/{game}/{token}` route and add the `GoldsvetInternalProvider` flow.
