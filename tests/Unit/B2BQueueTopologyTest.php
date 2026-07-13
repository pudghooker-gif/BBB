<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
        $this->assertStringContainsString('--tries=', $template);
        $this->assertStringContainsString('--timeout=', $template);

        foreach (config('b2b_queues.queues') as $queue) {
            $this->assertStringContainsString('--queue=' . $queue, $template);
        }
    }

    public function testFailedJobStorageUsesDatabaseProviderAndMigration()
    {
        $this->assertSame('database-uuids', config('queue.failed.driver'));
        $this->assertSame('failed_jobs', config('queue.failed.table'));

        $migration = file_get_contents(base_path('database/migrations/2026_06_24_000008_create_queue_runtime_tables.php'));

        $this->assertStringContainsString("Schema::create('jobs'", $migration);
        $this->assertStringContainsString("Schema::create('failed_jobs'", $migration);
        $this->assertStringContainsString("'uuid'", $migration);
        $this->assertStringContainsString("'failed_at'", $migration);
    }

    public function testB2BScheduledCommandTopologyDocumentsOperationalCommands()
    {
        $commands = config('b2b_queues.scheduled_commands');

        $this->assertSame('b2b:scheduler-heartbeat --source=scheduler', $commands['scheduler_heartbeat']['command']);
        $this->assertSame('everyMinute', $commands['scheduler_heartbeat']['frequency']);
        $this->assertSame('maintenance', $commands['scheduler_heartbeat']['queue']);
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

    public function testQueueRuntimeEvidenceCommandVerifiesWorkersSchedulerAndFailedJobs()
    {
        $directory = storage_path('framework/testing');
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $artifact = $directory . DIRECTORY_SEPARATOR . 'b2b-queue-runtime-evidence.json';
        $statusFile = $directory . DIRECTORY_SEPARATOR . 'b2b-supervisor-status.txt';
        $failedTable = config('queue.failed.table', 'failed_jobs');
        $createdFailedJobsTable = false;
        $queueNames = array_values(config('b2b_queues.queues'));

        if (!Schema::hasTable($failedTable)) {
            Schema::create($failedTable, function (Blueprint $table) {
                $table->id();
                $table->string('uuid')->nullable();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->nullable();
            });
            $createdFailedJobsTable = true;
        }

        if (Schema::hasColumn($failedTable, 'queue')) {
            DB::table($failedTable)->whereIn('queue', $queueNames)->delete();
        }

        $lines = [];
        foreach (config('b2b_queues.workers') as $key => $worker) {
            $program = 'bbb-b2b-' . str_replace('_', '-', $key);
            for ($index = 0; $index < (int) $worker['processes']; $index++) {
                $lines[] = sprintf(
                    '%s:%s_%02d RUNNING pid %d, uptime 0:01:00',
                    $program,
                    $program,
                    $index,
                    4000 + $index
                );
            }
        }

        file_put_contents($statusFile, implode(PHP_EOL, $lines) . PHP_EOL);
        @unlink($artifact);

        try {
            $exitCode = Artisan::call('b2b:queue-runtime-evidence', [
                '--artifact' => $artifact,
                '--supervisor-status-file' => $statusFile,
                '--production' => true,
            ]);

            $this->assertSame(0, $exitCode, Artisan::output());
            $this->assertFileExists($artifact);

            $payload = json_decode(file_get_contents($artifact), true);
            $this->assertSame('passed', $payload['status']);
            $this->assertTrue($payload['supervisor']['status_file_supplied']);
            $this->assertTrue($payload['scheduler']['heartbeat_configured']);
            $this->assertTrue($payload['scheduler']['without_overlapping_configured']);
            $this->assertSame(0, $payload['failed_jobs']['total_b2b_failed']);

            foreach (config('b2b_queues.workers') as $key => $worker) {
                $this->assertSame((int) $worker['processes'], $payload['workers'][$key]['running_processes']);
                $this->assertSame('passed', $payload['workers'][$key]['status']);
            }
        } finally {
            @unlink($artifact);
            @unlink($statusFile);
            if (Schema::hasTable($failedTable) && Schema::hasColumn($failedTable, 'queue')) {
                DB::table($failedTable)->whereIn('queue', $queueNames)->delete();
            }
            if ($createdFailedJobsTable && Schema::hasTable($failedTable)) {
                Schema::drop($failedTable);
            }
        }
    }
}
