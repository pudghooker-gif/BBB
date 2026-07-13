# B2B Release Checks

Run before packaging or deploying B2B production artifacts:

```bash
php artisan b2b:release-check --production
```

Before broad production traffic, validate the external launch evidence package:

```bash
php artisan b2b:evidence-template /var/www/bbb/release-evidence/<release-id> --release-id=<release-id>
php artisan b2b:evidence-hash /var/www/bbb/release-evidence/<release-id> --write
php artisan b2b:evidence-check /var/www/bbb/release-evidence/<release-id> --production
```

Before broad backoffice payload exposure, run the legacy wallet payload redaction audit and store the final clean dry-run artifact with release evidence:

```bash
php artisan b2b:payload-redaction-audit --artifact=/var/www/bbb/release-evidence/<release-id>/payload-redaction-dry-run.json
php artisan b2b:payload-redaction-audit --write --artifact=/var/www/bbb/release-evidence/<release-id>/payload-redaction-write.json
php artisan b2b:payload-redaction-audit --artifact=/var/www/bbb/release-evidence/<release-id>/payload-redaction-final.json
```

The dry-run exits non-zero when legacy unredacted payload fields are found. The JSON artifact contains counts by table/column only and must not contain payload values. The final clean dry-run artifact must be referenced by `payload_redaction_audit` in `release-evidence.json` before `b2b:evidence-hash --write` and `b2b:evidence-check --production` are run.

The `B2B Release Verification` GitHub Actions workflow runs the same production
gate, deploy shell-script syntax lint, clean and repeatable migration
verification, release evidence template generation sanity, a locked production
Composer audit, WebSocket `pnpm audit --prod`, and `pnpm run
check:production-config` against the shipped WebSocket production config as hard
failures. A red workflow is a release blocker, not an advisory signal.

The command fails production mode when:

- nonce replay cache is not Redis;
- B2B rate-limit cache is not Redis;
- B2B game catalog cache is disabled or not Redis;
- B2B scheduler heartbeat cache is not Redis;
- queue driver is not Redis;
- failed-job storage is disabled, not database-backed, missing the queue runtime migration, missing worker retry limits, or missing runbook coverage;
- scheduler heartbeat is missing from `config/b2b_queues.php` or the scheduler kernel is not reading scheduled commands;
- `APP_DEBUG` is enabled;
- session cookies are not secure, HTTP-only, and SameSite protected;
- production login throttling is disabled or configured above the maximum attempt budget;
- B2B web step-up does not require a current-password check for privileged backend actions;
- legacy B2B payload redaction audit/remediation tooling or payload redaction release evidence documentation is missing;
- B2B API keys do not have explicit scopes, settlement export is not protected by `reports.export`, or `B2B_API_KEY_DEFAULT_SCOPES` grants `reports.export`/`*` by default;
- password policy is below the production floor, allows whitespace, or active credential flows bypass the centralized policy;
- credential changes cannot revoke sessions/tokens because database sessions, the `sessions`/`api_tokens` runtime migrations, or credential-change listener wiring are missing;
- private wallet callback targets are enabled;
- sandbox wallet is enabled;
- B2B structured logging is disabled or points to a non-JSON-formatted channel;
- provider health is not surfaced through readiness, metrics, and the signed operator portal;
- B2B wallet `transaction_id` storage or production lookup/reporting database index migrations are missing;
- B2B game catalog sync lacks safe soft-disable or cache-invalidation coverage;
- B2B launcher integration is missing the signed `/b2b/launcher/{game}/{token}` bridge, hashed one-time token storage, provider-prepared legacy launcher redirect, or secret-free launch-flow regression coverage;
- production Composer dependencies (`composer audit --locked --no-dev`) have security advisories or abandoned packages;
- WebSocket production dependencies (`pnpm audit --prod` under `PTWebSocket/`) have known vulnerabilities;
- WebSocket production config validation fails because Node is publicly bound, wildcard/non-HTTPS origins are allowed, session-cookie handshakes or structured logs are disabled, inline auth tokens are present, or the runtime config still contains example domains;
- Laravel advisory mitigations for CRLF email validation, PHP upload extensions, or disabled framework signed routes are missing;
- B2B readiness, metrics, backend dashboard, redacted audit trail/export, operator portal page/overview, or web step-up surfaces are not registered;
- Node/WebSocket manifest, lockfile, proxy template, health probe, origin guard, heartbeat, safe logging, or production config controls are missing;
- deployment, staging migration rehearsal, smoke/load verification, release evidence template/checker, Prometheus alert, Alertmanager routing, or production runbook artifacts are missing;
- B2B admin RBAC/privileged step-up configuration is missing;
- known local/secret-bearing files are present in the release artifact.

