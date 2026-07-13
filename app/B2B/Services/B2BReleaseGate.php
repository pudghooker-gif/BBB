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
            $this->cacheStoreCheck('game_catalog_cache', config('b2b.game_catalog_cache_store') ?: config('cache.default'), $production),
            $this->booleanCheck('game_catalog_cache_enabled', (bool) config('b2b.game_catalog_cache_enabled', true), $production, 'B2B game catalog cache must be enabled in production.'),
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
            $this->providerHealthSurfaceCheck($production),
            $this->databaseSchemaCheck($production),
            $this->gameCatalogSyncCheck($production),
            $this->launcherIntegrationCheck($production),
            $this->payloadRedactionAuditCheck($production),
            $this->apiKeyScopeCheck($production),
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
            $checks[] = $this->webSocketDependencyAuditCheck($production);
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

                $capabilities = $provider->capabilities();
                if (!is_array($capabilities) || count($capabilities) === 0) {
                    $missing[] = 'capabilities:' . $label;
                }

                foreach ($this->requiredProviderCapabilities() as $capability => $allowedStates) {
                    $state = $provider->capability($capability);
                    if (!in_array($state, $allowedStates, true)) {
                        $missing[] = 'capability:' . $label . ':' . $capability . ':' . $state;
                    }
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
                ? 'Provider capabilities and wallet action contracts are explicit for catalog, launch, mutation, status lookup, and rollback recovery flows.'
                : 'Missing provider adapter contract coverage: ' . implode(', ', $missing),
        ];
    }

    protected function walletContractProviders()
    {
        return [
            app(GoldsvetInternalProvider::class),
        ];
    }

    private function providerHealthSurfaceCheck($production)
    {
        $missing = [];

        $service = $this->fileContents(base_path('app/B2B/Services/B2BProviderHealthService.php'));
        foreach (['summary', 'providers', 'safeHealth', 'GoldsvetInternalProvider'] as $needle) {
            if (strpos($service, $needle) === false) {
                $missing[] = 'provider_health_service:' . $needle;
            }
        }

        $readiness = $this->fileContents(base_path('app/B2B/Services/B2BReadinessService.php'));
        foreach (['providerHealthCheck', 'provider_health', 'B2BProviderHealthService', 'game_catalog_cache_store'] as $needle) {
            if (strpos($readiness, $needle) === false) {
                $missing[] = 'readiness:' . $needle;
            }
        }

        $metrics = $this->fileContents(base_path('app/B2B/Services/B2BMetricsExporter.php'));
        foreach (['providerHealth', 'bbb_b2b_provider_health_up', 'B2BProviderHealthService'] as $needle) {
            if (strpos($metrics, $needle) === false) {
                $missing[] = 'metrics:' . $needle;
            }
        }

        $portal = $this->fileContents(base_path('app/B2B/Services/B2BOperatorPortalQuery.php'));
        foreach (['provider_health', 'B2BProviderHealthService'] as $needle) {
            if (strpos($portal, $needle) === false) {
                $missing[] = 'portal:' . $needle;
            }
        }

        foreach ([
            'resources/views/b2b/operator-portal/overview.blade.php',
            'resources/views/b2b/operator-portal/section.blade.php',
        ] as $path) {
            $view = $this->fileContents(base_path($path));
            foreach (['Provider Health', 'provider_health', 'games_table_available'] as $needle) {
                if (strpos($view, $needle) === false) {
                    $missing[] = $path . ':' . $needle;
                }
            }
        }

        $backofficeQuery = $this->fileContents(base_path('app/B2B/Services/B2BBackofficeDashboardQuery.php'));
        foreach (['provider_health', 'B2BProviderHealthService'] as $needle) {
            if (strpos($backofficeQuery, $needle) === false) {
                $missing[] = 'backoffice_dashboard_query:' . $needle;
            }
        }

        $backofficeView = $this->fileContents(base_path('resources/views/backend/b2b/dashboard.blade.php'));
        foreach (['Provider Health', 'provider_health', 'games_table_available'] as $needle) {
            if (strpos($backofficeView, $needle) === false) {
                $missing[] = 'backoffice_dashboard_view:' . $needle;
            }
        }

        foreach ([
            'deploy/scripts/healthcheck.sh' => [
                'assert_readiness_check_pass',
                'provider_health',
                'bbb_b2b_provider_health_up',
                'readiness_provider_health',
                'metrics_provider_health',
            ],
            'deploy/scripts/b2b-smoke.sh' => [
                'assert_readiness_check_pass',
                'provider_health',
                'bbb_b2b_provider_health_up',
                'portal-overview-provider-health',
                'goldsvet_internal',
            ],
            'deploy/k6/b2b-smoke-load.js' => [
                'readiness provider health passes',
                'metrics exposes provider health',
                'portal overview provider health',
                'bbb_b2b_provider_health_up',
            ],
            'deploy/scripts/prometheus-smoke.sh' => [
                'bbb_b2b_provider_health_up',
                'BBBB2BProviderHealthDown',
            ],
        ] as $path => $needles) {
            $contents = $this->fileContents(base_path($path));
            foreach ($needles as $needle) {
                if (strpos($contents, $needle) === false) {
                    $missing[] = 'provider_health_evidence:' . $path . ':' . $needle;
                }
            }
        }

        foreach ([
            'tests/Feature/B2BReadinessEndpointTest.php' => ['provider_health', 'testReadinessEndpointFailsWhenProviderHealthFails'],
            'tests/Feature/B2BMetricsEndpointTest.php' => ['bbb_b2b_provider_health_up'],
            'tests/Feature/B2BOperatorPortalTest.php' => ['data.provider_health.ok'],
            'tests/Feature/B2BBackofficeRouteTest.php' => ['Provider Health', 'goldsvet_internal'],
            'tests/Unit/B2BDeploymentArtifactsTest.php' => [
                'readiness_provider_health',
                'metrics-provider-health',
                'portal-overview-provider-health',
                'readiness provider health passes',
            ],
        ] as $path => $needles) {
            $contents = $this->fileContents(base_path($path));
            foreach ($needles as $needle) {
                if (strpos($contents, $needle) === false) {
                    $missing[] = $path . ':' . $needle;
                }
            }
        }

        return [
            'name' => 'provider_health_surfaces',
            'status' => count($missing) === 0 ? 'pass' : ($production ? 'fail' : 'warn'),
            'message' => count($missing) === 0
                ? 'Provider health is surfaced through readiness, metrics, signed operator portal, backend dashboard, and release evidence tooling without secrets.'
                : 'Missing provider health surface coverage: ' . implode(', ', $missing),
        ];
    }

    private function requiredWalletActions()
    {
        return ['balance', 'bet', 'win', 'refund', 'rollback', 'transaction_status'];
    }

    private function requiredProviderCapabilities()
    {
        return [
            'list_games' => [GameProviderInterface::CAPABILITY_SUPPORTED],
            'sync_games' => [GameProviderInterface::CAPABILITY_SUPPORTED],
            'launch' => [GameProviderInterface::CAPABILITY_SUPPORTED],
            'validate_incoming_request' => [GameProviderInterface::CAPABILITY_SUPPORTED],
            'normalize_transaction' => [GameProviderInterface::CAPABILITY_SUPPORTED],
            'balance' => [GameProviderInterface::CAPABILITY_SUPPORTED],
            'bet' => [GameProviderInterface::CAPABILITY_SUPPORTED],
            'win' => [GameProviderInterface::CAPABILITY_SUPPORTED],
            'refund' => [GameProviderInterface::CAPABILITY_SUPPORTED],
            'rollback' => [GameProviderInterface::CAPABILITY_SUPPORTED],
            'transaction_status' => [GameProviderInterface::CAPABILITY_SUPPORTED],
            'close_session' => [GameProviderInterface::CAPABILITY_SUPPORTED],
            'close_round' => [
                GameProviderInterface::CAPABILITY_SUPPORTED,
                GameProviderInterface::CAPABILITY_NOT_APPLICABLE,
            ],
            'health' => [
                GameProviderInterface::CAPABILITY_SUPPORTED,
                GameProviderInterface::CAPABILITY_DEGRADED,
            ],
        ];
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
            'deploy/scripts/backup-offhost-verify.sh',
            'deploy/scripts/restore.sh',
            'deploy/scripts/rollback.sh',
            'deploy/scripts/healthcheck.sh',
            'deploy/scripts/final-topology-check.sh',
            'deploy/scripts/queue-runtime-drill.sh',
            'deploy/scripts/migration-rehearsal.sh',
            'deploy/scripts/b2b-smoke.sh',
            'deploy/scripts/alertmanager-smoke.sh',
            'deploy/scripts/alertmanager-receiver-check.sh',
            'deploy/scripts/prometheus-smoke.sh',
            'deploy/scripts/log-shipping-external-check.sh',
            'PTWebSocket/scripts/public-proxy-smoke.js',
            'deploy/k6/b2b-smoke-load.js',
            'deploy/evidence/release-evidence.example.json',
            'deploy/evidence/provider-credential-approval-redacted.example.txt',
            'deploy/evidence/provider-wallet-contract-certification-redacted.example.txt',
            'deploy/evidence/legal-launch-approval-redacted.example.txt',
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

        $console = $this->fileContents(base_path('routes/b2b_console.php'));
        foreach (['b2b:evidence-template', 'b2b:evidence-check', 'b2b:evidence-hash', 'b2b:queue-runtime-evidence', 'b2b:log-shipping-check', 'b2b:correlation-evidence', 'B2BReleaseEvidenceChecker'] as $needle) {
            if (strpos($console, $needle) === false) {
                $missing[] = 'console:' . $needle;
            }
        }

        foreach (['b2b_wallet_transaction_attempts', 'b2b_wallet_callback_logs', 'b2b_provider_requests'] as $needle) {
            if (strpos($console, $needle) === false) {
                $missing[] = 'correlation_evidence:' . $needle;
            }
        }

        $template = $this->jsonFile(base_path('deploy/evidence/release-evidence.example.json'));
        if (!$template || empty($template['evidence']) || !is_array($template['evidence'])) {
            $missing[] = 'evidence_template:release-evidence.example.json';
        } else {
            try {
                foreach (app(B2BReleaseEvidenceChecker::class)->requiredEvidence() as $key => $requirement) {
                    if (!array_key_exists($key, $template['evidence'])) {
                        $missing[] = 'evidence_template:' . $key;
                        continue;
                    }

                    $entry = is_array($template['evidence'][$key]) ? $template['evidence'][$key] : [];
                    if (!empty($entry['artifacts']) && is_array($entry['artifacts'])) {
                        if (empty($entry['artifact_hashes']) || !is_array($entry['artifact_hashes'])) {
                            $missing[] = 'evidence_template_hashes:' . $key;
                            continue;
                        }

                        foreach ($entry['artifacts'] as $artifact) {
                            if (!is_string($artifact) || !isset($entry['artifact_hashes'][$artifact])) {
                                $missing[] = 'evidence_template_hash:' . $key . ':' . (string) $artifact;
                            }
                        }
                    } elseif (!empty($entry['artifact']) && empty($entry['sha256'])) {
                        $missing[] = 'evidence_template_hash:' . $key;
                    }
                }
            } catch (\Throwable $e) {
                $missing[] = 'evidence_template:checker';
            }
        }

        $workflow = $this->fileContents(base_path('.github/workflows/b2b-release.yml'));
        foreach (['Syntax lint deployment shell scripts', 'bash -n', 'Verify clean and repeatable migrations', 'migrate:fresh --force --no-interaction', 'Nothing to migrate', 'Verify release evidence template generation', 'b2b:evidence-template', 'queue_runtime_drill'] as $needle) {
            if (strpos($workflow, $needle) === false) {
                $missing[] = 'workflow_release_verification:' . $needle;
            }
        }

        foreach ([
            'deploy/evidence/provider-credential-approval-redacted.example.txt' => ['approved_by:', 'credential_storage_reference:', 'Do not include API keys'],
            'deploy/evidence/provider-wallet-contract-certification-redacted.example.txt' => ['approved_by:', 'wallet_actions_certified:', 'Do not include provider API secrets'],
            'deploy/evidence/legal-launch-approval-redacted.example.txt' => ['approved_by:', 'approval_reference:', 'Do not include full contracts'],
        ] as $path => $needles) {
            $approvalTemplate = $this->fileContents(base_path($path));
            foreach ($needles as $needle) {
                if (strpos($approvalTemplate, $needle) === false) {
                    $missing[] = 'approval_template:' . $path . ':' . $needle;
                }
            }
        }

        $runbook = $this->fileContents(base_path('docs/deployment/PRODUCTION_RUNBOOK.md'));
        foreach (['b2b:evidence-template', 'b2b:evidence-check', 'b2b:queue-runtime-evidence', 'b2b:log-shipping-check', 'b2b:correlation-evidence', 'release-evidence.json', 'b2b-queue-runtime-evidence.json', 'b2b-correlation-validation.json', 'provider certification', 'legal approval'] as $needle) {
            if (strpos($runbook, $needle) === false) {
                $missing[] = 'runbook_evidence:' . $needle;
            }
        }

        $migrationRehearsal = $this->fileContents(base_path('deploy/scripts/migration-rehearsal.sh'));
        foreach (['MIGRATION_REHEARSAL_ARTIFACT_DIR', 'ARTIFACT_DIR', 'b2b-migration-rehearsal-'] as $needle) {
            if (strpos($migrationRehearsal, $needle) === false) {
                $missing[] = 'migration_rehearsal_evidence:' . $needle;
            }
        }

        $healthcheck = $this->fileContents(base_path('deploy/scripts/healthcheck.sh'));
        foreach (['HEALTHCHECK_ARTIFACT_DIR', 'b2b-healthcheck-', 'b2b-release-check-'] as $needle) {
            if (strpos($healthcheck, $needle) === false) {
                $missing[] = 'healthcheck_evidence:' . $needle;
            }
        }

        $finalTopology = $this->fileContents(base_path('deploy/scripts/final-topology-check.sh'));
        foreach (['FINAL_TOPOLOGY_ARTIFACT_DIR', 'final-domains-tls-proxy-redis-queue-scheduler-validation.log', 'trustedproxy.proxies', 'b2b:scheduler-heartbeat', 'b2b:release-check --production', 'WEBSOCKET_PUBLIC_URL'] as $needle) {
            if (strpos($finalTopology, $needle) === false) {
                $missing[] = 'final_topology_evidence:' . $needle;
            }
        }

        $queueRuntimeDrill = $this->fileContents(base_path('deploy/scripts/queue-runtime-drill.sh'));
        foreach (['set -euo pipefail', 'QUEUE_RUNTIME_ARTIFACT_DIR', 'b2b-queue-runtime-drill.log', 'b2b-queue-runtime-evidence.json', 'b2b:queue-runtime-evidence', 'supervisorctl', 'b2b:scheduler-heartbeat'] as $needle) {
            if (strpos($queueRuntimeDrill, $needle) === false) {
                $missing[] = 'queue_runtime_drill:' . $needle;
            }
        }

        $smoke = $this->fileContents(base_path('deploy/scripts/b2b-smoke.sh'));
        foreach (['B2B_SMOKE_ARTIFACT_DIR', 'b2b-smoke-', 'SMOKE_LOG', '/api/b2b/v1/portal/docs/openapi.json', '/api/b2b/v1/portal/docs/postman_collection.json', 'assert_not_contains'] as $needle) {
            if (strpos($smoke, $needle) === false) {
                $missing[] = 'smoke_evidence:' . $needle;
            }
        }

        $webSocketSmoke = $this->fileContents(base_path('PTWebSocket/scripts/public-proxy-smoke.js'));
        foreach (['WEBSOCKET_SMOKE_ARTIFACT_DIR', 'websocket-public-proxy-healthz.log', 'WEBSOCKET_PUBLIC_URL', 'WEBSOCKET_PUBLIC_ORIGIN', 'expectDenied'] as $needle) {
            if (strpos($webSocketSmoke, $needle) === false) {
                $missing[] = 'websocket_public_proxy_smoke:' . $needle;
            }
        }

        $alertmanagerSmoke = $this->fileContents(base_path('deploy/scripts/alertmanager-smoke.sh'));
        foreach (['set -euo pipefail', 'ALERTMANAGER_ARTIFACT_DIR', 'alertmanager-delivery-test.log', 'ALERTMANAGER_BEARER_TOKEN', '/api/v2/alerts', 'BBBB2BSmokeNotification'] as $needle) {
            if (strpos($alertmanagerSmoke, $needle) === false) {
                $missing[] = 'alertmanager_smoke:' . $needle;
            }
        }

        $alertmanagerReceiver = $this->fileContents(base_path('deploy/scripts/alertmanager-receiver-check.sh'));
        foreach (['set -euo pipefail', 'ALERTMANAGER_RECEIVER_EXPORT_FILE', 'ALERTMANAGER_RECEIVER_QUERY_URL', 'alertmanager-receiver-delivery-confirmation.log', 'BBBB2BSmokeNotification', 'receiver_delivery_verified'] as $needle) {
            if (strpos($alertmanagerReceiver, $needle) === false) {
                $missing[] = 'alertmanager_receiver:' . $needle;
            }
        }

        $prometheusSmoke = $this->fileContents(base_path('deploy/scripts/prometheus-smoke.sh'));
        foreach (['set -euo pipefail', 'PROMETHEUS_ARTIFACT_DIR', 'prometheus-scrape-and-rule-test.log', 'PROMETHEUS_RULES_FILE', 'METRICS_FILE', 'promtool', 'bbb_b2b_info'] as $needle) {
            if (strpos($prometheusSmoke, $needle) === false) {
                $missing[] = 'prometheus_smoke:' . $needle;
            }
        }

        $logShippingExternal = $this->fileContents(base_path('deploy/scripts/log-shipping-external-check.sh'));
        foreach (['set -euo pipefail', 'LOG_SHIPPING_MARKER', 'LOG_SHIPPING_EXPORT_FILE', 'LOG_SHIPPING_QUERY_URL', 'b2b-log-shipping-external-delivery.log', 'observability.log_shipping_check', 'log-shipping-secret-probe'] as $needle) {
            if (strpos($logShippingExternal, $needle) === false) {
                $missing[] = 'log_shipping_external:' . $needle;
            }
        }

        $backup = $this->fileContents(base_path('deploy/scripts/backup.sh'));
        foreach (['BACKUP_ARTIFACT_DIR', 'b2b-backup-', '.sha256', 'sha256_value'] as $needle) {
            if (strpos($backup, $needle) === false) {
                $missing[] = 'backup_evidence:' . $needle;
            }
        }

        $backupOffhost = $this->fileContents(base_path('deploy/scripts/backup-offhost-verify.sh'));
        foreach (['OFFHOST_BACKUP_DIR', 'BACKUP_HASH_FILE', 'backup-and-offhost-storage-verification.log', 'sha256_value', 'offhost_backup_storage'] as $needle) {
            if (strpos($backupOffhost, $needle) === false) {
                $missing[] = 'backup_offhost_evidence:' . $needle;
            }
        }

        $restore = $this->fileContents(base_path('deploy/scripts/restore.sh'));
        foreach (['RESTORE_ARTIFACT_DIR', 'b2b-restore-', 'b2b-restore-release-check-', 'sha256_value'] as $needle) {
            if (strpos($restore, $needle) === false) {
                $missing[] = 'restore_evidence:' . $needle;
            }
        }

        $rollback = $this->fileContents(base_path('deploy/scripts/rollback.sh'));
        foreach (['ROLLBACK_ARTIFACT_DIR', 'b2b-rollback-', 'b2b-rollback-release-check-'] as $needle) {
            if (strpos($rollback, $needle) === false) {
                $missing[] = 'rollback_evidence:' . $needle;
            }
        }

        $loadTest = $this->fileContents(base_path('deploy/k6/b2b-smoke-load.js'));
        foreach (['handleSummary', 'K6_SUMMARY_PATH', 'signed_operator_checks_enabled', '/api/b2b/v1/portal/docs/openapi.json', '/api/b2b/v1/portal/docs/postman_collection.json', 'omits canary secret'] as $needle) {
            if (strpos($loadTest, $needle) === false) {
                $missing[] = 'load_evidence:' . $needle;
            }
        }

        return [
            'name' => 'deployment_artifacts',
            'status' => count($missing) === 0 ? 'pass' : ($production ? 'fail' : 'warn'),
            'message' => count($missing) === 0
                ? 'Production deployment, monitoring, smoke/load, release evidence, and runbook artifacts are present.'
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

    private function gameCatalogSyncCheck($production)
    {
        $missing = [];
        $console = $this->fileContents(base_path('routes/b2b_console.php'));
        foreach ([
            "b2b:sync-games",
            "{--soft-disable-missing}",
            "--soft-disable-missing cannot be combined with --limit",
            "missing_from_games_source",
            "B2BGameCatalogCache::class",
            "soft-disabled:",
        ] as $needle) {
            if (strpos($console, $needle) === false) {
                $missing[] = 'console:' . $needle;
            }
        }

        $syncTest = $this->fileContents(base_path('tests/Feature/B2BGameCatalogSyncCommandTest.php'));
        foreach ([
            'testSyncGamesCanSoftDisableMissingShopGames',
            'testSoftDisableMissingCannotRunWithPartialLimit',
            'sync_missing_other_shop',
            'sync_external_provider',
        ] as $needle) {
            if (strpos($syncTest, $needle) === false) {
                $missing[] = 'test:' . $needle;
            }
        }

        $availability = $this->fileContents(base_path('app/B2B/Services/B2BGameAvailabilityService.php'));
        foreach ([
            'catalogStatusDecision',
            'GAME_UNDER_MAINTENANCE',
            'STATUS_MAINTENANCE',
        ] as $needle) {
            if (strpos($availability, $needle) === false) {
                $missing[] = 'availability:' . $needle;
            }
        }

        $flowTest = $this->fileContents(base_path('tests/Feature/B2BOperatorFlowIsolationTest.php'));
        foreach ([
            'testMaintenanceCatalogGameIsHiddenAndLaunchRejected',
            'GAME_UNDER_MAINTENANCE',
            'catalog_maintenance_a',
            'provider_game_id',
            'canonical_game_id',
            'launch_config.launch_mode',
            'meta.filters.platform',
        ] as $needle) {
            if (strpos($flowTest, $needle) === false) {
                $missing[] = 'flow_test:' . $needle;
            }
        }

        $catalogMigration = $this->fileContents(base_path('database/migrations/2026_07_13_155853_add_catalog_runtime_fields_to_b2b_game_catalog_table.php'));
        $catalogModel = $this->fileContents(base_path('app/B2B/Models/B2BGameCatalog.php'));
        $catalogController = $this->fileContents(base_path('app/Http/Controllers/Api/B2B/GameCatalogController.php'));
        $provider = $this->fileContents(base_path('app/B2B/Providers/GoldsvetInternalProvider.php'));
        foreach (['provider_game_id', 'canonical_game_id', 'slug', 'platform', 'launch_config'] as $field) {
            if (strpos($catalogMigration, $field) === false) {
                $missing[] = 'catalog_runtime_migration:' . $field;
            }

            if (strpos($catalogModel, "'" . $field . "'") === false) {
                $missing[] = 'catalog_model:' . $field;
            }

            if (strpos($catalogController, "'" . $field . "'") === false) {
                $missing[] = 'catalog_api_payload:' . $field;
            }

            if (strpos($provider, "'" . $field . "'") === false) {
                $missing[] = 'provider_catalog_payload:' . $field;
            }
        }

        $openapi = $this->fileContents(base_path('docs/b2b/openapi.json'));
        foreach (['"platform"', 'GAME_UNDER_MAINTENANCE', 'provider_game_id', 'launch_config'] as $needle) {
            if (strpos($openapi, $needle) === false) {
                $missing[] = 'openapi:' . $needle;
            }
        }

        return [
            'name' => 'game_catalog_sync',
            'status' => count($missing) === 0 ? 'pass' : ($production ? 'fail' : 'warn'),
            'message' => count($missing) === 0
                ? 'Game catalog sync supports cache invalidation, safe soft-disable of missing synced games, and explicit maintenance-state launch rejection.'
                : 'Missing B2B game catalog sync coverage: ' . implode(', ', $missing),
        ];
    }

    private function launcherIntegrationCheck($production)
    {
        $missing = [];
        $route = Route::getRoutes()->getByName('b2b.launcher');
        if (!$route) {
            $missing[] = 'route:b2b.launcher';
        } else {
            if ($route->uri() !== 'b2b/launcher/{game}/{token}' || !in_array('GET', $route->methods(), true)) {
                $missing[] = 'route_shape:b2b.launcher';
            }

            $action = $route->getActionName();
            if (strpos($action, 'B2BLauncherController') === false || strpos($action, 'launch') === false) {
                $missing[] = 'route_action:b2b.launcher';
            }
        }

        foreach ([
            'app/Http/Controllers/Api/B2B/GameLaunchController.php' => [
                'Str::random(64)',
                "hash('sha256', \$token)",
                'publicLaunchUrl($gameId, $token)',
                "'launch_url' => null",
                "'launch_url' => \$launchUrl",
            ],
            'app/Http/Controllers/Api/B2B/B2BLauncherController.php' => [
                'findSessionByPlainToken($game, $token)',
                'prepareProviderLaunch($session)',
                "redirect()->to(\$prepared['redirect_url'])",
                'SESSION_EXPIRED',
            ],
            'app/B2B/Services/B2BLaunchBridge.php' => [
                "url('/b2b/launcher/'",
                "hash('sha256', \$plainToken)",
                'prepareProviderLaunch',
                'recordProviderRequest',
                "'redirect_prepared'",
            ],
            'app/B2B/Providers/GoldsvetInternalProvider.php' => [
                'ensureShadowUser',
                'refreshApiToken',
                "url('/launcher/'",
                "'legacy_launch_token'",
                "'legacy_launch_url'",
                "'launched_at'",
                'launch_attempts',
            ],
            'tests/Feature/B2BOperatorFlowIsolationTest.php' => [
                'testLaunchCreatesSessionOnlyForOperatorsOwnGame',
                "assertStringStartsWith('/b2b/launcher/book_flow_a/'",
                'assertSame(64, strlen($launchToken))',
                "hash('sha256', \$launchToken)",
                'assertNull($session->launch_url)',
                "assertArrayNotHasKey('legacy_launch_token'",
                'assertStringNotContainsString($launchToken',
            ],
        ] as $path => $needles) {
            $contents = $this->fileContents(base_path($path));
            foreach ($needles as $needle) {
                if (strpos($contents, $needle) === false) {
                    $missing[] = 'launcher_integration:' . $path . ':' . $needle;
                }
            }
        }

        return [
            'name' => 'launcher_integration',
            'status' => count($missing) === 0 ? 'pass' : ($production ? 'fail' : 'warn'),
            'message' => count($missing) === 0
                ? 'B2B launch sessions use one-time hashed bridge tokens before provider-prepared legacy launcher redirects without exposing launch secrets.'
                : 'Missing B2B launcher integration coverage: ' . implode(', ', $missing),
        ];
    }

    private function websocketRuntimeCheck($production)
    {
        $missing = [];
        $paths = [
            'PTWebSocket/Server.js',
            'PTWebSocket/httpClient.js',
            'PTWebSocket/scripts/validate-production-config.js',
            'PTWebSocket/scripts/public-proxy-smoke.js',
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
            foreach (['ws', 'mysql2', 'ioredis', 'moment-timezone'] as $dependency) {
                if (empty($package['dependencies'][$dependency])) {
                    $missing[] = 'package_dependency:' . $dependency;
                }
            }

            if (!empty($package['dependencies']['request'])) {
                $missing[] = 'package_dependency:deprecated_request';
            }

            if (empty($package['scripts']['start']) || strpos($package['scripts']['start'], 'node Server.js') === false) {
                $missing[] = 'package_script:start';
            }

            if (empty($package['scripts']['check:syntax'])) {
                $missing[] = 'package_script:check:syntax';
            }

            if (empty($package['scripts']['check:production-config'])
                || strpos($package['scripts']['check:production-config'], 'validate-production-config.js') === false) {
                $missing[] = 'package_script:check:production-config';
            }

            if (empty($package['scripts']['smoke:public-proxy'])
                || strpos($package['scripts']['smoke:public-proxy'], 'public-proxy-smoke.js') === false) {
                $missing[] = 'package_script:smoke:public-proxy';
            }
        }

        $lock = $this->fileContents(base_path('PTWebSocket/pnpm-lock.yaml'));
        foreach (['request@', 'deprecated: request'] as $needle) {
            if (strpos($lock, $needle) !== false) {
                $missing[] = 'pnpm_lock:deprecated_request';
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
            } else {
                $seenOrigins = [];
                foreach ($socketConfig['allowed_origins'] as $origin) {
                    $originKey = is_string($origin) ? $origin : 'non_string';
                    $parts = is_string($origin) ? parse_url($origin) : false;
                    $path = is_array($parts) && isset($parts['path']) ? (string) $parts['path'] : '';

                    if (!is_string($origin)
                        || trim($origin) !== $origin
                        || $origin === '*'
                        || strpos($origin, 'https://') !== 0
                        || !is_array($parts)
                        || ($parts['scheme'] ?? null) !== 'https'
                        || empty($parts['host'])
                        || $path !== ''
                        || isset($parts['query'])
                        || isset($parts['fragment'])
                        || isset($parts['user'])
                        || isset($parts['pass'])) {
                        $missing[] = 'socket_config:allowed_origin:' . $originKey;
                        continue;
                    }

                    if (isset($seenOrigins[$origin])) {
                        $missing[] = 'socket_config:duplicate_allowed_origin:' . $origin;
                    }

                    $seenOrigins[$origin] = true;
                }
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
            "require('./httpClient')",
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

        $validator = $this->fileContents(base_path('PTWebSocket/scripts/validate-production-config.js'));
        foreach ([
            'validateOrigins',
            'listen_host',
            'require_session_cookie',
            'auth_tokens',
            'BBB_WEBSOCKET_CONFIG_STRICT',
            'public/socket_config2.json',
            'heartbeat_interval_ms',
            'log_json',
        ] as $needle) {
            if (strpos($validator, $needle) === false) {
                $missing[] = 'websocket_config_validator:' . $needle;
            }
        }

        $publicProxySmoke = $this->fileContents(base_path('PTWebSocket/scripts/public-proxy-smoke.js'));
        foreach ([
            "require('ws')",
            'WEBSOCKET_PUBLIC_URL',
            'WEBSOCKET_PUBLIC_ORIGIN',
            'WEBSOCKET_SMOKE_ARTIFACT_DIR',
            'websocket-public-proxy-healthz.log',
            'public_healthz',
            'websocket_upgrade_allowed_origin',
            'websocket_upgrade_denied_origin',
            'auth_token_supplied',
        ] as $needle) {
            if (strpos($publicProxySmoke, $needle) === false) {
                $missing[] = 'websocket_public_proxy_smoke:' . $needle;
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

        $workflow = $this->fileContents(base_path('.github/workflows/b2b-release.yml'));
        if (strpos($workflow, 'pnpm run check:production-config') === false) {
            $missing[] = 'workflow_websocket:check:production-config';
        }

        $runbook = $this->fileContents(base_path('docs/deployment/PRODUCTION_RUNBOOK.md'));
        if (strpos($runbook, 'pnpm run check:production-config') === false) {
            $missing[] = 'runbook_websocket:check:production-config';
        }

        return [
            'name' => 'websocket_runtime',
            'status' => count($missing) === 0 ? 'pass' : ($production ? 'fail' : 'warn'),
            'message' => count($missing) === 0
                ? 'Node/WebSocket manifest, lockfile, proxy template, health probe, origin guard, heartbeat, production config validator, public proxy smoke, and safe logging are present.'
                : 'Missing Node/WebSocket runtime release coverage: ' . implode(', ', $missing),
        ];
    }

    private function apiKeyScopeCheck($production)
    {
        $missing = [];
        $scopePolicy = app(B2BApiKeyScopePolicy::class);
        $defaultScopes = $scopePolicy->defaultScopes();

        if (in_array(B2BApiKeyScopePolicy::WILDCARD_SCOPE, $defaultScopes, true)) {
            $missing[] = 'default_scopes:wildcard';
        }

        if (in_array('reports.export', $defaultScopes, true)) {
            $missing[] = 'default_scopes:reports.export';
        }

        $migration = $this->fileContents(base_path('database/migrations/2026_06_24_000011_add_scopes_to_b2b_operator_api_keys_table.php'));
        foreach (["'scopes'", "json('scopes')", "dropColumn('scopes')"] as $needle) {
            if (strpos($migration, $needle) === false) {
                $missing[] = 'api_key_scopes_migration:' . $needle;
            }
        }

        $model = $this->fileContents(base_path('app/B2B/Models/B2BOperatorApiKey.php'));
        foreach (["'scopes'", "'scopes' => 'array'"] as $needle) {
            if (strpos($model, $needle) === false) {
                $missing[] = 'api_key_model:' . $needle;
            }
        }

        $kernel = $this->fileContents(base_path('app/Http/Kernel.php'));
        foreach (["'b2b.scope'", 'RequireB2BApiScope'] as $needle) {
            if (strpos($kernel, $needle) === false) {
                $missing[] = 'kernel:' . $needle;
            }
        }

        foreach ($this->requiredApiScopedRoutes() as $route) {
            if (!$this->routeUsesMiddlewareByUri($route['method'], $route['uri'], $route['middleware'])) {
                $missing[] = 'route_scope:' . $route['uri'] . ':' . $route['middleware'];
            }
        }

        $middleware = $this->fileContents(base_path('app/Http/Middleware/RequireB2BApiScope.php'));
        foreach (['B2B_SCOPE_DENIED', 'required_scopes', 'B2BApiKeyScopePolicy'] as $needle) {
            if (strpos($middleware, $needle) === false) {
                $missing[] = 'scope_middleware:' . $needle;
            }
        }

        $credentialLifecycle = $this->fileContents(base_path('app/B2B/Services/B2BApiCredentialLifecycleService.php'));
        foreach (['normalizeScopes', "'scopes'", 'B2BApiKeyScopePolicy'] as $needle) {
            if (strpos($credentialLifecycle, $needle) === false) {
                $missing[] = 'credential_lifecycle:' . $needle;
            }
        }

        $envExample = $this->fileContents(base_path('.env.example'));
        if (strpos($envExample, 'B2B_API_KEY_DEFAULT_SCOPES=') === false) {
            $missing[] = 'env:B2B_API_KEY_DEFAULT_SCOPES';
        }

        $releaseChecks = $this->fileContents(base_path('docs/b2b/RELEASE_CHECKS.md'));
        foreach (['B2B_API_KEY_DEFAULT_SCOPES', 'reports.export'] as $needle) {
            if (strpos($releaseChecks, $needle) === false) {
                $missing[] = 'release_checks:' . $needle;
            }
        }

        return [
            'name' => 'api_key_scopes',
            'status' => count($missing) === 0 ? 'pass' : ($production ? 'fail' : 'warn'),
            'message' => count($missing) === 0
                ? 'B2B API keys have explicit scopes and settlement export requires reports.export outside the default key scope set.'
                : 'Missing B2B API-key scope isolation: ' . implode(', ', $missing),
        ];
    }

    private function payloadRedactionAuditCheck($production)
    {
        $missing = [];

        $service = $this->fileContents(base_path('app/B2B/Services/B2BPayloadRedactionAuditor.php'));
        foreach (['B2BPayloadRedactor', 'b2b_wallet_transactions', 'b2b_wallet_transaction_attempts', 'needsRedaction'] as $needle) {
            if (strpos($service, $needle) === false) {
                $missing[] = 'payload_auditor:' . $needle;
            }
        }

        $redactor = $this->fileContents(base_path('app/B2B/Services/B2BPayloadRedactor.php'));
        foreach (['redactText($value)', 'return $this->redactText($value);'] as $needle) {
            if (strpos($redactor, $needle) === false) {
                $missing[] = 'payload_redactor:' . $needle;
            }
        }

        $console = $this->fileContents(base_path('routes/b2b_console.php'));
        foreach (['b2b:payload-redaction-audit', '--write', '--artifact', 'B2BPayloadRedactionAuditor'] as $needle) {
            if (strpos($console, $needle) === false) {
                $missing[] = 'console:' . $needle;
            }
        }

        $releaseChecks = $this->fileContents(base_path('docs/b2b/RELEASE_CHECKS.md'));
        foreach (['b2b:payload-redaction-audit', '--write', 'payload redaction'] as $needle) {
            if (strpos($releaseChecks, $needle) === false) {
                $missing[] = 'release_checks:' . $needle;
            }
        }

        $runbook = $this->fileContents(base_path('docs/deployment/PRODUCTION_RUNBOOK.md'));
        foreach (['b2b:payload-redaction-audit', 'PAYLOAD_REDACTION_ARTIFACT', 'release-evidence'] as $needle) {
            if (strpos($runbook, $needle) === false) {
                $missing[] = 'runbook:' . $needle;
            }
        }

        return [
            'name' => 'payload_redaction_audit',
            'status' => count($missing) === 0 ? 'pass' : ($production ? 'fail' : 'warn'),
            'message' => count($missing) === 0
                ? 'Legacy B2B wallet payload redaction audit/remediation tooling and release docs are present.'
                : 'Missing legacy B2B payload redaction audit coverage: ' . implode(', ', $missing),
        ];
    }

    private function requiredApiScopedRoutes()
    {
        $routes = [
            ['method' => 'GET', 'uri' => 'api/b2b/v1/operator/me', 'middleware' => 'b2b.scope:operator.read'],
            ['method' => 'GET', 'uri' => 'api/b2b/v1/portal', 'middleware' => 'b2b.scope:portal.read'],
            ['method' => 'GET', 'uri' => 'api/b2b/v1/portal/overview', 'middleware' => 'b2b.scope:portal.read'],
            ['method' => 'GET', 'uri' => 'api/b2b/v1/portal/diagnostics/{request_uid}', 'middleware' => 'b2b.scope:portal.read'],
            ['method' => 'GET', 'uri' => 'api/b2b/v1/portal/support/cases/{transaction_uid}', 'middleware' => 'b2b.scope:portal.read'],
            ['method' => 'GET', 'uri' => 'api/b2b/v1/portal/support/tickets/{ticket_uid}', 'middleware' => 'b2b.scope:portal.read'],
            ['method' => 'POST', 'uri' => 'api/b2b/v1/portal/support/cases/{transaction_uid}/comments', 'middleware' => 'b2b.scope:support.write'],
            ['method' => 'POST', 'uri' => 'api/b2b/v1/portal/support/tickets', 'middleware' => 'b2b.scope:support.write'],
            ['method' => 'POST', 'uri' => 'api/b2b/v1/portal/support/tickets/{ticket_uid}/comments', 'middleware' => 'b2b.scope:support.write'],
            ['method' => 'POST', 'uri' => 'api/b2b/v1/portal/support/tickets/{ticket_uid}/close', 'middleware' => 'b2b.scope:support.write'],
            ['method' => 'POST', 'uri' => 'api/b2b/v1/portal/support/tickets/{ticket_uid}/reopen', 'middleware' => 'b2b.scope:support.write'],
            ['method' => 'GET', 'uri' => 'api/b2b/v1/games', 'middleware' => 'b2b.scope:games.read'],
            ['method' => 'GET', 'uri' => 'api/b2b/v1/games/{game_uid}', 'middleware' => 'b2b.scope:games.read'],
            ['method' => 'POST', 'uri' => 'api/b2b/v1/games/launch', 'middleware' => 'b2b.scope:games.launch'],
            ['method' => 'GET', 'uri' => 'api/b2b/v1/sessions', 'middleware' => 'b2b.scope:sessions.read'],
            ['method' => 'GET', 'uri' => 'api/b2b/v1/sessions/{session_uid}', 'middleware' => 'b2b.scope:sessions.read'],
            ['method' => 'POST', 'uri' => 'api/b2b/v1/sessions/{session_uid}/close', 'middleware' => 'b2b.scope:sessions.close'],
            ['method' => 'POST', 'uri' => 'api/b2b/v1/wallet/balance', 'middleware' => 'b2b.scope:wallet.balance'],
            ['method' => 'POST', 'uri' => 'api/b2b/v1/wallet/bet', 'middleware' => 'b2b.scope:wallet.mutate'],
            ['method' => 'POST', 'uri' => 'api/b2b/v1/wallet/win', 'middleware' => 'b2b.scope:wallet.mutate'],
            ['method' => 'POST', 'uri' => 'api/b2b/v1/wallet/refund', 'middleware' => 'b2b.scope:wallet.mutate'],
            ['method' => 'POST', 'uri' => 'api/b2b/v1/wallet/rollback', 'middleware' => 'b2b.scope:wallet.mutate'],
            ['method' => 'GET', 'uri' => 'api/b2b/v1/wallet/health', 'middleware' => 'b2b.scope:wallet.status'],
            ['method' => 'GET', 'uri' => 'api/b2b/v1/wallet/transactions/{transaction_uid}/status', 'middleware' => 'b2b.scope:wallet.status'],
            ['method' => 'GET', 'uri' => 'api/b2b/v1/wallet/transactions/{transaction_uid}/attempts', 'middleware' => 'b2b.scope:wallet.status'],
            ['method' => 'GET', 'uri' => 'api/b2b/v1/reports/summary', 'middleware' => 'b2b.scope:reports.read'],
            ['method' => 'GET', 'uri' => 'api/b2b/v1/reports/transactions', 'middleware' => 'b2b.scope:reports.read'],
            ['method' => 'GET', 'uri' => 'api/b2b/v1/reports/ggr', 'middleware' => 'b2b.scope:reports.read'],
            ['method' => 'GET', 'uri' => 'api/b2b/v1/reports/settlements', 'middleware' => 'b2b.scope:reports.read'],
            ['method' => 'POST', 'uri' => 'api/b2b/v1/reports/settlements/export', 'middleware' => 'b2b.scope:reports.read'],
            ['method' => 'POST', 'uri' => 'api/b2b/v1/reports/settlements/export', 'middleware' => 'b2b.scope:reports.export'],
            ['method' => 'GET', 'uri' => 'api/b2b/v1/reports/settlements/{settlement_uid}', 'middleware' => 'b2b.scope:reports.read'],
            ['method' => 'GET', 'uri' => 'api/b2b/v1/reports/reconciliation', 'middleware' => 'b2b.scope:reports.read'],
            ['method' => 'GET', 'uri' => 'api/b2b/v1/reports/transactions/{transaction_uid}', 'middleware' => 'b2b.scope:reports.read'],
            ['method' => 'GET', 'uri' => 'api/b2b/v1/sandbox/wallet/{player_id}', 'middleware' => 'b2b.scope:sandbox.wallet.read'],
            ['method' => 'GET', 'uri' => 'api/b2b/v1/sandbox/wallet/{player_id}/entries', 'middleware' => 'b2b.scope:sandbox.wallet.read'],
            ['method' => 'POST', 'uri' => 'api/b2b/v1/sandbox/wallet/{player_id}/credit', 'middleware' => 'b2b.scope:sandbox.wallet.mutate'],
            ['method' => 'POST', 'uri' => 'api/b2b/v1/sandbox/wallet/{player_id}/debit', 'middleware' => 'b2b.scope:sandbox.wallet.mutate'],
        ];

        foreach (['credentials', 'games', 'sessions', 'transactions', 'settlements', 'cases', 'callbacks', 'diagnostics', 'reports', 'support', 'logs', 'docs'] as $portalSection) {
            $routes[] = [
                'method' => 'GET',
                'uri' => 'api/b2b/v1/portal/' . $portalSection,
                'middleware' => 'b2b.scope:portal.read',
            ];
        }
        $routes[] = [
            'method' => 'GET',
            'uri' => 'api/b2b/v1/portal/games/{game_uid}',
            'middleware' => 'b2b.scope:portal.read',
        ];
        $routes[] = [
            'method' => 'GET',
            'uri' => 'api/b2b/v1/portal/sessions/{session_uid}',
            'middleware' => 'b2b.scope:portal.read',
        ];
        $routes[] = [
            'method' => 'GET',
            'uri' => 'api/b2b/v1/portal/transactions/{transaction_uid}',
            'middleware' => 'b2b.scope:portal.read',
        ];
        $routes[] = [
            'method' => 'GET',
            'uri' => 'api/b2b/v1/portal/settlements/{settlement_uid}',
            'middleware' => 'b2b.scope:portal.read',
        ];

        return $routes;
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

        if ($production && !(bool) config('b2b_admin.web_step_up_requires_password', true)) {
            $missing[] = 'web_step_up_requires_password';
        }

        $stepUpGuard = $this->fileContents(base_path('app/B2B/Services/B2BWebStepUpGuard.php'));
        foreach (['Hash::check', 'password_verified_at', 'current_password_required'] as $needle) {
            if (strpos($stepUpGuard, $needle) === false) {
                $missing[] = 'web_step_up_guard:' . $needle;
            }
        }

        $stepUpController = $this->fileContents(base_path('app/Http/Controllers/Web/Backend/B2BStepUpController.php'));
        foreach (['current_password', 'passwordRequired()', "'2fa'"] as $needle) {
            if (strpos($stepUpController, $needle) === false) {
                $missing[] = 'web_step_up_controller:' . $needle;
            }
        }

        $stepUpView = $this->fileContents(base_path('resources/views/backend/b2b/step-up.blade.php'));
        foreach (['current_password', 'autocomplete="current-password"'] as $needle) {
            if (strpos($stepUpView, $needle) === false) {
                $missing[] = 'web_step_up_view:' . $needle;
            }
        }

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

        $walletManualController = $this->fileContents(base_path('app/Http/Controllers/Web/Backend/B2BWalletManualActionController.php'));
        foreach (['formDefaults', 'safeRedirect', "'redirect_to' => 'nullable|string|max:2048'", "redirect(\$this->safeRedirect"] as $needle) {
            if (strpos($walletManualController, $needle) === false) {
                $missing[] = 'backend_wallet_manual_controller:' . $needle;
            }
        }

        $backendWalletManualView = $this->fileContents(base_path('resources/views/backend/b2b/wallet-manual-actions.blade.php'));
        foreach (["name=\"redirect_to\"", "\$form['redirect_to']", "\$form['transaction_uid']", "\$form['operator_id']", "\$form['reason']"] as $needle) {
            if (strpos($backendWalletManualView, $needle) === false) {
                $missing[] = 'backend_wallet_manual_view:' . $needle;
            }
        }

        foreach (['backend.b2b.settlements.index', 'backend.b2b.settlements.show', 'backend.b2b.settlements.submit', 'backend.b2b.settlements.approve', 'backend.b2b.settlements.reject'] as $routeName) {
            if (!Route::has($routeName)) {
                $missing[] = 'route:' . $routeName;
            }
        }

        if (!$this->routeUsesMiddleware('backend.b2b.settlements.index', 'b2b.admin:b2b.reports.view')) {
            $missing[] = 'route_middleware:backend.b2b.settlements.index:b2b.admin';
        }

        if (!$this->routeUsesMiddleware('backend.b2b.settlements.show', 'b2b.admin:b2b.reports.view')) {
            $missing[] = 'route_middleware:backend.b2b.settlements.show:b2b.admin';
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

        if (!View::exists('backend.b2b.settlement')) {
            $missing[] = 'view:backend.b2b.settlement';
        }

        $backendSettlementsView = $this->fileContents(base_path('resources/views/backend/b2b/settlements.blade.php'));
        foreach (['backend.b2b.settlements.show', 'View Settlement'] as $needle) {
            if (strpos($backendSettlementsView, $needle) === false) {
                $missing[] = 'backend_settlements_view_drilldown:' . $needle;
            }
        }

        $backendSettlementView = $this->fileContents(base_path('resources/views/backend/b2b/settlement.blade.php'));
        foreach ([
            'B2B Settlement Detail',
            'Settlement Actions',
            'Settlement Totals',
            'Transaction Breakdown',
            'Approval Trail',
            'Snapshot Metadata',
            'name="redirect_to"',
            '$canSubmit',
            '$canApprove',
            '$canReject',
            'backend.b2b.settlements.submit',
            'backend.b2b.settlements.approve',
            'backend.b2b.settlements.reject',
        ] as $needle) {
            if (strpos($backendSettlementView, $needle) === false) {
                $missing[] = 'backend_settlement_view:' . $needle;
            }
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

        $backendCredentialView = $this->fileContents(base_path('resources/views/backend/b2b/credentials.blade.php'));
        foreach (['Revoke Active Key', 'Revoke Step-Up', '$canRevokeKey', 'operator_uid'] as $needle) {
            if (strpos($backendCredentialView, $needle) === false) {
                $missing[] = 'backend_credentials_view:' . $needle;
            }
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
            'backend.b2b.cases.show',
            'backend.b2b.cases.support_ticket.show',
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

        if (!$this->routeUsesMiddleware('backend.b2b.cases.show', 'b2b.admin:b2b.cases.view')) {
            $missing[] = 'route_middleware:backend.b2b.cases.show:b2b.admin';
        }

        if (!$this->routeUsesMiddleware('backend.b2b.cases.support_ticket.show', 'b2b.admin:b2b.cases.view')) {
            $missing[] = 'route_middleware:backend.b2b.cases.support_ticket.show:b2b.admin';
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

        if (!View::exists('backend.b2b.case')) {
            $missing[] = 'view:backend.b2b.case';
        }

        if (!View::exists('backend.b2b.support-ticket')) {
            $missing[] = 'view:backend.b2b.support-ticket';
        }

        $backendCasesView = $this->fileContents(base_path('resources/views/backend/b2b/cases.blade.php'));
        foreach (['backend.b2b.cases.show', 'View Case', 'backend.b2b.cases.support_ticket.show', 'View Thread'] as $needle) {
            if (strpos($backendCasesView, $needle) === false) {
                $missing[] = 'backend_cases_view_drilldown:' . $needle;
            }
        }

        $backendCaseView = $this->fileContents(base_path('resources/views/backend/b2b/case.blade.php'));
        foreach ([
            'B2B Case Detail',
            'Case Actions',
            'Operator Comments',
            'Case Events',
            'name="redirect_to"',
            '$canClaim',
            '$canResolve',
            '$canReopen',
            'Reopen Reason',
            'backend.b2b.cases.claim',
            'backend.b2b.cases.resolve',
            'backend.b2b.cases.reopen',
            'Manual Wallet Action',
            'backend.b2b.wallet_manual_actions.index',
        ] as $needle) {
            if (strpos($backendCaseView, $needle) === false) {
                $missing[] = 'backend_case_view:' . $needle;
            }
        }

        $backendSupportTicketView = $this->fileContents(base_path('resources/views/backend/b2b/support-ticket.blade.php'));
        foreach ([
            'B2B Support Ticket',
            'Ticket Actions',
            'Message Thread',
            '$canComment',
            '$canClose',
            '$canReopen',
            'Reopen Reason',
            "\$ticket['context_display']",
            "\$ticket['messages']",
            'name="redirect_to"',
            'backend.b2b.cases.support_ticket.comment',
            'backend.b2b.cases.support_ticket.close',
            'backend.b2b.cases.support_ticket.reopen',
        ] as $needle) {
            if (strpos($backendSupportTicketView, $needle) === false) {
                $missing[] = 'backend_support_ticket_view:' . $needle;
            }
        }

        if (!Route::has('backend.b2b.audit.index')) {
            $missing[] = 'route:backend.b2b.audit.index';
        }

        if (!Route::has('backend.b2b.audit.export')) {
            $missing[] = 'route:backend.b2b.audit.export';
        }

        if (!$this->routeUsesMiddleware('backend.b2b.audit.index', 'b2b.admin:b2b.audit.view')) {
            $missing[] = 'route_middleware:backend.b2b.audit.index:b2b.admin';
        }

        if (!$this->routeUsesMiddleware('backend.b2b.audit.export', 'b2b.admin:b2b.audit.view')) {
            $missing[] = 'route_middleware:backend.b2b.audit.export:b2b.admin';
        }

        if (!View::exists('backend.b2b.audit')) {
            $missing[] = 'view:backend.b2b.audit';
        }

        $auditController = $this->fileContents(base_path('app/Http/Controllers/Web/Backend/B2BAuditBackofficeController.php'));
        foreach (['function export', 'text/csv', 'Content-Disposition', 'X-Content-Type-Options', 'fputcsv', 'metadata_display', 'reason_display'] as $needle) {
            if (strpos($auditController, $needle) === false) {
                $missing[] = 'backend_audit_export_controller:' . $needle;
            }
        }

        $auditView = $this->fileContents(base_path('resources/views/backend/b2b/audit.blade.php'));
        foreach (['Export CSV', 'backend.b2b.audit.export', 'fa-download'] as $needle) {
            if (strpos($auditView, $needle) === false) {
                $missing[] = 'backend_audit_export_view:' . $needle;
            }
        }

        $auditTest = $this->fileContents(base_path('tests/Feature/B2BBackofficeAuditTrailTest.php'));
        foreach (['testAuditTrailExportsRedactedFilteredCsv', 'text/csv', 'assertStringNotContainsString', 'audit-secret-value', 'testAuditExportRequiresAuditPermission'] as $needle) {
            if (strpos($auditTest, $needle) === false) {
                $missing[] = 'backend_audit_export_test:' . $needle;
            }
        }

        $manualWalletTest = $this->fileContents(base_path('tests/Feature/B2BBackofficeManualWalletActionTest.php'));
        foreach (['testManualWalletActionScreenPrefillsCaseWorkflow', 'testManualWalletActionReturnsToCaseDetailAfterFreshWebStepUp', 'redirect_to', 'tx_web_manual_case_return'] as $needle) {
            if (strpos($manualWalletTest, $needle) === false) {
                $missing[] = 'backend_manual_wallet_workflow_test:' . $needle;
            }
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

        if (!View::exists('b2b.operator-portal.game')) {
            $missing[] = 'view:b2b.operator-portal.game';
        }

        if (!View::exists('b2b.operator-portal.thread')) {
            $missing[] = 'view:b2b.operator-portal.thread';
        }

        if (!View::exists('b2b.operator-portal.session')) {
            $missing[] = 'view:b2b.operator-portal.session';
        }

        if (!View::exists('b2b.operator-portal.transaction')) {
            $missing[] = 'view:b2b.operator-portal.transaction';
        }

        if (!View::exists('b2b.operator-portal.settlement')) {
            $missing[] = 'view:b2b.operator-portal.settlement';
        }

        if (!View::exists('b2b.operator-portal.diagnostic')) {
            $missing[] = 'view:b2b.operator-portal.diagnostic';
        }

        $portalQuery = $this->fileContents(base_path('app/B2B/Services/B2BOperatorPortalQuery.php'));
        foreach (["'scopes'", 'scopeList(', "'scopes_count'"] as $needle) {
            if (strpos($portalQuery, $needle) === false) {
                $missing[] = 'portal_query_scope_visibility:' . $needle;
            }
        }
        foreach ([
            'support_case_detail_template',
            'support_case_thread_template',
            'support_ticket_detail_template',
            'support_ticket_thread_template',
            'support_case_detail_endpoint',
            'support_case_thread_endpoint',
            'support_case_comment_endpoint',
            'recent_cases',
            'detail_endpoint',
            'thread_endpoint',
            'portal_logs',
            'portal_openapi_download',
            'portal_postman_download',
            'auditSummary',
            'metadata_summary',
            'launch_diagnostics',
            'portal_diagnostics',
            'portal_diagnostic_detail_template',
            'providerRequestDetail',
            'providerRequestDetailEndpoint',
            'portal_game_detail_template',
            'gameDetail',
            'gameDetailEndpoint',
            'portal_session_detail_template',
            'sessionDetail',
            'sessionDetailEndpoint',
            'portal_transaction_detail_template',
            'transactionDetail',
            'transactionDetailEndpoint',
            'portal_settlement_detail_template',
            'settlementDetail',
            'settlementDetailEndpoint',
        ] as $needle) {
            if (strpos($portalQuery, $needle) === false) {
                $missing[] = 'portal_query_detail_endpoint:' . $needle;
            }
        }

        foreach ([
            'resources/views/b2b/operator-portal/overview.blade.php',
            'resources/views/b2b/operator-portal/section.blade.php',
        ] as $viewPath) {
            $view = $this->fileContents(base_path($viewPath));
            if (strpos($view, "\$key['scopes']") === false) {
                $missing[] = 'portal_view_scope_visibility:' . $viewPath;
            }
            foreach (['Detail Endpoint', 'support_case_detail_endpoint', 'detail_endpoint'] as $needle) {
                if (strpos($view, $needle) === false) {
                    $missing[] = 'portal_view_detail_endpoint:' . $viewPath . ':' . $needle;
                }
            }
            foreach (['Thread Page', 'support_case_thread_endpoint', 'thread_endpoint'] as $needle) {
                if (strpos($view, $needle) === false) {
                    $missing[] = 'portal_view_thread_endpoint:' . $viewPath . ':' . $needle;
                }
            }
            foreach (['Comment Endpoint', 'support_case_comment_endpoint'] as $needle) {
                if (strpos($view, $needle) === false) {
                    $missing[] = 'portal_view_case_comment_endpoint:' . $viewPath . ':' . $needle;
                }
            }
            foreach (['Action Endpoints', 'comment_endpoint', 'close_endpoint', 'reopen_endpoint'] as $needle) {
                if (strpos($view, $needle) === false) {
                    $missing[] = 'portal_view_ticket_action_endpoint:' . $viewPath . ':' . $needle;
                }
            }
            if (strpos($view, 'API Logs') === false) {
                $missing[] = 'portal_view_api_logs:' . $viewPath;
            }
            if ($viewPath === 'resources/views/b2b/operator-portal/section.blade.php') {
                foreach (['Recent Cases', 'recent_cases'] as $needle) {
                    if (strpos($view, $needle) === false) {
                        $missing[] = 'portal_view_recent_cases:' . $needle;
                    }
                }
                foreach (['launch_diagnostics', 'Provider Requests', 'Failed Launch Sessions'] as $needle) {
                    if (strpos($view, $needle) === false) {
                        $missing[] = 'portal_view_diagnostics:' . $needle;
                    }
                }
                if (strpos($view, "\$audit['recent_events']") === false) {
                    $missing[] = 'portal_view_api_logs:recent_events';
                }
                foreach (['Downloadable Artifacts', 'portal_openapi_download', 'portal_postman_download'] as $needle) {
                    if (strpos($view, $needle) === false) {
                        $missing[] = 'portal_view_docs_download:' . $needle;
                    }
                }
            }
        }

        $portalGameView = $this->fileContents(base_path('resources/views/b2b/operator-portal/game.blade.php'));
        foreach (['Game Summary', 'Assignment', 'Availability', 'Recent Sessions', 'Recent Transactions', 'Portal Detail Endpoint'] as $needle) {
            if (strpos($portalGameView, $needle) === false) {
                $missing[] = 'portal_game_view:' . $needle;
            }
        }
        foreach (['request_body', 'response_body', 'raw_request', 'raw_response', 'launch_url', 'legacy_launch_token', 'token_hash'] as $needle) {
            if (strpos($portalGameView, $needle) !== false) {
                $missing[] = 'portal_game_view_raw_payload:' . $needle;
            }
        }

        $portalDiagnosticView = $this->fileContents(base_path('resources/views/b2b/operator-portal/diagnostic.blade.php'));
        foreach (['Provider Request Summary', 'Request Summary', 'Response Summary', 'Portal Detail Endpoint'] as $needle) {
            if (strpos($portalDiagnosticView, $needle) === false) {
                $missing[] = 'portal_diagnostic_view:' . $needle;
            }
        }
        foreach (['request_payload', 'response_payload', 'raw_request', 'raw_response', 'launch_url', 'legacy_launch_token', 'token_hash'] as $needle) {
            if (strpos($portalDiagnosticView, $needle) !== false) {
                $missing[] = 'portal_diagnostic_view_raw_payload:' . $needle;
            }
        }

        $portalThreadView = $this->fileContents(base_path('resources/views/b2b/operator-portal/thread.blade.php'));
        foreach (["\$thread_type === 'case'", 'Case Summary', 'Ticket Summary', 'API Detail Endpoint', 'Comment Endpoint', 'Close Endpoint', 'Reopen Endpoint'] as $needle) {
            if (strpos($portalThreadView, $needle) === false) {
                $missing[] = 'portal_thread_view:' . $needle;
            }
        }

        $portalSessionView = $this->fileContents(base_path('resources/views/b2b/operator-portal/session.blade.php'));
        foreach (['Session Summary', 'Session Transactions', 'Portal Detail Endpoint', 'request_body'] as $needle) {
            if ($needle === 'request_body') {
                if (strpos($portalSessionView, $needle) !== false) {
                    $missing[] = 'portal_session_view_raw_payload:' . $needle;
                }
                continue;
            }
            if (strpos($portalSessionView, $needle) === false) {
                $missing[] = 'portal_session_view:' . $needle;
            }
        }

        $portalTransactionView = $this->fileContents(base_path('resources/views/b2b/operator-portal/transaction.blade.php'));
        foreach (['Transaction Summary', 'Callback Attempts', 'Callback Logs', 'Portal Detail Endpoint', 'request_body'] as $needle) {
            if ($needle === 'request_body') {
                if (strpos($portalTransactionView, $needle) !== false) {
                    $missing[] = 'portal_transaction_view_raw_payload:' . $needle;
                }
                continue;
            }
            if (strpos($portalTransactionView, $needle) === false) {
                $missing[] = 'portal_transaction_view:' . $needle;
            }
        }

        $portalSettlementView = $this->fileContents(base_path('resources/views/b2b/operator-portal/settlement.blade.php'));
        foreach (['Settlement Summary', 'Settlement Totals', 'Transaction Breakdown', 'Approval Trail', 'Export Metadata', 'Portal Detail Endpoint', 'request_body', 'export_content'] as $needle) {
            if (in_array($needle, ['request_body', 'export_content'], true)) {
                if (strpos($portalSettlementView, $needle) !== false) {
                    $missing[] = 'portal_settlement_view_raw_payload:' . $needle;
                }
                continue;
            }
            if (strpos($portalSettlementView, $needle) === false) {
                $missing[] = 'portal_settlement_view:' . $needle;
            }
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
            'api/b2b/v1/portal/diagnostics',
            'api/b2b/v1/portal/reports',
            'api/b2b/v1/portal/support',
            'api/b2b/v1/portal/logs',
            'api/b2b/v1/portal/docs',
            'api/b2b/v1/portal/docs/openapi.json',
            'api/b2b/v1/portal/docs/postman_collection.json',
            'api/b2b/v1/portal/games/{game_uid}',
            'api/b2b/v1/portal/diagnostics/{request_uid}',
            'api/b2b/v1/portal/sessions/{session_uid}',
            'api/b2b/v1/portal/transactions/{transaction_uid}',
            'api/b2b/v1/portal/settlements/{settlement_uid}',
            'api/b2b/v1/portal/support/cases/{transaction_uid}',
            'api/b2b/v1/portal/support/cases/{transaction_uid}/thread',
            'api/b2b/v1/portal/support/tickets/{ticket_uid}',
            'api/b2b/v1/portal/support/tickets/{ticket_uid}/thread',
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
            'api/b2b/v1/portal/support/tickets/{ticket_uid}/reopen',
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

    private function routeUsesMiddlewareByUri($method, $uri, $middleware)
    {
        foreach (Route::getRoutes() as $route) {
            if ($route->uri() === $uri && in_array($method, $route->methods(), true)) {
                return in_array($middleware, $route->gatherMiddleware(), true);
            }
        }

        return false;
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

    private function webSocketDependencyAuditCheck($production)
    {
        if (!file_exists(base_path('PTWebSocket/pnpm-lock.yaml'))) {
            return [
                'name' => 'websocket_dependency_audit',
                'status' => $production ? 'fail' : 'warn',
                'message' => 'PTWebSocket/pnpm-lock.yaml is missing; WebSocket dependency advisories cannot be verified.',
            ];
        }

        $result = $this->runWebSocketDependencyAuditCommand();
        $payload = json_decode($result['output'], true);

        if (!is_array($payload)) {
            return [
                'name' => 'websocket_dependency_audit',
                'status' => $production ? 'fail' : 'warn',
                'message' => 'WebSocket pnpm audit could not be parsed: ' . trim($result['error'] ?: $result['output']),
            ];
        }

        $vulnerabilities = isset($payload['metadata']['vulnerabilities']) && is_array($payload['metadata']['vulnerabilities'])
            ? $payload['metadata']['vulnerabilities']
            : [];
        $total = 0;
        foreach (['info', 'low', 'moderate', 'high', 'critical'] as $severity) {
            $total += isset($vulnerabilities[$severity]) ? (int) $vulnerabilities[$severity] : 0;
        }

        if ($total === 0) {
            return [
                'name' => 'websocket_dependency_audit',
                'status' => 'pass',
                'message' => 'WebSocket pnpm production dependency audit has no known vulnerabilities.',
            ];
        }

        $parts = [];
        foreach (['critical', 'high', 'moderate', 'low', 'info'] as $severity) {
            $count = isset($vulnerabilities[$severity]) ? (int) $vulnerabilities[$severity] : 0;
            if ($count > 0) {
                $parts[] = $count . ' ' . $severity;
            }
        }

        return [
            'name' => 'websocket_dependency_audit',
            'status' => $production ? 'fail' : 'warn',
            'message' => 'WebSocket pnpm production dependency audit found ' . implode(', ', $parts) . ' vulnerabilities.',
        ];
    }

    protected function runWebSocketDependencyAuditCommand()
    {
        $process = Process::fromShellCommandline('pnpm audit --prod --json', base_path('PTWebSocket'));
        $process->setTimeout(120);
        $process->run();

        return [
            'exit_code' => $process->getExitCode(),
            'output' => $process->getOutput(),
            'error' => $process->getErrorOutput(),
        ];
    }
}
