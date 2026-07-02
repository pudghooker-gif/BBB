<?php

namespace VanguardLTE\B2B\Services;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Symfony\Component\Process\Process;
use VanguardLTE\B2B\Contracts\GameProviderInterface;
use VanguardLTE\B2B\Providers\GoldsvetInternalProvider;
use VanguardLTE\Support\Validation\SecurityHardenedValidator;

class B2BReleaseGate
{
    public function run($production = false, $checkFiles = true, $checkDependencyAudit = false)
    {
        $checks = [
            $this->cacheStoreCheck('nonce_cache', config('b2b.nonce_cache_store') ?: config('cache.default'), $production),
            $this->cacheStoreCheck('rate_limit_cache', config('b2b.rate_limit_cache_store') ?: config('cache.default'), $production),
            $this->cacheStoreCheck('scheduler_heartbeat_cache', config('b2b.scheduler_heartbeat_cache_store') ?: config('cache.default'), $production),
            $this->queueCheck($production),
            $this->failedJobStorageCheck($production),
            $this->schedulerConfigCheck($production),
            $this->booleanCheck('app_debug', !(bool) config('app.debug'), $production, 'APP_DEBUG must be false for production.'),
            $this->sessionCookieSecurityCheck($production),
            $this->loginThrottleSecurityCheck($production),
            $this->passwordPolicySecurityCheck($production),
            $this->credentialSessionRevocationCheck($production),
            $this->booleanCheck('private_wallet_callbacks', !(bool) config('b2b.allow_private_wallet_callbacks'), $production, 'Private wallet callback targets must stay disabled in production.'),
            $this->booleanCheck('sandbox_disabled', !(bool) config('b2b.sandbox_enabled'), $production, 'B2B sandbox must be disabled in production.'),
            $this->structuredLoggingCheck($production),
            $this->providerWalletContractsCheck($production),
            $this->databaseSchemaCheck($production),
            $this->deploymentArtifactsCheck($production),
            $this->websocketRuntimeCheck($production),
            $this->adminRbacCheck($production),
            $this->webSurfacesCheck($production),
            $this->laravelSecurityMitigationsCheck($production),
        ];

        if ($checkFiles) {
            $checks[] = $this->secretFilesCheck($production);
        }

        if ($checkDependencyAudit) {
            $checks[] = $this->dependencyAuditCheck($production);
        }

        $ok = true;
        foreach ($checks as $check) {
            if ($check['status'] === 'fail') {
                $ok = false;
                break;
            }
        }

        return [
            'ok' => $ok,
            'checks' => $checks,
        ];
    }

    private function cacheStoreCheck($name, $store, $production)
    {
        $driver = config('cache.stores.' . $store . '.driver');
        $ok = !$production || $driver === 'redis';

        return [
            'name' => $name,
            'status' => $ok ? 'pass' : 'fail',
            'message' => $ok
                ? 'Cache store is acceptable: ' . ($store ?: 'default') . ' (' . ($driver ?: 'unknown') . ').'
                : 'Production B2B shared state must use Redis. Current store: ' . ($store ?: 'default') . ' (' . ($driver ?: 'unknown') . ').',
        ];
    }

    private function queueCheck($production)
    {
        $connection = config('queue.default');
        $driver = config('queue.connections.' . $connection . '.driver');
        $ok = !$production || $driver === 'redis';

        return [
            'name' => 'queue_driver',
            'status' => $ok ? 'pass' : 'fail',
            'message' => $ok
                ? 'Queue driver is acceptable: ' . ($connection ?: 'default') . ' (' . ($driver ?: 'unknown') . ').'
                : 'Production B2B workers must use Redis queues. Current queue: ' . ($connection ?: 'default') . ' (' . ($driver ?: 'unknown') . ').',
        ];
    }

    private function failedJobStorageCheck($production)
    {
        $failed = (array) config('queue.failed', []);
        $driver = isset($failed['driver'])
            ? (string) $failed['driver']
            : (isset($failed['table']) ? 'database' : null);
        $table = isset($failed['table']) ? (string) $failed['table'] : '';
        $database = isset($failed['database']) ? (string) $failed['database'] : '';
        $missing = [];

        if (!in_array($driver, ['database', 'database-uuids'], true)) {
            $missing[] = 'driver:' . ($driver ?: 'missing');
        }

        if ($database === '') {
            $missing[] = 'database';
        }

        if ($table !== 'failed_jobs') {
            $missing[] = 'table:' . ($table ?: 'missing');
        }

        $queueConfig = $this->fileContents(base_path('config/queue.php'));
        foreach (['QUEUE_FAILED_DRIVER', 'database-uuids', "'failed'"] as $needle) {
            if (strpos($queueConfig, $needle) === false) {
                $missing[] = 'queue_config:' . $needle;
            }
        }

        $migration = $this->fileContents(base_path('database/migrations/2026_06_24_000008_create_queue_runtime_tables.php'));
        foreach ([
            "Schema::create('failed_jobs'",
            "Schema::create('jobs'",
            "'uuid'",
            "'connection'",
            "'queue'",
            "'payload'",
            "'exception'",
            "'failed_at'",
        ] as $needle) {
            if (strpos($migration, $needle) === false) {
                $missing[] = 'migration:' . $needle;
            }
        }

        $supervisor = $this->fileContents(base_path('deploy/supervisor/b2b-workers.conf.example'));
        foreach (['queue:work redis', '--tries=', '--timeout=', '--max-time='] as $needle) {
            if (strpos($supervisor, $needle) === false) {
                $missing[] = 'supervisor:' . $needle;
            }
        }

        $runbook = $this->fileContents(base_path('docs/deployment/PRODUCTION_RUNBOOK.md'));
        foreach (['queue:failed', 'queue:retry', 'failed_jobs'] as $needle) {
            if (strpos($runbook, $needle) === false) {
                $missing[] = 'runbook:' . $needle;
            }
        }

        return [
            'name' => 'failed_job_storage',
            'status' => count($missing) === 0 ? 'pass' : ($production ? 'fail' : 'warn'),
            'message' => count($missing) === 0
                ? 'Laravel failed-job storage, migration, worker retry limits, and runbook coverage are present.'
                : 'Missing failed-job production coverage: ' . implode(', ', $missing),
        ];
    }

