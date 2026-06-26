<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\B2BApiTestHelpers;
use Tests\TestCase;

class B2BReadinessEndpointTest extends TestCase
{
    use B2BApiTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->resetB2BTables();
    }

    public function testReadinessEndpointReportsReadyWhenCriticalDependenciesPass()
    {
        $response = $this->getJson('/api/b2b/v1/readiness');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.service', 'bbb-b2b')
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.checks.0.name', 'database');

        $this->assertNotEmpty($response->headers->get('X-Request-Id'));
        $this->assertReadinessCheck($response->json('data.checks'), 'b2b_tables', 'pass');
    }

    public function testReadinessEndpointFailsWhenCriticalB2BTableIsMissing()
    {
        Schema::dropIfExists('b2b_wallet_transactions');

        $response = $this->getJson('/api/b2b/v1/readiness');

        $response->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'SERVICE_NOT_READY')
            ->assertJsonPath('error.details.status', 'not_ready');

        $this->assertReadinessCheck($response->json('error.details.checks'), 'b2b_tables', 'fail');
    }

    public function testReadinessEndpointFailsUnsafeProductionRuntimeConfiguration()
    {
        config([
            'app.env' => 'production',
            'app.debug' => true,
            'b2b.allow_private_wallet_callbacks' => true,
            'b2b.nonce_cache_store' => null,
            'b2b.rate_limit_cache_store' => null,
            'b2b.sandbox_enabled' => true,
            'cache.default' => 'array',
            'queue.default' => 'sync',
        ]);

        $response = $this->getJson('/api/b2b/v1/readiness');

        $response->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'SERVICE_NOT_READY')
            ->assertJsonPath('error.details.production_mode', true);

        $checks = $response->json('error.details.checks');
        $this->assertReadinessCheck($checks, 'cache_runtime', 'fail');
        $this->assertReadinessCheck($checks, 'queue_config', 'fail');
        $this->assertReadinessCheck($checks, 'release_gate_config', 'fail');
    }

    private function assertReadinessCheck(array $checks, $name, $status)
    {
        foreach ($checks as $check) {
            if ($check['name'] === $name) {
                $this->assertSame($status, $check['status']);
                return;
            }
        }

        $this->fail('Readiness check was not found: '.$name);
    }
}
