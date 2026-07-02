# B2B Release Checks

Run before packaging or deploying B2B production artifacts:

```bash
php artisan b2b:release-check --production
```

The command fails production mode when:

- nonce replay cache is not Redis;
- B2B rate-limit cache is not Redis;
- B2B scheduler heartbeat cache is not Redis;
- queue driver is not Redis;
- failed-job storage is disabled, not database-backed, missing the queue runtime migration, missing worker retry limits, or missing runbook coverage;
- scheduler heartbeat is missing from `config/b2b_queues.php` or the scheduler kernel is not reading scheduled commands;
- `APP_DEBUG` is enabled;
- session cookies are not secure, HTTP-only, and SameSite protected;
- production login throttling is disabled or configured above the maximum attempt budget;
- password policy is below the production floor, allows whitespace, or active credential flows bypass the centralized policy;
- credential changes cannot revoke sessions/tokens because database sessions, the `sessions`/`api_tokens` runtime migrations, or credential-change listener wiring are missing;
- private wallet callback targets are enabled;
- sandbox wallet is enabled;
- B2B structured logging is disabled or points to a non-JSON-formatted channel;
- B2B wallet `transaction_id` storage or production lookup/reporting database index migrations are missing;
- production Composer dependencies (`composer audit --locked --no-dev`) have security advisories or abandoned packages;
- Laravel advisory mitigations for CRLF email validation, PHP upload extensions, or disabled framework signed routes are missing;
- B2B readiness, metrics, backend dashboard, operator portal page/overview, or web step-up surfaces are not registered;
- Node/WebSocket manifest, lockfile, proxy template, health probe, origin guard, heartbeat, safe logging, or production config controls are missing;
- deployment, staging migration rehearsal, smoke/load verification, Prometheus alert, Alertmanager routing, or production runbook artifacts are missing;
- B2B admin RBAC/privileged step-up configuration is missing;
- known local/secret-bearing files are present in the release artifact.

Required production environment values:

```env
CACHE_DRIVER=redis
QUEUE_DRIVER=redis
QUEUE_FAILED_DRIVER=database-uuids
B2B_NONCE_CACHE_STORE=redis
B2B_RATE_LIMIT_CACHE_STORE=redis
B2B_SCHEDULER_HEARTBEAT_CACHE_STORE=redis
B2B_SCHEDULER_HEARTBEAT_MAX_AGE_SECONDS=180
B2B_SANDBOX_ENABLED=false
B2B_ALLOW_PRIVATE_WALLET_CALLBACKS=false
B2B_STRUCTURED_LOGGING_ENABLED=true
B2B_STRUCTURED_LOG_CHANNEL=b2b
APP_DEBUG=false
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
LOGIN_THROTTLE_PRODUCTION_ENFORCED=true
LOGIN_THROTTLE_MAX_ATTEMPTS=10
LOGIN_THROTTLE_LOCKOUT_MINUTES=1
PASSWORD_POLICY_MIN_LENGTH=12
PASSWORD_POLICY_MAX_LENGTH=72
PASSWORD_POLICY_REQUIRE_MIXED_CASE=true
PASSWORD_POLICY_REQUIRE_NUMBERS=true
PASSWORD_POLICY_REQUIRE_SYMBOLS=false
PASSWORD_POLICY_DISALLOW_WHITESPACE=true
PASSWORD_POLICY_TEMPORARY_LENGTH=16
```

Local development may run `php artisan b2b:release-check` without `--production`; local secret-bearing files are reported as warnings, not release failures.

Production deployment templates live under `deploy/` and the runbook lives in `docs/deployment/PRODUCTION_RUNBOOK.md`.