    private function schedulerConfigCheck($production)
    {
        $scheduled = (array) config('b2b_queues.scheduled_commands', []);
        $heartbeat = isset($scheduled['scheduler_heartbeat']) && is_array($scheduled['scheduler_heartbeat'])
            ? $scheduled['scheduler_heartbeat']
            : [];
        $missing = [];

        if (empty($heartbeat['command']) || strpos((string) $heartbeat['command'], 'b2b:scheduler-heartbeat') !== 0) {
            $missing[] = 'command:b2b:scheduler-heartbeat';
        }

        if (empty($heartbeat['frequency']) || $heartbeat['frequency'] !== 'everyMinute') {
            $missing[] = 'frequency:everyMinute';
        }

        if (empty($heartbeat['queue']) || $heartbeat['queue'] !== 'maintenance') {
            $missing[] = 'queue:maintenance';
        }

        $kernel = $this->fileContents(base_path('app/Console/Kernel.php'));
        foreach (['scheduleB2BCommands($schedule)', "config('b2b_queues.scheduled_commands'", 'withoutOverlapping()'] as $needle) {
            if (strpos($kernel, $needle) === false) {
                $missing[] = 'kernel:' . $needle;
            }
        }

        return [
            'name' => 'scheduler_config',
            'status' => count($missing) === 0 ? 'pass' : ($production ? 'fail' : 'warn'),
            'message' => count($missing) === 0
                ? 'B2B scheduler heartbeat command is registered with shared scheduler locking.'
                : 'Missing B2B scheduler heartbeat coverage: ' . implode(', ', $missing),
        ];
    }

    private function booleanCheck($name, $condition, $production, $message)
    {
        return [
            'name' => $name,
            'status' => (!$production || $condition) ? 'pass' : 'fail',
            'message' => (!$production || $condition) ? 'Configuration is acceptable.' : $message,
        ];
    }

    private function sessionCookieSecurityCheck($production)
    {
        $missing = [];
        $sameSite = strtolower((string) config('session.same_site'));

        if (!(bool) config('session.secure')) {
            $missing[] = 'SESSION_SECURE_COOKIE=true';
        }

        if (!(bool) config('session.http_only')) {
            $missing[] = 'SESSION_HTTP_ONLY=true';
        }

        if (!in_array($sameSite, ['lax', 'strict'], true)) {
            $missing[] = 'SESSION_SAME_SITE=lax|strict';
        }

        return [
            'name' => 'session_cookie_security',
            'status' => count($missing) === 0 ? 'pass' : ($production ? 'fail' : 'warn'),
            'message' => count($missing) === 0
                ? 'Session cookies are secure, HTTP-only, and SameSite protected.'
                : 'Production session cookie settings are unsafe: ' . implode(', ', $missing),
        ];
    }

    private function loginThrottleSecurityCheck($production)
    {
        $missing = [];
        $enforced = (bool) config('security.login_throttle.production_enforced', true);
        $maxAttempts = (int) config('security.login_throttle.max_attempts', 10);
        $lockoutMinutes = (int) config('security.login_throttle.lockout_minutes', 1);

        if (!$enforced) {
            $missing[] = 'LOGIN_THROTTLE_PRODUCTION_ENFORCED=true';
        }

        if ($maxAttempts < 1 || $maxAttempts > 10) {
            $missing[] = 'LOGIN_THROTTLE_MAX_ATTEMPTS<=10';
        }

        if ($lockoutMinutes < 1) {
            $missing[] = 'LOGIN_THROTTLE_LOCKOUT_MINUTES>=1';
        }

        foreach ([
            'app/Http/Controllers/Web/Backend/Auth/AuthController.php',
            'app/Http/Controllers/Web/Frontend/Auth/AuthController.php',
        ] as $path) {
            $contents = $this->fileContents(base_path($path));
            foreach (['loginThrottlingEnabled', 'productionLoginThrottleEnforced', 'security.login_throttle.max_attempts'] as $needle) {
                if (strpos($contents, $needle) === false) {
                    $missing[] = $path . ':' . $needle;
                }
            }

            if (strpos($contents, 'lockoutTime() / 60') !== false) {
                $missing[] = $path . ':rate_limiter_decay_seconds';
            }
        }

        return [
            'name' => 'login_throttle_security',
            'status' => count($missing) === 0 ? 'pass' : ($production ? 'fail' : 'warn'),
            'message' => count($missing) === 0
                ? 'Production login throttling is enforced with bounded attempts and lockout.'
                : 'Production login throttling is unsafe: ' . implode(', ', $missing),
        ];
    }

    private function passwordPolicySecurityCheck($production)
    {
        $missing = [];
        $minLength = (int) config('security.password_policy.min_length', 12);
        $maxLength = (int) config('security.password_policy.max_length', 72);
        $temporaryLength = (int) config('security.password_policy.temporary_length', 16);

        if ($minLength < 12) {
            $missing[] = 'PASSWORD_POLICY_MIN_LENGTH>=12';
        }

        if ($maxLength < $minLength || $maxLength > 72) {
            $missing[] = 'PASSWORD_POLICY_MAX_LENGTH between min length and 72';
        }

        if (!(bool) config('security.password_policy.require_mixed_case', true)) {
            $missing[] = 'PASSWORD_POLICY_REQUIRE_MIXED_CASE=true';
        }

        if (!(bool) config('security.password_policy.require_numbers', true)) {
            $missing[] = 'PASSWORD_POLICY_REQUIRE_NUMBERS=true';
        }

        if (!(bool) config('security.password_policy.disallow_whitespace', true)) {
            $missing[] = 'PASSWORD_POLICY_DISALLOW_WHITESPACE=true';
        }

        if ($temporaryLength < $minLength || $temporaryLength > $maxLength) {
            $missing[] = 'PASSWORD_POLICY_TEMPORARY_LENGTH within policy bounds';
        }

        foreach ($this->passwordPolicyFiles() as $path) {
            $contents = $this->fileContents(base_path($path));
            if (strpos($contents, 'PasswordPolicy') === false) {
                $missing[] = $path . ':PasswordPolicy';
            }

            foreach (['min:6', 'min:8'] as $legacyRule) {
                if (strpos($contents, $legacyRule) !== false) {
                    $missing[] = $path . ':' . $legacyRule;
                }
            }

            foreach ([
                '$password = rand(111111111, 999999999)',
                "'password' => \$number",
            ] as $legacyPassword) {
                if (strpos($contents, $legacyPassword) !== false) {
                    $missing[] = $path . ':legacy_generated_password';
                }
            }
        }

        return [
            'name' => 'password_policy_security',
            'status' => count($missing) === 0 ? 'pass' : ($production ? 'fail' : 'warn'),
            'message' => count($missing) === 0
                ? 'Production password policy is centralized, strong, and applied to active credential flows.'
                : 'Production password policy is unsafe: ' . implode(', ', $missing),
        ];
    }

    private function passwordPolicyFiles()
    {
        return [
            'app/Http/Requests/Auth/RegisterRequest.php',
            'app/Http/Requests/Auth/PasswordResetRequest.php',
            'app/Http/Requests/User/CreateUserRequest.php',
            'app/Http/Requests/User/UpdateUserRequest.php',
            'app/Http/Requests/User/UpdateLoginDetailsRequest.php',
            'app/Http/Requests/User/UpdateProfileDetailsRequest.php',
            'app/Http/Requests/User/UpdateProfilePasswordRequest.php',
            'app/Http/Requests/User/UpdateDetailsRequest.php',
            'app/Http/Controllers/Api/BasicController.php',
            'app/Http/Controllers/Api/ShopController.php',
            'app/Http/Controllers/Api/Users/UsersController.php',
            'app/Http/Controllers/Api/Profile/DetailsController.php',
            'app/Http/Controllers/Web/Backend/UsersController.php',
            'app/Http/Controllers/Web/Backend/ShopsController.php',
            'app/Http/Controllers/Web/Frontend/ProfileController.php',
        ];
    }

