<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use VanguardLTE\B2B\Services\B2BSchedulerHeartbeat;

class B2BSchedulerHeartbeatTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'b2b.scheduler_heartbeat_cache_store' => null,
            'b2b.scheduler_heartbeat_key' => 'b2b:test:scheduler:heartbeat',
            'b2b.scheduler_heartbeat_max_age_seconds' => 180,
        ]);
        Cache::flush();
    }

    public function testSchedulerHeartbeatCommandRecordsFreshStatus()
    {
        $exitCode = Artisan::call('b2b:scheduler-heartbeat', [
            '--source' => 'phpunit',
        ]);

        $status = app(B2BSchedulerHeartbeat::class)->status();

        $this->assertSame(0, $exitCode);
        $this->assertTrue($status['present']);
        $this->assertTrue($status['fresh']);
        $this->assertSame('phpunit', $status['source']);
        $this->assertSame('array', $status['cache_store']);
        $this->assertLessThanOrEqual(180, $status['age_seconds']);
    }

    public function testSchedulerHeartbeatStatusDetectsStalePayload()
    {
        $heartbeat = app(B2BSchedulerHeartbeat::class);

        Cache::store($heartbeat->cacheStoreName())->forever($heartbeat->cacheKey(), [
            'recorded_at' => now()->subSeconds(240)->toIso8601String(),
            'timestamp' => time() - 240,
            'source' => 'phpunit',
        ]);

        $status = $heartbeat->status();

        $this->assertTrue($status['present']);
        $this->assertFalse($status['fresh']);
        $this->assertGreaterThanOrEqual(180, $status['age_seconds']);
    }
}
