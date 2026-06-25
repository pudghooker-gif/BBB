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
        $this->assertCheckPassed($result, 'admin_rbac_config');
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
        $this->assertCheckPassed($result, 'admin_rbac_config');
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
}