    private function credentialSessionRevocationCheck($production)
    {
        $missing = [];

        if ((string) config('session.driver') !== 'database') {
            $missing[] = 'SESSION_DRIVER=database';
        }

        if ((string) config('session.table') !== 'sessions') {
            $missing[] = 'SESSION_TABLE=sessions';
        }

        $migration = $this->fileContents(base_path('database/migrations/2026_06_24_000009_create_sessions_runtime_table.php'));
        foreach ([
            "Schema::create('sessions'",
            "\$table->string('id')->primary()",
            "\$table->unsignedInteger('user_id')->nullable()->index()",
            "\$table->integer('last_activity')->index()",
        ] as $needle) {
            if (strpos($migration, $needle) === false) {
                $missing[] = 'sessions_migration:' . $needle;
            }
        }

        $eventProvider = $this->fileContents(base_path('app/Providers/EventServiceProvider.php'));
        foreach (['UserCredentialsChanged::class', 'InvalidateSessionsAndTokens::class'] as $needle) {
            if (strpos($eventProvider, $needle) === false) {
                $missing[] = 'event_provider:' . $needle;
            }
        }

        $listener = $this->fileContents(base_path('app/Listeners/Users/InvalidateSessionsAndTokens.php'));
        foreach (['invalidateAllSessionsForUser', "Token::where('user_id'", "Schema::hasTable('api_tokens')"] as $needle) {
            if (strpos($listener, $needle) === false) {
                $missing[] = 'listener:' . $needle;
            }
        }

        $apiTokensMigration = $this->fileContents(base_path('database/migrations/2026_06_24_000010_create_api_tokens_runtime_table.php'));
        foreach ([
            "Schema::create('api_tokens'",
            "\$table->string('id', 80)->primary()",
            "\$table->unsignedInteger('user_id')->index()",
            "\$table->timestamp('expires_at')->nullable()->index()",
        ] as $needle) {
            if (strpos($apiTokensMigration, $needle) === false) {
                $missing[] = 'api_tokens_migration:' . $needle;
            }
        }

        foreach ($this->credentialChangeFiles() as $path) {
            $contents = $this->fileContents(base_path($path));
            if (strpos($contents, 'UserCredentialsChanged') === false) {
                $missing[] = $path . ':UserCredentialsChanged';
            }
        }

        return [
            'name' => 'credential_session_revocation',
            'status' => count($missing) === 0 ? 'pass' : ($production ? 'fail' : 'warn'),
            'message' => count($missing) === 0
                ? 'Password changes revoke database sessions, remember tokens, and local API tokens.'
                : 'Credential-change session revocation is incomplete: ' . implode(', ', $missing),
        ];
    }

    private function credentialChangeFiles()
    {
        return [
            'app/Http/Controllers/Web/Frontend/Auth/PasswordController.php',
            'app/Http/Controllers/Api/Auth/Password/ResetController.php',
            'app/Http/Controllers/Web/Frontend/ProfileController.php',
            'app/Http/Controllers/Web/Backend/ProfileController.php',
            'app/Http/Controllers/Api/Profile/DetailsController.php',
            'app/Http/Controllers/Api/Users/UsersController.php',
            'app/Http/Controllers/Web/Backend/UsersController.php',
            'app/Http/Controllers/Web/Backend/ShopsController.php',
        ];
    }

    private function structuredLoggingCheck($production)
    {
        $enabled = (bool) config('b2b.structured_logging_enabled', true);
        $channel = config('b2b.structured_log_channel') ?: config('logging.default');
        $channelConfig = config('logging.channels.' . $channel);
        $taps = is_array($channelConfig) && isset($channelConfig['tap']) && is_array($channelConfig['tap'])
            ? $channelConfig['tap']
            : [];
        $hasJsonFormatter = in_array(\VanguardLTE\Logging\B2BJsonFormatter::class, $taps, true);
        $ok = !$production || ($enabled && is_array($channelConfig) && $hasJsonFormatter);

        return [
            'name' => 'structured_logging',
            'status' => $ok ? 'pass' : 'fail',
            'message' => $ok
                ? 'B2B structured JSON logging is enabled on channel: ' . ($channel ?: 'default') . '.'
                : 'Production B2B structured logging must be enabled and point to a JSON-formatted channel.',
        ];
    }

    private function secretFilesCheck($production)
    {
        $paths = [
            '.env',
            '.env_old',
            'totalbet365.sql',
            'PTWebSocket/ssl/key.key',
            'PTWebSocket/ssl/crt.crt',
        ];

        $present = [];
        foreach ($paths as $path) {
            if (file_exists(base_path($path))) {
                $present[] = $path;
            }
        }

        return [
            'name' => 'release_secret_files',
            'status' => count($present) === 0 ? 'pass' : ($production ? 'fail' : 'warn'),
            'message' => count($present) > 0
                ? 'Secret-bearing/local files must be excluded from production artifacts: ' . implode(', ', $present)
                : 'No known secret-bearing/local release blocker files were found.',
        ];
    }

