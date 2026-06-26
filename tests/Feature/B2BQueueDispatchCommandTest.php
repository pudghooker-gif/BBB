<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\B2BApiTestHelpers;
use Tests\TestCase;
use VanguardLTE\B2B\Jobs\CloseStaleB2BSessionsJob;
use VanguardLTE\B2B\Jobs\RecoverWalletRollbacksJob;
use VanguardLTE\B2B\Jobs\ReconcileWalletTransactionsJob;
use VanguardLTE\B2B\Jobs\RetryWalletTransactionsJob;

class B2BQueueDispatchCommandTest extends TestCase
{
    use B2BApiTestHelpers;

    public function testWalletRetryCommandCanDispatchQueuedJob()
    {
        Queue::fake();

        $exitCode = Artisan::call('b2b:retry-wallet', [
            '--limit' => 7,
            '--dispatch' => true,
        ]);

        $this->assertSame(0, $exitCode);
        Queue::assertPushed(RetryWalletTransactionsJob::class, function ($job) {
            return $job->limit() === 7
                && $job->connection === config('b2b_queues.connection')
                && $job->queue === config('b2b_queues.queues.wallet_retry');
        });
    }

    public function testRollbackRecoveryCommandCanDispatchQueuedJob()
    {
        Queue::fake();

        $exitCode = Artisan::call('b2b:recover-rollbacks', [
            '--limit' => 9,
            '--dispatch' => true,
        ]);

        $this->assertSame(0, $exitCode);
        Queue::assertPushed(RecoverWalletRollbacksJob::class, function ($job) {
            return $job->limit() === 9
                && $job->connection === config('b2b_queues.connection')
                && $job->queue === config('b2b_queues.queues.wallet_retry');
        });
    }

    public function testReconciliationCommandCanDispatchQueuedJob()
    {
        Queue::fake();

        $exitCode = Artisan::call('b2b:reconcile-wallet', [
            '--limit' => 11,
            '--pending-minutes' => 3,
            '--dispatch' => true,
        ]);

        $this->assertSame(0, $exitCode);
        Queue::assertPushed(ReconcileWalletTransactionsJob::class, function ($job) {
            return $job->limit() === 11
                && $job->pendingMinutes() === 3
                && $job->connection === config('b2b_queues.connection')
                && $job->queue === config('b2b_queues.queues.reconciliation');
        });
    }

    public function testStaleSessionCommandCanDispatchQueuedJob()
    {
        Queue::fake();

        $exitCode = Artisan::call('b2b:close-stale-sessions', [
            '--minutes' => 45,
            '--dispatch' => true,
        ]);

        $this->assertSame(0, $exitCode);
        Queue::assertPushed(CloseStaleB2BSessionsJob::class, function ($job) {
            return $job->minutes() === 45
                && $job->connection === config('b2b_queues.connection')
                && $job->queue === config('b2b_queues.queues.maintenance');
        });
    }

    public function testStaleSessionCommandStillSupportsInlineCleanup()
    {
        Cache::flush();
        $this->resetB2BTables();
        $operator = $this->createB2BOperator('op_stale_job', 'key_stale_job', 'stale_secret_1234567890');
        $sessionId = $this->createB2BSession($operator, 'player_stale', 'sess_stale_old', 'book_stale');

        DB::table('b2b_game_sessions')
            ->where('id', $sessionId)
            ->update([
                'last_seen_at' => now()->subMinutes(90),
                'updated_at' => now()->subMinutes(90),
            ]);

        $exitCode = Artisan::call('b2b:close-stale-sessions', [
            '--minutes' => 30,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertSame('stale', DB::table('b2b_game_sessions')->where('id', $sessionId)->value('status'));
        $this->assertSame('heartbeat_timeout', DB::table('b2b_game_sessions')->where('id', $sessionId)->value('close_reason'));
    }
}