`b2b:evidence-template` creates a fresh `release-evidence.json` skeleton from the same required evidence list used by the production checker. `b2b:evidence-hash --write` calculates SHA-256 hashes for every artifact referenced by `release-evidence.json` and writes `sha256` or `artifact_hashes` back into the manifest. `b2b:evidence-check --production` fails when the external evidence directory is missing `release-evidence.json`, when required evidence entries or non-empty artifacts are missing, when provider/legal approvals lack an `approved_by` owner, when any artifact lacks a SHA-256 hash, when artifact hashes do not match, or when the manifest or artifact files appear to contain inline secrets. The command expects redacted logs or references for staging migration rehearsal, production release gate output, final clean payload redaction audit, healthcheck, smoke, smoke-load, WebSocket public proxy validation, backup, restore rehearsal, rollback rehearsal, queue runtime drill, Prometheus, Alertmanager, log shipping, wallet/provider correlation validation, provider credentials/certification, legal approval, and final domains/TLS/proxy/shared-state validation. Use `sha256` for a single `artifact` entry and `artifact_hashes` for entries with multiple `artifacts`. Run migration rehearsal with `MIGRATION_REHEARSAL_ARTIFACT_DIR`, `deploy/scripts/healthcheck.sh` with `HEALTHCHECK_ARTIFACT_DIR`, the final topology check with `FINAL_TOPOLOGY_ARTIFACT_DIR` and `bash deploy/scripts/final-topology-check.sh`, queue runtime validation with `QUEUE_RUNTIME_ARTIFACT_DIR` and `bash deploy/scripts/queue-runtime-drill.sh`, which runs `php artisan b2b:queue-runtime-evidence --production`, so `b2b-queue-runtime-drill.log` and `b2b-queue-runtime-evidence.json` land under `operations`, Prometheus scrape/rule validation with `PROMETHEUS_ARTIFACT_DIR` and `bash deploy/scripts/prometheus-smoke.sh`, the smoke script with `B2B_SMOKE_ARTIFACT_DIR`, the WebSocket public proxy smoke with `WEBSOCKET_SMOKE_ARTIFACT_DIR` and `pnpm run smoke:public-proxy`, Alertmanager smoke with `ALERTMANAGER_ARTIFACT_DIR` and `bash deploy/scripts/alertmanager-smoke.sh`, downstream receiver validation with `ALERTMANAGER_RECEIVER_ARTIFACT_DIR` and `bash deploy/scripts/alertmanager-receiver-check.sh` so `alertmanager-receiver-delivery-confirmation.log` lands beside `alertmanager-delivery-test.log`, `php artisan b2b:log-shipping-check --marker=<release-marker> --artifact=/var/www/bbb/release-evidence/<release-id>/observability/b2b-log-shipping-validation.log`, external log delivery validation with `LOG_SHIPPING_MARKER=<release-marker>`, `LOG_SHIPPING_ARTIFACT_DIR`, and `bash deploy/scripts/log-shipping-external-check.sh` so `b2b-log-shipping-external-delivery.log` lands alongside the local marker artifact, `php artisan b2b:correlation-evidence --artifact=/var/www/bbb/release-evidence/<release-id>/observability/b2b-correlation-validation.json`, the k6 scenario with `K6_SUMMARY_PATH`, backup with `BACKUP_ARTIFACT_DIR`, off-host backup verification with `OFFHOST_BACKUP_DIR`, `BACKUP_HASH_FILE`, and `bash deploy/scripts/backup-offhost-verify.sh`, restore with `RESTORE_ARTIFACT_DIR`, and rollback with `ROLLBACK_ARTIFACT_DIR` so those checks write evidence-ready artifacts directly into the release evidence directory. Use `deploy/evidence/provider-credential-approval-redacted.example.txt`, `deploy/evidence/provider-wallet-contract-certification-redacted.example.txt`, and `deploy/evidence/legal-launch-approval-redacted.example.txt` as secret-free starting points for external approval artifacts.

Required production environment values:

```env
CACHE_DRIVER=redis
QUEUE_DRIVER=redis
QUEUE_FAILED_DRIVER=database-uuids
B2B_NONCE_CACHE_STORE=redis
B2B_RATE_LIMIT_CACHE_STORE=redis
B2B_GAME_CATALOG_CACHE_ENABLED=true
B2B_GAME_CATALOG_CACHE_STORE=redis
B2B_GAME_CATALOG_CACHE_TTL_SECONDS=300
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
B2B_WEB_STEP_UP_REQUIRES_PASSWORD=true
B2B_WEB_STEP_UP_TTL_SECONDS=300
B2B_API_KEY_DEFAULT_SCOPES=operator.read,portal.read,games.read,games.launch,sessions.read,sessions.close,wallet.balance,wallet.status,wallet.mutate,reports.read,support.write
PASSWORD_POLICY_MIN_LENGTH=12
PASSWORD_POLICY_MAX_LENGTH=72
PASSWORD_POLICY_REQUIRE_MIXED_CASE=true
PASSWORD_POLICY_REQUIRE_NUMBERS=true
PASSWORD_POLICY_REQUIRE_SYMBOLS=false
PASSWORD_POLICY_DISALLOW_WHITESPACE=true
PASSWORD_POLICY_TEMPORARY_LENGTH=16
```

Local development may run `php artisan b2b:release-check` without `--production`; local secret-bearing files are reported as warnings, not release failures.

Production deployment templates live under `deploy/`; the release evidence template lives at `deploy/evidence/release-evidence.example.json`; and the runbook lives in `docs/deployment/PRODUCTION_RUNBOOK.md`.
