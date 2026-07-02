<?php

namespace Tests\Unit;

use Tests\TestCase;
use VanguardLTE\B2B\Contracts\GameProviderInterface;
use VanguardLTE\B2B\Models\B2BGameSession;
use VanguardLTE\B2B\Services\B2BReleaseGate;

class B2BReleaseGateTest extends TestCase
{
    public function testProductionGateFailsUnsafeSharedStateAndDebugSettings()
    {
        config([
            'app.debug' => true,
            'b2b.allow_private_wallet_callbacks' => true,
            'b2b.nonce_cache_store' => null,
            'b2b.rate_limit_cache_store' => null,
            'b2b.sandbox_enabled' => true,
            'cache.default' => 'file',
            'queue.default' => 'sync',
            'session.driver' => 'database',
            'session.table' => 'sessions',
        ]);

        $result = app(B2BReleaseGate::class)->run(true, false);

        $this->assertFalse($result['ok']);
        $this->assertCheckFailed($result, 'nonce_cache');
        $this->assertCheckFailed($result, 'rate_limit_cache');
        $this->assertCheckFailed($result, 'scheduler_heartbeat_cache');
        $this->assertCheckFailed($result, 'queue_driver');
        $this->assertCheckPassed($result, 'failed_job_storage');
        $this->assertCheckFailed($result, 'app_debug');
        $this->assertCheckFailed($result, 'session_cookie_security');
        $this->assertCheckPassed($result, 'login_throttle_security');
        $this->assertCheckPassed($result, 'password_policy_security');
        $this->assertCheckPassed($result, 'credential_session_revocation');
        $this->assertCheckFailed($result, 'private_wallet_callbacks');
        $this->assertCheckFailed($result, 'sandbox_disabled');
        $this->assertCheckPassed($result, 'structured_logging');
        $this->assertCheckPassed($result, 'scheduler_config');
        $this->assertCheckPassed($result, 'provider_wallet_contracts');
        $this->assertCheckPassed($result, 'database_schema');
        $this->assertCheckPassed($result, 'deployment_artifacts');
        $this->assertCheckPassed($result, 'websocket_runtime');
        $this->assertCheckPassed($result, 'admin_rbac_config');
        $this->assertCheckPassed($result, 'web_surfaces');
        $this->assertCheckPassed($result, 'laravel_security_mitigations');
    }

    public function testProductionGatePassesRedisSharedStateAndSafeFlags()
    {
        config([
            'app.debug' => false,
            'b2b.allow_private_wallet_callbacks' => false,
            'b2b.nonce_cache_store' => 'redis',
            'b2b.rate_limit_cache_store' => 'redis',
            'b2b.scheduler_heartbeat_cache_store' => 'redis',
            'b2b.sandbox_enabled' => false,
            'cache.default' => 'redis',
            'queue.default' => 'redis',
            'session.secure' => true,
            'session.http_only' => true,
            'session.same_site' => 'lax',
            'session.driver' => 'database',
            'session.table' => 'sessions',
            'security.login_throttle.production_enforced' => true,
            'security.login_throttle.max_attempts' => 10,
            'security.login_throttle.lockout_minutes' => 1,
            'security.password_policy.min_length' => 12,
            'security.password_policy.max_length' => 72,
            'security.password_policy.require_mixed_case' => true,
            'security.password_policy.require_numbers' => true,
            'security.password_policy.disallow_whitespace' => true,
            'security.password_policy.temporary_length' => 16,
        ]);

        $result = app(B2BReleaseGate::class)->run(true, false);

        $this->assertTrue($result['ok']);
        $this->assertCheckPassed($result, 'scheduler_heartbeat_cache');
        $this->assertCheckPassed($result, 'failed_job_storage');
        $this->assertCheckPassed($result, 'scheduler_config');
        $this->assertCheckPassed($result, 'session_cookie_security');
        $this->assertCheckPassed($result, 'login_throttle_security');
        $this->assertCheckPassed($result, 'password_policy_security');
        $this->assertCheckPassed($result, 'credential_session_revocation');
        $this->assertCheckPassed($result, 'structured_logging');
        $this->assertCheckPassed($result, 'provider_wallet_contracts');
        $this->assertCheckPassed($result, 'database_schema');
        $this->assertCheckPassed($result, 'deployment_artifacts');
        $this->assertCheckPassed($result, 'websocket_runtime');
        $this->assertCheckPassed($result, 'admin_rbac_config');
        $this->assertCheckPassed($result, 'web_surfaces');
        $this->assertCheckPassed($result, 'laravel_security_mitigations');
    }

