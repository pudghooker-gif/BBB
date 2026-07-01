<?php

namespace Tests\Unit;

use Tests\TestCase;
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
        ]);

        $result = app(B2BReleaseGate::class)->run(true, false);

        $this->assertFalse($result['ok']);
        $this->assertCheckFailed($result, 'nonce_cache');
        $this->assertCheckFailed($result, 'rate_limit_cache');
        $this->assertCheckFailed($result, 'queue_driver');
        $this->assertCheckFailed($result, 'app_debug');
        $this->assertCheckFailed($result, 'private_wallet_callbacks');
        $this->assertCheckFailed($result, 'sandbox_disabled');
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
            'b2b.sandbox_enabled' => false,
            'cache.default' => 'redis',
            'queue.default' => 'redis',
        ]);

        $result = app(B2BReleaseGate::class)->run(true, false);

        $this->assertTrue($result['ok']);
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
            'b2b.sandbox_enabled' => false,
            'cache.default' => 'redis',
            'queue.default' => 'redis',
        ]);
    }
}