    private function providerWalletContractsCheck($production)
    {
        $missing = [];

        try {
            foreach ($this->walletContractProviders() as $provider) {
                $label = is_object($provider) && method_exists($provider, 'providerCode')
                    ? $provider->providerCode()
                    : (is_object($provider) ? get_class($provider) : 'unknown_provider');

                if (!$provider instanceof GameProviderInterface) {
                    $missing[] = 'provider_interface:' . $label;
                    continue;
                }

                $contracts = $provider->walletActionContracts();
                if (!is_array($contracts) || count($contracts) === 0) {
                    $missing[] = 'contracts:' . $label;
                    continue;
                }

                foreach ($this->requiredWalletActions() as $action) {
                    if (!$provider->supportsWalletAction($action)) {
                        $missing[] = 'action:' . $label . ':' . $action;
                        continue;
                    }

                    $contract = $provider->walletActionContract($action);
                    if (!is_array($contract)) {
                        $missing[] = 'contract:' . $label . ':' . $action;
                    }
                }

                foreach ($this->requiredWalletActionContracts() as $action => $requirements) {
                    $contract = $provider->walletActionContract($action);
                    if (!is_array($contract)) {
                        $missing[] = 'contract:' . $label . ':' . $action;
                        continue;
                    }

                    foreach ($requirements as $key => $values) {
                        if ($key === 'idempotency_key') {
                            if (!isset($contract[$key]) || $contract[$key] !== $values) {
                                $missing[] = 'contract:' . $label . ':' . $action . ':' . $key;
                            }
                            continue;
                        }

                        if (empty($contract[$key]) || !is_array($contract[$key])) {
                            $missing[] = 'contract:' . $label . ':' . $action . ':' . $key;
                            continue;
                        }

                        foreach ($values as $value) {
                            if (!in_array($value, $contract[$key], true)) {
                                $missing[] = 'contract:' . $label . ':' . $action . ':' . $key . ':' . $value;
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            $missing[] = 'exception:' . $e->getMessage();
        }

        return [
            'name' => 'provider_wallet_contracts',
            'status' => count($missing) === 0 ? 'pass' : ($production ? 'fail' : 'warn'),
            'message' => count($missing) === 0
                ? 'Provider wallet action contracts are explicit for mutation, status lookup, and rollback recovery flows.'
                : 'Missing provider wallet action contract coverage: ' . implode(', ', $missing),
        ];
    }

    protected function walletContractProviders()
    {
        return [
            app(GoldsvetInternalProvider::class),
        ];
    }

    private function requiredWalletActions()
    {
        return ['balance', 'bet', 'win', 'refund', 'rollback', 'transaction_status'];
    }

    private function requiredWalletActionContracts()
    {
        return [
            'transaction_status' => [
                'request_fields' => ['transaction_uid', 'transaction_id', 'round_id', 'session_id', 'game_uid', 'type', 'amount', 'currency', 'current_status'],
                'response_fields' => ['transaction_status', 'status', 'state'],
                'final_statuses' => ['success', 'failed', 'rollback_required', 'reversed'],
                'ambiguous_statuses' => ['pending', 'processing', 'unknown', 'not_found'],
            ],
            'rollback' => [
                'request_fields' => ['transaction_id', 'original_transaction_id', 'original_transaction_uid', 'round_id', 'session_id', 'game_id', 'amount', 'currency', 'recovery_reason', 'recovery_attempt'],
                'response_fields' => ['status'],
                'terminal_statuses' => ['accepted', 'success', 'ok', 'failed'],
                'idempotency_key' => 'transaction_id',
            ],
        ];
    }

    private function deploymentArtifactsCheck($production)
    {
        $paths = [
            'deploy/nginx/bbb-b2b.conf.example',
            'deploy/php-fpm/bbb-b2b.pool.conf.example',
            'deploy/supervisor/b2b-workers.conf.example',
            'deploy/systemd/bbb-scheduler.service',
            'deploy/systemd/bbb-scheduler.timer',
            'deploy/systemd/bbb-websocket.service',
            'deploy/cron/bbb-maintenance.cron.example',
            'deploy/scripts/backup.sh',
            'deploy/scripts/restore.sh',
            'deploy/scripts/rollback.sh',
            'deploy/scripts/healthcheck.sh',
            'deploy/scripts/migration-rehearsal.sh',
            'deploy/scripts/b2b-smoke.sh',
            'deploy/k6/b2b-smoke-load.js',
            'deploy/prometheus/b2b-alerts.yml',
            'deploy/prometheus/alertmanager-routes.example.yml',
            'docs/deployment/PRODUCTION_RUNBOOK.md',
        ];

        $missing = [];
        foreach ($paths as $path) {
            if (!file_exists(base_path($path))) {
                $missing[] = $path;
            }
        }

        return [
            'name' => 'deployment_artifacts',
            'status' => count($missing) === 0 ? 'pass' : ($production ? 'fail' : 'warn'),
            'message' => count($missing) === 0
                ? 'Production deployment, monitoring, smoke/load, and runbook artifacts are present.'
                : 'Missing production deployment artifacts: ' . implode(', ', $missing),
        ];
    }

    private function databaseSchemaCheck($production)
    {
        $missing = [];
        $migration = $this->fileContents(base_path('database/migrations/2026_06_24_000007_add_production_indexes_to_b2b_tables.php'));
        if ($migration === '') {
            $missing[] = 'migration:production_indexes';
        }

        foreach ([
            'transaction_id',
            'b2b_wt_operator_tx_uid_idx',
            'b2b_wt_operator_tx_id_idx',
            'b2b_wt_operator_status_created_idx',
            'b2b_gs_operator_session_uid_idx',
            'b2b_gs_operator_status_created_idx',
            'b2b_wcl_operator_http_created_idx',
            'b2b_set_operator_period_idx',
            'b2b_oae_operator_event_created_idx',
            'b2b_pr_provider_action_status_created_idx',
        ] as $needle) {
            if (strpos($migration, $needle) === false) {
                $missing[] = 'migration_index:' . $needle;
            }
        }

        $model = $this->fileContents(base_path('app/B2B/Models/B2BWalletTransaction.php'));
        if (strpos($model, "'transaction_id'") === false) {
            $missing[] = 'model_fillable:transaction_id';
        }

        return [
            'name' => 'database_schema',
            'status' => count($missing) === 0 ? 'pass' : ($production ? 'fail' : 'warn'),
            'message' => count($missing) === 0
                ? 'B2B wallet transaction IDs and production lookup/reporting indexes are covered by migrations.'
                : 'Missing B2B production database schema coverage: ' . implode(', ', $missing),
        ];
    }

    private function websocketRuntimeCheck($production)
    {
        $missing = [];
        $paths = [
            'PTWebSocket/Server.js',
            'PTWebSocket/package.json',
            'PTWebSocket/pnpm-lock.yaml',
            'deploy/websocket/socket_config2.production.example.json',
            'deploy/systemd/bbb-websocket.service',
        ];

        foreach ($paths as $path) {
            if (!file_exists(base_path($path))) {
                $missing[] = 'path:' . $path;
            }
        }

        $package = $this->jsonFile(base_path('PTWebSocket/package.json'));
        if (!$package) {
            $missing[] = 'json:PTWebSocket/package.json';
        } else {
            foreach (['ws', 'request', 'mysql2', 'ioredis', 'moment-timezone'] as $dependency) {
                if (empty($package['dependencies'][$dependency])) {
                    $missing[] = 'package_dependency:' . $dependency;
                }
            }

            if (empty($package['scripts']['start']) || strpos($package['scripts']['start'], 'node Server.js') === false) {
                $missing[] = 'package_script:start';
            }

            if (empty($package['scripts']['check:syntax'])) {
                $missing[] = 'package_script:check:syntax';
            }
        }

        $socketConfig = $this->jsonFile(base_path('deploy/websocket/socket_config2.production.example.json'));
        if (!$socketConfig) {
            $missing[] = 'json:deploy/websocket/socket_config2.production.example.json';
        } else {
            foreach ([
                'listen_port' => 12097,
                'listen_host' => '127.0.0.1',
                'ssl' => false,
                'health_path' => '/healthz',
                'ready_path' => '/readyz',
                'require_session_cookie' => true,
                'log_json' => true,
            ] as $key => $expected) {
                if (!array_key_exists($key, $socketConfig) || $socketConfig[$key] !== $expected) {
                    $missing[] = 'socket_config:' . $key;
                }
            }

            if (empty($socketConfig['allowed_origins']) || !is_array($socketConfig['allowed_origins'])) {
                $missing[] = 'socket_config:allowed_origins';
            }

            if (empty($socketConfig['auth_tokens_env'])) {
                $missing[] = 'socket_config:auth_tokens_env';
            }

            if (!empty($socketConfig['auth_tokens'])) {
                $missing[] = 'socket_config:no_inline_auth_tokens';
            }

            foreach (['max_connections', 'heartbeat_interval_ms', 'idle_timeout_ms'] as $key) {
                if (empty($socketConfig[$key]) || (int) $socketConfig[$key] <= 0) {
                    $missing[] = 'socket_config:' . $key;
                }
            }
        }

        $server = $this->fileContents(base_path('PTWebSocket/Server.js'));
        foreach ([
            'serverConfig.listen_port',
            'serverConfig.listen_host',
            '../public/socket_config2.json',
            'new WebSocket.Server',
            'verifyClient: verifyClient',
            'function allowedOrigin',
            'function tokenAllowed',
            'function healthResponse',
            'function validHandshakeMessage',
            'structuredLog',
            'ws.ping()',
            'websocket.handshake_invalid',
        ] as $needle) {
            if (strpos($server, $needle) === false) {
                $missing[] = 'server_js:' . $needle;
            }
        }

        foreach (['console.log(ck)', 'console.log(body)'] as $needle) {
            if (strpos($server, $needle) !== false) {
                $missing[] = 'server_js_raw_log:' . $needle;
            }
        }

        $nginx = $this->fileContents(base_path('deploy/nginx/bbb-b2b.conf.example'));
        foreach (['bbb_b2b_websocket', 'listen 12096 ssl', 'proxy_set_header Upgrade', 'proxy_set_header Origin', 'proxy_buffering off'] as $needle) {
            if (strpos($nginx, $needle) === false) {
                $missing[] = 'nginx_websocket:' . $needle;
            }
        }

        $healthcheck = $this->fileContents(base_path('deploy/scripts/healthcheck.sh'));
        foreach (['WEBSOCKET_TCP_HOST', 'WEBSOCKET_TCP_PORT', 'WEBSOCKET_HEALTH_URL', '/dev/tcp'] as $needle) {
            if (strpos($healthcheck, $needle) === false) {
                $missing[] = 'healthcheck_websocket:' . $needle;
            }
        }

        return [
            'name' => 'websocket_runtime',
            'status' => count($missing) === 0 ? 'pass' : ($production ? 'fail' : 'warn'),
            'message' => count($missing) === 0
                ? 'Node/WebSocket manifest, lockfile, proxy template, health probe, origin guard, heartbeat, and safe logging are present.'
                : 'Missing Node/WebSocket runtime release coverage: ' . implode(', ', $missing),
        ];
    }

    private function adminRbacCheck($production)
    {
        $requiredPermissions = [
            'b2b.operators.create',
            'b2b.operators.update',
            'b2b.operators.suspend',
            'b2b.credentials.rotate',
            'b2b.credentials.revoke',
            'b2b.wallet.manual_action',
            'b2b.cases.view',
            'b2b.cases.manage',
            'b2b.payloads.view_redacted',
            'b2b.payloads.view_raw',
            'b2b.settlements.submit',
            'b2b.settlements.approve',
            'b2b.audit.view',
        ];
        $requiredActions = [
            'operator.create' => 'b2b.operators.create',
            'operator.update' => 'b2b.operators.update',
            'operator.suspend' => 'b2b.operators.suspend',
            'operator.resume' => 'b2b.operators.suspend',
            'api_key.rotate' => 'b2b.credentials.rotate',
            'api_key.revoke' => 'b2b.credentials.revoke',
            'wallet.manual_action' => 'b2b.wallet.manual_action',
            'payload.view_raw' => 'b2b.payloads.view_raw',
            'case.claim' => 'b2b.cases.manage',
            'case.resolve' => 'b2b.cases.manage',
            'case.reopen' => 'b2b.cases.manage',
            'support_ticket.comment' => 'b2b.cases.manage',
            'support_ticket.close' => 'b2b.cases.manage',
            'support_ticket.reopen' => 'b2b.cases.manage',
            'settlement.submit' => 'b2b.settlements.submit',
            'settlement.approve' => 'b2b.settlements.approve',
            'settlement.reject' => 'b2b.settlements.approve',
        ];

        $permissions = config('b2b_admin.permissions', []);
        $actions = config('b2b_admin.privileged_actions', []);
        $missing = [];

        foreach ($requiredPermissions as $permission) {
            if (!array_key_exists($permission, $permissions)) {
                $missing[] = 'permission:' . $permission;
            }
        }

        foreach ($requiredActions as $action => $permission) {
            if (!isset($actions[$action])) {
                $missing[] = 'action:' . $action;
                continue;
            }

            if (!isset($actions[$action]['permission']) || $actions[$action]['permission'] !== $permission) {
                $missing[] = 'action_permission:' . $action;
            }
            if (empty($actions[$action]['step_up']) || empty($actions[$action]['confirm'])) {
                $missing[] = 'action_step_up:' . $action;
            }
        }

        return [
            'name' => 'admin_rbac_config',
            'status' => count($missing) === 0 ? 'pass' : ($production ? 'fail' : 'warn'),
            'message' => count($missing) === 0
                ? 'B2B admin RBAC and privileged step-up configuration is present.'
                : 'Missing B2B admin RBAC configuration: ' . implode(', ', $missing),
        ];
    }

    private function webSurfacesCheck($production)
    {
        $missing = [];

        if (!Route::has('backend.b2b.dashboard')) {
            $missing[] = 'route:backend.b2b.dashboard';
        }

        if ($this->routeMiddlewareClass('b2b.admin') !== 'VanguardLTE\Http\Middleware\AuthorizeB2BAdminPermission') {
            $missing[] = 'middleware:b2b.admin';
        }

        if (!$this->routeUsesMiddleware('backend.b2b.dashboard', 'b2b.admin:b2b.reports.view')) {
            $missing[] = 'route_middleware:backend.b2b.dashboard:b2b.admin';
        }

        if (!View::exists('backend.b2b.dashboard')) {
            $missing[] = 'view:backend.b2b.dashboard';
        }

        foreach (['backend.b2b.wallet_manual_actions.index', 'backend.b2b.wallet_manual_actions.store'] as $routeName) {
            if (!Route::has($routeName)) {
                $missing[] = 'route:' . $routeName;
            }
        }

        if (!$this->routeUsesMiddleware('backend.b2b.wallet_manual_actions.index', 'b2b.admin:b2b.wallet.manual_action')) {
            $missing[] = 'route_middleware:backend.b2b.wallet_manual_actions.index:b2b.admin';
        }

        if (!$this->routeUsesMiddleware('backend.b2b.wallet_manual_actions.store', 'b2b.admin:b2b.wallet.manual_action')) {
            $missing[] = 'route_middleware:backend.b2b.wallet_manual_actions.store:b2b.admin';
        }

        if (!$this->routeUsesMiddleware('backend.b2b.wallet_manual_actions.store', 'b2b.web_step_up:wallet.manual_action')) {
            $missing[] = 'route_middleware:backend.b2b.wallet_manual_actions.store:b2b.web_step_up';
        }

        if (!View::exists('backend.b2b.wallet-manual-actions')) {
            $missing[] = 'view:backend.b2b.wallet-manual-actions';
        }

        foreach (['backend.b2b.settlements.index', 'backend.b2b.settlements.submit', 'backend.b2b.settlements.approve', 'backend.b2b.settlements.reject'] as $routeName) {
            if (!Route::has($routeName)) {
                $missing[] = 'route:' . $routeName;
            }
        }

        if (!$this->routeUsesMiddleware('backend.b2b.settlements.index', 'b2b.admin:b2b.reports.view')) {
            $missing[] = 'route_middleware:backend.b2b.settlements.index:b2b.admin';
        }

        if (!$this->routeUsesMiddleware('backend.b2b.settlements.submit', 'b2b.admin:b2b.settlements.submit')) {
            $missing[] = 'route_middleware:backend.b2b.settlements.submit:b2b.admin';
        }

        if (!$this->routeUsesMiddleware('backend.b2b.settlements.submit', 'b2b.web_step_up:settlement.submit')) {
            $missing[] = 'route_middleware:backend.b2b.settlements.submit:b2b.web_step_up';
        }

        if (!$this->routeUsesMiddleware('backend.b2b.settlements.approve', 'b2b.admin:b2b.settlements.approve')) {
            $missing[] = 'route_middleware:backend.b2b.settlements.approve:b2b.admin';
        }

        if (!$this->routeUsesMiddleware('backend.b2b.settlements.approve', 'b2b.web_step_up:settlement.approve')) {
            $missing[] = 'route_middleware:backend.b2b.settlements.approve:b2b.web_step_up';
        }

        if (!$this->routeUsesMiddleware('backend.b2b.settlements.reject', 'b2b.admin:b2b.settlements.approve')) {
            $missing[] = 'route_middleware:backend.b2b.settlements.reject:b2b.admin';
        }

        if (!$this->routeUsesMiddleware('backend.b2b.settlements.reject', 'b2b.web_step_up:settlement.reject')) {
            $missing[] = 'route_middleware:backend.b2b.settlements.reject:b2b.web_step_up';
        }

        if (!View::exists('backend.b2b.settlements')) {
            $missing[] = 'view:backend.b2b.settlements';
        }

        foreach (['backend.b2b.credentials.index', 'backend.b2b.credentials.rotate', 'backend.b2b.credentials.revoke'] as $routeName) {
            if (!Route::has($routeName)) {
                $missing[] = 'route:' . $routeName;
            }
        }

        if (!$this->routeUsesMiddleware('backend.b2b.credentials.index', 'b2b.admin:b2b.credentials.rotate')) {
            $missing[] = 'route_middleware:backend.b2b.credentials.index:b2b.admin';
        }

        if (!$this->routeUsesMiddleware('backend.b2b.credentials.rotate', 'b2b.admin:b2b.credentials.rotate')) {
            $missing[] = 'route_middleware:backend.b2b.credentials.rotate:b2b.admin';
        }

        if (!$this->routeUsesMiddleware('backend.b2b.credentials.rotate', 'b2b.web_step_up:api_key.rotate')) {
            $missing[] = 'route_middleware:backend.b2b.credentials.rotate:b2b.web_step_up';
        }

        if (!$this->routeUsesMiddleware('backend.b2b.credentials.revoke', 'b2b.admin:b2b.credentials.revoke')) {
            $missing[] = 'route_middleware:backend.b2b.credentials.revoke:b2b.admin';
        }

        if (!$this->routeUsesMiddleware('backend.b2b.credentials.revoke', 'b2b.web_step_up:api_key.revoke')) {
            $missing[] = 'route_middleware:backend.b2b.credentials.revoke:b2b.web_step_up';
        }

        if (!View::exists('backend.b2b.credentials')) {
            $missing[] = 'view:backend.b2b.credentials';
        }

        foreach (['backend.b2b.operators.index', 'backend.b2b.operators.update', 'backend.b2b.operators.suspend', 'backend.b2b.operators.resume'] as $routeName) {
            if (!Route::has($routeName)) {
                $missing[] = 'route:' . $routeName;
            }
        }

        if (!$this->routeUsesMiddleware('backend.b2b.operators.index', 'b2b.admin:b2b.operators.update')) {
            $missing[] = 'route_middleware:backend.b2b.operators.index:b2b.admin';
        }

        if (!$this->routeUsesMiddleware('backend.b2b.operators.update', 'b2b.admin:b2b.operators.update')) {
            $missing[] = 'route_middleware:backend.b2b.operators.update:b2b.admin';
        }

        if (!$this->routeUsesMiddleware('backend.b2b.operators.update', 'b2b.web_step_up:operator.update')) {
            $missing[] = 'route_middleware:backend.b2b.operators.update:b2b.web_step_up';
        }

        if (!$this->routeUsesMiddleware('backend.b2b.operators.suspend', 'b2b.admin:b2b.operators.suspend')) {
            $missing[] = 'route_middleware:backend.b2b.operators.suspend:b2b.admin';
        }

        if (!$this->routeUsesMiddleware('backend.b2b.operators.suspend', 'b2b.web_step_up:operator.suspend')) {
            $missing[] = 'route_middleware:backend.b2b.operators.suspend:b2b.web_step_up';
        }

        if (!$this->routeUsesMiddleware('backend.b2b.operators.resume', 'b2b.admin:b2b.operators.suspend')) {
            $missing[] = 'route_middleware:backend.b2b.operators.resume:b2b.admin';
        }

        if (!$this->routeUsesMiddleware('backend.b2b.operators.resume', 'b2b.web_step_up:operator.resume')) {
            $missing[] = 'route_middleware:backend.b2b.operators.resume:b2b.web_step_up';
        }

        if (!View::exists('backend.b2b.operators')) {
            $missing[] = 'view:backend.b2b.operators';
        }

        foreach (['backend.b2b.payloads.index', 'backend.b2b.payloads.raw'] as $routeName) {
            if (!Route::has($routeName)) {
                $missing[] = 'route:' . $routeName;
            }
        }

        if (!$this->routeUsesMiddleware('backend.b2b.payloads.index', 'b2b.admin:b2b.payloads.view_redacted')) {
            $missing[] = 'route_middleware:backend.b2b.payloads.index:b2b.admin';
        }

        if (!$this->routeUsesMiddleware('backend.b2b.payloads.raw', 'b2b.admin:b2b.payloads.view_raw')) {
            $missing[] = 'route_middleware:backend.b2b.payloads.raw:b2b.admin';
        }

        if (!$this->routeUsesMiddleware('backend.b2b.payloads.raw', 'b2b.web_step_up:payload.view_raw')) {
            $missing[] = 'route_middleware:backend.b2b.payloads.raw:b2b.web_step_up';
        }

        if (!View::exists('backend.b2b.payloads')) {
            $missing[] = 'view:backend.b2b.payloads';
        }

        foreach ([
            'backend.b2b.cases.index',
            'backend.b2b.cases.claim',
            'backend.b2b.cases.resolve',
            'backend.b2b.cases.reopen',
            'backend.b2b.cases.support_ticket.comment',
            'backend.b2b.cases.support_ticket.close',
            'backend.b2b.cases.support_ticket.reopen',
        ] as $routeName) {
            if (!Route::has($routeName)) {
                $missing[] = 'route:' . $routeName;
            }
        }

        if (!$this->routeUsesMiddleware('backend.b2b.cases.index', 'b2b.admin:b2b.cases.view')) {
            $missing[] = 'route_middleware:backend.b2b.cases.index:b2b.admin';
        }

        foreach ([
            'backend.b2b.cases.claim' => 'case.claim',
            'backend.b2b.cases.resolve' => 'case.resolve',
            'backend.b2b.cases.reopen' => 'case.reopen',
            'backend.b2b.cases.support_ticket.comment' => 'support_ticket.comment',
            'backend.b2b.cases.support_ticket.close' => 'support_ticket.close',
            'backend.b2b.cases.support_ticket.reopen' => 'support_ticket.reopen',
        ] as $routeName => $stepUpAction) {
            if (!$this->routeUsesMiddleware($routeName, 'b2b.admin:b2b.cases.manage')) {
                $missing[] = 'route_middleware:' . $routeName . ':b2b.admin';
            }

            if (!$this->routeUsesMiddleware($routeName, 'b2b.web_step_up:' . $stepUpAction)) {
                $missing[] = 'route_middleware:' . $routeName . ':b2b.web_step_up';
            }
        }

        if (!View::exists('backend.b2b.cases')) {
            $missing[] = 'view:backend.b2b.cases';
        }

        if (!Route::has('backend.b2b.audit.index')) {
            $missing[] = 'route:backend.b2b.audit.index';
        }

        if (!$this->routeUsesMiddleware('backend.b2b.audit.index', 'b2b.admin:b2b.audit.view')) {
            $missing[] = 'route_middleware:backend.b2b.audit.index:b2b.admin';
        }

        if (!View::exists('backend.b2b.audit')) {
            $missing[] = 'view:backend.b2b.audit';
        }

        foreach (['backend.b2b.step_up.show', 'backend.b2b.step_up.store'] as $routeName) {
            if (!Route::has($routeName)) {
                $missing[] = 'route:' . $routeName;
            }
        }

        if (!View::exists('backend.b2b.step-up')) {
            $missing[] = 'view:backend.b2b.step-up';
        }

        if (!View::exists('b2b.operator-portal.overview')) {
            $missing[] = 'view:b2b.operator-portal.overview';
        }

        if (!View::exists('b2b.operator-portal.section')) {
            $missing[] = 'view:b2b.operator-portal.section';
        }

        if ($this->routeMiddlewareClass('b2b.web_step_up') !== 'VanguardLTE\Http\Middleware\RequireB2BWebStepUp') {
            $missing[] = 'middleware:b2b.web_step_up';
        }

        foreach ([
            'api/b2b/v1/readiness',
            'api/b2b/v1/metrics',
            'api/b2b/v1/portal',
            'api/b2b/v1/portal/overview',
            'api/b2b/v1/portal/credentials',
            'api/b2b/v1/portal/games',
            'api/b2b/v1/portal/sessions',
            'api/b2b/v1/portal/transactions',
            'api/b2b/v1/portal/settlements',
            'api/b2b/v1/portal/cases',
            'api/b2b/v1/portal/callbacks',
            'api/b2b/v1/portal/reports',
            'api/b2b/v1/portal/support',
            'api/b2b/v1/portal/docs',
            'api/b2b/v1/games/{game_uid}',
        ] as $uri) {
            if (!$this->routeExists('GET', $uri)) {
                $missing[] = 'route:' . $uri;
            }
        }

        if (!$this->routeExists('POST', 'api/b2b/v1/portal/support/cases/{transaction_uid}/comments')) {
            $missing[] = 'route:api/b2b/v1/portal/support/cases/{transaction_uid}/comments';
        }

        foreach ([
            'api/b2b/v1/portal/support/tickets',
            'api/b2b/v1/portal/support/tickets/{ticket_uid}/comments',
            'api/b2b/v1/portal/support/tickets/{ticket_uid}/close',
        ] as $uri) {
            if (!$this->routeExists('POST', $uri)) {
                $missing[] = 'route:' . $uri;
            }
        }

        return [
            'name' => 'web_surfaces',
            'status' => count($missing) === 0 ? 'pass' : ($production ? 'fail' : 'warn'),
            'message' => count($missing) === 0
                ? 'B2B backend, operator portal, web step-up, readiness, and metrics web surfaces are registered.'
                : 'Missing B2B web surfaces: ' . implode(', ', $missing),
        ];
    }

    private function laravelSecurityMitigationsCheck($production)
    {
        $missing = [];

        if (!$this->hardenedValidatorRejectsCrlfEmail()) {
            $missing[] = 'validator:email_crlf_mitigation';
        }

        if (!$this->hardenedValidatorBlocksPhp8UploadExtension()) {
            $missing[] = 'validator:php8_upload_extension_mitigation';
        }

        if ($this->routeMiddlewareClass('signed') === 'Illuminate\Routing\Middleware\ValidateSignature') {
            $missing[] = 'middleware_alias:signed';
        }

        if ($this->usesLaravelSignedRouteMiddleware()) {
            $missing[] = 'route_middleware:signed';
        }

        if ($this->usesLaravelTemporarySignedUrls()) {
            $missing[] = 'temporary_signed_urls';
        }

        return [
            'name' => 'laravel_security_mitigations',
            'status' => count($missing) === 0 ? 'pass' : ($production ? 'fail' : 'warn'),
            'message' => count($missing) === 0
                ? 'Laravel advisory mitigations are active for email validation, PHP upload extensions, signed-route exposure, and temporary signed URL exposure.'
                : 'Missing Laravel advisory mitigations: ' . implode(', ', $missing),
        ];
    }

    private function hardenedValidatorRejectsCrlfEmail()
    {
        try {
            $validator = app('validator')->make(
                ['email' => "ops@example.test\r\nBcc: attacker@example.test"],
                ['email' => 'email']
            );

            return $validator instanceof SecurityHardenedValidator && $validator->fails();
        } catch (\Exception $e) {
            return false;
        }
    }

    private function hardenedValidatorBlocksPhp8UploadExtension()
    {
        $path = tempnam(sys_get_temp_dir(), 'bbb-upload-check-');
        if ($path === false) {
            return false;
        }

        try {
            file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAFgwJ/lUcQGQAAAABJRU5ErkJggg=='));
            $file = new \Illuminate\Http\UploadedFile($path, 'avatar.php8', 'image/png', null, true);
            $validator = app('validator')->make(['file' => $file], ['file' => 'file|mimes:png']);

            return $validator instanceof SecurityHardenedValidator && $validator->fails();
        } catch (\Exception $e) {
            return false;
        } finally {
            @unlink($path);
        }
    }

    private function usesLaravelSignedRouteMiddleware()
    {
        foreach (Route::getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if ($this->isLaravelSignedMiddlewareReference($middleware)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isLaravelSignedMiddlewareReference($middleware)
    {
        if (!is_string($middleware)) {
            return false;
        }

        $middleware = ltrim($middleware, '\\');

        return $middleware === 'signed'
            || strpos($middleware, 'signed:') === 0
            || $middleware === 'Illuminate\Routing\Middleware\ValidateSignature'
            || strpos($middleware, 'Illuminate\Routing\Middleware\ValidateSignature:') === 0;
    }

    protected function routeMiddlewareClass($alias)
    {
        $routerMiddleware = app('router')->getMiddleware();
        if (isset($routerMiddleware[$alias]) && is_string($routerMiddleware[$alias])) {
            return ltrim($routerMiddleware[$alias], '\\');
        }

        try {
            $kernel = app(\Illuminate\Contracts\Http\Kernel::class);
            $reflection = new \ReflectionClass($kernel);
            if (!$reflection->hasProperty('routeMiddleware')) {
                return null;
            }

            $property = $reflection->getProperty('routeMiddleware');
            $property->setAccessible(true);
            $middleware = $property->getValue($kernel);
            if (is_array($middleware) && isset($middleware[$alias]) && is_string($middleware[$alias])) {
                return ltrim($middleware[$alias], '\\');
            }
        } catch (\Exception $e) {
            return null;
        }

        return null;
    }

    protected function usesLaravelTemporarySignedUrls()
    {
        $patterns = [
            'temporarySignedRoute',
            'temporaryUrl',
            'temporaryUploadUrl',
            'buildTemporaryUrlsUsing',
        ];

        foreach ($this->firstPartySourceFiles() as $path) {
            $contents = @file_get_contents($path);
            if ($contents === false) {
                continue;
            }

            foreach ($patterns as $pattern) {
                if (strpos($contents, $pattern) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    private function firstPartySourceFiles()
    {
        $roots = [
            'app/B2B',
            'app/Console',
            'app/Exceptions',
            'app/Http',
            'app/Jobs',
            'app/Lib',
            'app/Providers',
            'app/Repositories',
            'app/Services',
            'app/Support',
            'config',
            'resources/views',
            'routes',
        ];
        $self = realpath(__FILE__);

        foreach ($roots as $root) {
            $rootPath = base_path($root);
            if (!is_dir($rootPath)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($rootPath, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }

                $path = $file->getPathname();
                $realPath = realpath($path);
                if ($realPath === $self) {
                    continue;
                }

                if (preg_match('/(\.php|\.blade\.php)$/', $path) !== 1) {
                    continue;
                }

                yield $path;
            }
        }
    }

    private function jsonFile($path)
    {
        if (!file_exists($path)) {
            return null;
        }

        $decoded = json_decode(file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    private function fileContents($path)
    {
        return file_exists($path) ? file_get_contents($path) : '';
    }

    private function routeExists($method, $uri)
    {
        foreach (Route::getRoutes() as $route) {
            if ($route->uri() === $uri && in_array($method, $route->methods(), true)) {
                return true;
            }
        }

        return false;
    }

    private function routeUsesMiddleware($name, $middleware)
    {
        $route = Route::getRoutes()->getByName($name);
        if (!$route) {
            return false;
        }

        return in_array($middleware, $route->gatherMiddleware(), true);
    }

    private function dependencyAuditCheck($production)
    {
        if (!file_exists(base_path('composer.lock'))) {
            return [
                'name' => 'dependency_audit',
                'status' => $production ? 'fail' : 'warn',
                'message' => 'composer.lock is missing; dependency advisories cannot be verified.',
            ];
        }

        $result = $this->runDependencyAuditCommand();
        $payload = json_decode($result['output'], true);

        if (!is_array($payload)) {
            return [
                'name' => 'dependency_audit',
                'status' => $production ? 'fail' : 'warn',
                'message' => 'Composer locked dependency audit could not be parsed: ' . trim($result['error'] ?: $result['output']),
            ];
        }

        $advisoryPackages = isset($payload['advisories']) && is_array($payload['advisories'])
            ? array_keys($payload['advisories'])
            : [];
        $advisoryCount = 0;
        foreach ($advisoryPackages as $package) {
            $advisoryCount += is_array($payload['advisories'][$package]) ? count($payload['advisories'][$package]) : 0;
        }

        $abandonedPackages = isset($payload['abandoned']) && is_array($payload['abandoned'])
            ? array_keys($payload['abandoned'])
            : [];
        $abandonedCount = count($abandonedPackages);

        if ($advisoryCount === 0 && $abandonedCount === 0) {
            return [
                'name' => 'dependency_audit',
                'status' => 'pass',
                'message' => 'Composer locked dependency audit has no advisories or abandoned packages.',
            ];
        }

        $parts = [];
        if ($advisoryCount > 0) {
            $parts[] = $advisoryCount . ' advisories across ' . count($advisoryPackages) . ' packages: ' . implode(', ', $advisoryPackages);
        }
        if ($abandonedCount > 0) {
            $parts[] = $abandonedCount . ' abandoned packages: ' . implode(', ', $abandonedPackages);
        }

        return [
            'name' => 'dependency_audit',
            'status' => $production ? 'fail' : 'warn',
            'message' => 'Composer locked dependency audit found ' . implode('; ', $parts) . '.',
        ];
    }

    protected function runDependencyAuditCommand()
    {
        $process = Process::fromShellCommandline('composer audit --locked --no-dev --format=json --abandoned=report', base_path());
        $process->setTimeout(120);
        $process->run();

        return [
            'exit_code' => $process->getExitCode(),
            'output' => $process->getOutput(),
            'error' => $process->getErrorOutput(),
        ];
    }
}