    public function testProductionGatePassesWhenSecretReleaseFilesAreAbsent()
    {
        $this->configureSafeProductionSettings();

        $result = app(B2BReleaseGate::class)->run(true, true);

        $this->assertTrue($result['ok']);
        $this->assertCheckPassed($result, 'release_secret_files');
    }

    public function testProductionGateFailsWhenStructuredLoggingIsDisabled()
    {
        $this->configureSafeProductionSettings();
        config(['b2b.structured_logging_enabled' => false]);

        $result = app(B2BReleaseGate::class)->run(true, false);

        $this->assertFalse($result['ok']);
        $this->assertCheckFailed($result, 'structured_logging');
    }

    public function testProductionGateFailsWhenSessionCookiesAreNotHardened()
    {
        $this->configureSafeProductionSettings();
        config([
            'session.secure' => false,
            'session.http_only' => false,
            'session.same_site' => null,
        ]);

        $result = app(B2BReleaseGate::class)->run(true, false);

        $this->assertFalse($result['ok']);
        $this->assertCheckFailed($result, 'session_cookie_security');
    }

    public function testProductionGateFailsWhenLoginThrottlingPolicyIsUnsafe()
    {
        $this->configureSafeProductionSettings();
        config([
            'security.login_throttle.production_enforced' => false,
            'security.login_throttle.max_attempts' => 50,
            'security.login_throttle.lockout_minutes' => 0,
        ]);

        $result = app(B2BReleaseGate::class)->run(true, false);

        $this->assertFalse($result['ok']);
        $this->assertCheckFailed($result, 'login_throttle_security');
    }

    public function testProductionGateFailsWhenPasswordPolicyIsUnsafe()
    {
        $this->configureSafeProductionSettings();
        config([
            'security.password_policy.min_length' => 8,
            'security.password_policy.max_length' => 128,
            'security.password_policy.require_mixed_case' => false,
            'security.password_policy.require_numbers' => false,
            'security.password_policy.disallow_whitespace' => false,
            'security.password_policy.temporary_length' => 8,
        ]);

        $result = app(B2BReleaseGate::class)->run(true, false);

        $this->assertFalse($result['ok']);
        $this->assertCheckFailed($result, 'password_policy_security');
    }

    public function testProductionGateFailsWhenCredentialSessionRevocationCannotWork()
    {
        $this->configureSafeProductionSettings();
        config([
            'session.driver' => 'file',
            'session.table' => 'legacy_sessions',
        ]);

        $result = app(B2BReleaseGate::class)->run(true, false);

        $this->assertFalse($result['ok']);
        $this->assertCheckFailed($result, 'credential_session_revocation');
    }

    public function testProductionGateFailsWhenStructuredLoggingChannelIsNotJsonFormatted()
    {
        $this->configureSafeProductionSettings();
        config(['b2b.structured_log_channel' => 'single']);

        $result = app(B2BReleaseGate::class)->run(true, false);

        $this->assertFalse($result['ok']);
        $this->assertCheckFailed($result, 'structured_logging');
    }

    public function testProductionGateFailsWhenSchedulerHeartbeatCommandIsMissing()
    {
        $this->configureSafeProductionSettings();
        config(['b2b_queues.scheduled_commands.scheduler_heartbeat' => null]);

        $result = app(B2BReleaseGate::class)->run(true, false);

        $this->assertFalse($result['ok']);
        $this->assertCheckFailed($result, 'scheduler_config');
    }

    public function testProductionGateFailsWhenFailedJobDriverIsDisabled()
    {
        $this->configureSafeProductionSettings();
        config(['queue.failed.driver' => 'null']);

        $result = app(B2BReleaseGate::class)->run(true, false);

        $this->assertFalse($result['ok']);
        $this->assertCheckFailed($result, 'failed_job_storage');
    }

    public function testProductionGateFailsWhenDependencyAuditFindsAdvisories()
    {
        $this->configureSafeProductionSettings();

        $gate = new class extends B2BReleaseGate {
            protected function runDependencyAuditCommand()
            {
                return [
                    'exit_code' => 1,
                    'output' => json_encode([
                        'advisories' => [
                            'laravel/framework' => [
                                ['title' => 'Laravel advisory'],
                                ['title' => 'Another Laravel advisory'],
                            ],
                        ],
                        'abandoned' => [
                            'swiftmailer/swiftmailer' => 'symfony/mailer',
                        ],
                    ]),
                    'error' => '',
                ];
            }
        };

        $result = $gate->run(true, false, true);

        $this->assertFalse($result['ok']);
        $this->assertCheckFailed($result, 'dependency_audit');
    }

