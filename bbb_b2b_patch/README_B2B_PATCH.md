# BBB B2B Aggregator MVP patch

This package adds the first B2B foundation around the existing Goldsvet/VanguardLTE codebase:

- `/api/b2b/v1/health`
- signed B2B operator API auth via HMAC
- B2B operator/API-key/player/session models
- game launch endpoint
- wallet event endpoints with idempotency and callback logging
- transaction ledger tables
- basic transaction and GGR reports
- security cleanup documentation

## Install

Copy this folder to your BBB repo root, then run:

```bash
./install_b2b_skeleton.sh
composer dump-autoload
php artisan migrate
```

Optional but strongly recommended before pushing:

```bash
./security_cleanup.sh
git add .
git commit -m "Add B2B aggregator foundation"
git push
```

## Important

This is not yet a complete production aggregator. It is a safe MVP skeleton that creates the right extension points without rewriting the old casino core.

Next implementation steps:

1. Add an admin UI or artisan command for creating B2B operators and API keys.
2. Replace Goldsvet launcher token handling with B2B session lookup by `token_hash`.
3. Add provider adapter interface and move Goldsvet games behind `GoldsvetInternalProvider`.
4. Add reconciliation jobs and settlement export.
5. Add integration tests for HMAC, idempotency, rollback, and callback failures.
