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

        $this->assertSame('b2b:retry-wallet --limit=50 --dispatch', $commands['wallet_retry']['command']);
        $this->assertSame('wallet_retry', $commands['wallet_retry']['queue']);
        $this->assertSame('b2b:recover-rollbacks --limit=50 --dispatch', $commands['wallet_rollback_recovery']['command']);
        $this->assertSame('wallet_retry', $commands['wallet_rollback_recovery']['queue']);
        $this->assertStringContainsString('b2b:reconcile-wallet', $commands['wallet_reconciliation']['command']);
        $this->assertStringContainsString('--dispatch', $commands['wallet_reconciliation']['command']);
        $this->assertSame('reconciliation', $commands['wallet_reconciliation']['queue']);
        $this->assertSame('b2b:close-stale-sessions --minutes=30 --dispatch', $commands['stale_sessions']['command']);
        $this->assertSame('maintenance', $commands['stale_sessions']['queue']);
    }

    public function testKernelSchedulesConfiguredB2BCommands()
    {
        $kernel = file_get_contents(base_path('app/Console/Kernel.php'));

        $this->assertStringContainsString('scheduleB2BCommands($schedule)', $kernel);
        $this->assertStringContainsString("config('b2b_queues.scheduled_commands'", $kernel);
        $this->assertStringContainsString('withoutOverlapping()', $kernel);
    }
}
