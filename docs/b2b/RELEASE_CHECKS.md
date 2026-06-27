# B2B Release Checks

Run before packaging or deploying B2B production artifacts:

```bash
php artisan b2b:release-check --production
```

The command fails production mode when:

- nonce replay cache is not Redis;
- B2B rate-limit cache is not Redis;
- queue driver is not Redis;
- `APP_DEBUG` is enabled;
- private wallet callback targets are enabled;
- sandbox wallet is enabled;
- `composer.lock` has security advisories or abandoned packages;
- deployment templates or the production runbook are missing;
- B2B admin RBAC/privileged step-up configuration is missing;
- known local/secret-bearing files are present in the release artifact.

Required production environment values:

```env
CACHE_DRIVER=redis
QUEUE_DRIVER=redis
B2B_NONCE_CACHE_STORE=redis
B2B_RATE_LIMIT_CACHE_STORE=redis
B2B_SANDBOX_ENABLED=false
B2B_ALLOW_PRIVATE_WALLET_CALLBACKS=false
APP_DEBUG=false
```

Local development may run `php artisan b2b:release-check` without `--production`; local secret-bearing files are reported as warnings, not release failures.

Production deployment templates live under `deploy/` and the runbook lives in `docs/deployment/PRODUCTION_RUNBOOK.md`.
