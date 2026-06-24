<?php

namespace Tests\Unit;

use Tests\TestCase;

class B2BQueueTopologyTest extends TestCase
{
    public function testB2BQueueTopologyDefinesRequiredProductionQueues()
    {
        $this->assertSame('redis', config('b2b_queues.connection'));

        $queues = config('b2b_queues.queues');
        $workers = config('b2b_queues.workers');

        foreach ([
            'wallet_live',
            'wallet_retry',
            'provider_callbacks',
            'reporting',
            'settlement',
            'reconciliation',
            'notifications',
            'maintenance',
        ] as $key) {
            $this->assertArrayHasKey($key, $queues);
            $this->assertArrayHasKey($key, $workers);
            $this->assertSame($queues[$key], $workers[$key]['queue']);
            $this->assertSame('redis', $workers[$key]['connection']);
            $this->assertGreaterThan(0, (int) $workers[$key]['processes']);
            $this->assertGreaterThan(0, (int) $workers[$key]['timeout']);
        }
    }

    public function testB2BSupervisorTemplateCoversConfiguredQueues()
    {
        $template = file_get_contents(base_path('deploy/supervisor/b2b-workers.conf.example'));

        $this->assertStringContainsString('queue:work redis', $template);

        foreach (config('b2b_queues.queues') as $queue) {
            $this->assertStringContainsString('--queue=' . $queue, $template);
        }
    }

    public function testB2BScheduledCommandTopologyDocumentsOperationalCommands()
    {
        $commands = config('b2b_queues.scheduled_commands');

        $this->assertSame('b2b:retry-wallet --limit=50', $commands['wallet_retry']['command']);
        $this->assertSame('wallet_retry', $commands['wallet_retry']['queue']);
        $this->assertStringContainsString('b2b:reconcile-wallet', $commands['wallet_reconciliation']['command']);
        $this->assertSame('reconciliation', $commands['wallet_reconciliation']['queue']);
        $this->assertSame('b2b:close-stale-sessions --minutes=30', $commands['stale_sessions']['command']);
        $this->assertSame('maintenance', $commands['stale_sessions']['queue']);
    }
}