    public function testProductionGatePassesCleanDependencyAudit()
    {
        $this->configureSafeProductionSettings();

        $gate = new class extends B2BReleaseGate {
            protected function runDependencyAuditCommand()
            {
                return [
                    'exit_code' => 0,
                    'output' => json_encode([
                        'advisories' => [],
                        'abandoned' => [],
                    ]),
                    'error' => '',
                ];
            }
        };

        $result = $gate->run(true, false, true);

        $this->assertTrue($result['ok']);
        $this->assertCheckPassed($result, 'dependency_audit');
    }

    public function testProductionGateFailsWhenProviderWalletContractIsIncomplete()
    {
        $this->configureSafeProductionSettings();

        $gate = new class extends B2BReleaseGate {
            protected function walletContractProviders()
            {
                return [
                    new class implements GameProviderInterface {
                        public function providerCode()
                        {
                            return 'broken_provider';
                        }

                        public function health()
                        {
                            return ['ok' => true];
                        }

                        public function supportsWalletAction($action)
                        {
                            return $action === 'bet';
                        }

                        public function walletActionContracts()
                        {
                            return [
                                'bet' => [
                                    'request_fields' => ['transaction_id'],
                                    'response_fields' => ['status'],
                                ],
                            ];
                        }

                        public function walletActionContract($action)
                        {
                            $contracts = $this->walletActionContracts();

                            return isset($contracts[$action]) ? $contracts[$action] : null;
                        }

                        public function prepareLaunch(B2BGameSession $session)
                        {
                            return ['ok' => false];
                        }

                        public function refreshSession(B2BGameSession $session)
                        {
                            return ['ok' => false];
                        }

                        public function closeSession(B2BGameSession $session, $reason = null)
                        {
                            return ['ok' => false];
                        }
                    },
                ];
            }
        };

        $result = $gate->run(true, false);

        $this->assertFalse($result['ok']);
        $this->assertCheckFailed($result, 'provider_wallet_contracts');
    }

    public function testProductionGateFailsWhenLaravelSignedMiddlewareIsEnabled()
    {
        $this->configureSafeProductionSettings();

        $gate = new class extends B2BReleaseGate {
            protected function routeMiddlewareClass($alias)
            {
                if ($alias === 'signed') {
                    return 'Illuminate\Routing\Middleware\ValidateSignature';
                }

                return parent::routeMiddlewareClass($alias);
            }
        };

        $result = $gate->run(true, false);

        $this->assertFalse($result['ok']);
        $this->assertCheckFailed($result, 'laravel_security_mitigations');
    }

    public function testProductionGateFailsWhenLaravelTemporarySignedUrlsAreUsed()
    {
        $this->configureSafeProductionSettings();

        $gate = new class extends B2BReleaseGate {
            protected function usesLaravelTemporarySignedUrls()
            {
                return true;
            }
        };

        $result = $gate->run(true, false);

        $this->assertFalse($result['ok']);
        $this->assertCheckFailed($result, 'laravel_security_mitigations');
    }

    private function assertCheckFailed(array $result, $name)
    {
        foreach ($result['checks'] as $check) {
            if ($check['name'] === $name) {
                $this->assertSame('fail', $check['status']);
                return;
            }
        }

        $this->fail('Release gate check was not found: ' . $name);
    }

    private function assertCheckPassed(array $result, $name)
    {
        foreach ($result['checks'] as $check) {
            if ($check['name'] === $name) {
                $this->assertSame('pass', $check['status']);
                return;
            }
        }

        $this->fail('Release gate check was not found: ' . $name);
    }

    private function configureSafeProductionSettings()
    {
        config([
            'app.debug' => false,
            'b2b.allow_private_wallet_callbacks' => false,
            'b2b.nonce_cache_store' => 'redis',
            'b2b.rate_limit_cache_store' => 'redis',
            'b2b.scheduler_heartbeat_cache_store' => 'redis',
            'b2b.sandbox_enabled' => false,
            'b2b.structured_logging_enabled' => true,
            'b2b.structured_log_channel' => 'b2b',
            'cache.default' => 'redis',
            'queue.default' => 'redis',
            'session.secure' => true,
            'session.http_only' => true,
            'session.same_site' => 'lax',
            'session.driver' => 'database',
            'session.table' => 'sessions',
            'security.login_throttle.production_enforced' => true,
            'security.login_throttle.max_attempts' => 10,
            'security.login_throttle.lockout_minutes' => 1,
            'security.password_policy.min_length' => 12,
            'security.password_policy.max_length' => 72,
            'security.password_policy.require_mixed_case' => true,
            'security.password_policy.require_numbers' => true,
            'security.password_policy.disallow_whitespace' => true,
            'security.password_policy.temporary_length' => 16,
        ]);
    }
}
