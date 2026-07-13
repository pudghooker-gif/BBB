<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\B2BApiTestHelpers;
use Tests\TestCase;
use VanguardLTE\B2B\Models\B2BOperator;
use VanguardLTE\B2B\Models\B2BWalletTransaction;
use VanguardLTE\B2B\Services\B2BSchedulerHeartbeat;

class B2BMetricsEndpointTest extends TestCase
{
    use B2BApiTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetB2BTables();
        $this->resetJobsTable();
        $this->resetFailedJobsTable();
        Cache::flush();

        config([
            'cache.default' => 'array',
            'b2b.scheduler_heartbeat_cache_store' => null,
            'queue.default' => 'database',
            'b2b_queues.queues.wallet_retry' => 'b2b-wallet-retry',
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('jobs');

        parent::tearDown();
    }

    public function testMetricsEndpointExportsPrometheusAggregatesWithoutTenantIdentifiers()
    {
        $operator = $this->createB2BOperator('op_metrics_a', 'key_metrics_a', 'secret-a');
        $degraded = $this->createB2BOperator('op_metrics_b', 'key_metrics_b', 'secret-b', [
            'status' => B2BOperator::STATUS_DEGRADED,
            'circuit_open_until' => now()->addMinutes(5),
        ]);

        $this->createB2BSession($operator, 'player-a', 'sess-a', 'bookofdead', ['status' => 'active']);
        $this->createB2BSession($degraded, 'player-b', 'sess-b', 'bookofdead', ['status' => 'closed']);

        DB::table('b2b_wallet_transactions')->insert([
            $this->walletTransactionRow($operator->id, B2BWalletTransaction::TYPE_BET, B2BWalletTransaction::STATUS_SUCCESS, 'bet-1'),
            $this->walletTransactionRow($operator->id, B2BWalletTransaction::TYPE_WIN, B2BWalletTransaction::STATUS_UNKNOWN, 'win-1'),
        ]);

        DB::table('b2b_wallet_callback_logs')->insert([
            [
                'operator_id' => $operator->id,
                'wallet_transaction_id' => null,
                'direction' => 'outbound',
                'endpoint' => 'https://operator.example/wallet',
                'http_status' => 200,
                'duration_ms' => 100,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'operator_id' => $operator->id,
                'wallet_transaction_id' => null,
                'direction' => 'outbound',
                'endpoint' => 'https://operator.example/wallet',
                'http_status' => 500,
                'duration_ms' => 350,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'operator_id' => $operator->id,
                'wallet_transaction_id' => null,
                'direction' => 'outbound',
                'endpoint' => 'https://operator.example/wallet',
                'http_status' => null,
                'duration_ms' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('b2b_wallet_reconciliation_items')->insert([
            [
                'operator_id' => $operator->id,
                'wallet_transaction_id' => null,
                'transaction_uid' => 'win-1',
                'status' => 'open',
                'reason' => 'unknown_status',
                'priority' => 'normal',
                'state' => 'open',
                'detected_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('b2b_operator_health_events')->insert([
            [
                'operator_id' => $degraded->id,
                'event_type' => 'wallet_callback',
                'status' => 'failure',
                'failure_count' => 1,
                'message' => 'failed',
                'created_at' => now(),
            ],
        ]);

        DB::table('b2b_settlements')->insert([
            [
                'operator_id' => $operator->id,
                'settlement_uid' => 'settlement-1',
                'currency' => 'USD',
                'status' => 'submitted',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('jobs')->insert([
            'queue' => 'b2b-wallet-retry',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => time() - 60,
            'created_at' => time() - 120,
        ]);
        DB::table('failed_jobs')->insert([
            'uuid' => 'failed-metrics-1',
            'connection' => 'redis',
            'queue' => 'b2b-wallet-retry',
            'payload' => '{}',
            'exception' => 'RuntimeException: failed',
            'failed_at' => now()->subMinutes(10),
        ]);
        app(B2BSchedulerHeartbeat::class)->record('phpunit');

        $response = $this->get('/api/b2b/v1/metrics');
        $body = $response->getContent();

        $response->assertStatus(200);
        $this->assertStringContainsString('text/plain', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('# HELP bbb_b2b_info', $body);
        $this->assertStringContainsString('bbb_b2b_operators_total 2', $body);
        $this->assertStringContainsString('bbb_b2b_operators_status_total{status="active"} 1', $body);
        $this->assertStringContainsString('bbb_b2b_operator_circuit_open_total 1', $body);
        $this->assertStringContainsString('bbb_b2b_sessions_active_total 1', $body);
        $this->assertStringContainsString('bbb_b2b_wallet_transactions_total{status="success",type="bet"} 1', $body);
        $this->assertStringContainsString('bbb_b2b_wallet_transactions_total{status="unknown",type="win"} 1', $body);
        $this->assertStringContainsString('bbb_b2b_wallet_callbacks_total{outcome="success"} 1', $body);
        $this->assertStringContainsString('bbb_b2b_wallet_callbacks_total{outcome="failed"} 2', $body);
        $this->assertStringContainsString('bbb_b2b_wallet_callback_duration_ms_average 225', $body);
        $this->assertStringContainsString('bbb_b2b_reconciliation_items_total{state="open",status="open"} 1', $body);
        $this->assertStringContainsString('bbb_b2b_settlements_total{status="submitted"} 1', $body);
        $this->assertStringContainsString('bbb_b2b_queue_depth{queue="wallet_retry"} 1', $body);
        $this->assertStringContainsString('bbb_b2b_queue_failed_jobs_total{queue="wallet_retry"} 1', $body);
        $this->assertStringContainsString('# HELP bbb_b2b_queue_failed_job_oldest_age_seconds', $body);
        $this->assertStringContainsString('# HELP bbb_b2b_scheduler_heartbeat_age_seconds', $body);
        $this->assertStringContainsString('bbb_b2b_scheduler_heartbeat_fresh{cache_store="array"} 1', $body);
        $this->assertStringContainsString('bbb_b2b_scheduler_heartbeat_max_age_seconds{cache_store="array"} 180', $body);
        $this->assertStringContainsString('bbb_b2b_provider_health_up{provider="goldsvet_internal",status="ok"} 1', $body);
        $this->assertStringContainsString('bbb_b2b_metrics_collection_errors 0', $body);
        $this->assertStringNotContainsString('op_metrics_a', $body);
        $this->assertStringNotContainsString('op_metrics_b', $body);
    }

    private function resetJobsTable()
    {
        Schema::dropIfExists('jobs');
        Schema::create('jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
    }

    private function resetFailedJobsTable()
    {
        Schema::dropIfExists('failed_jobs');
        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('uuid')->nullable()->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
    }

    private function walletTransactionRow($operatorId, $type, $status, $uid)
    {
        return [
            'operator_id' => $operatorId,
            'operator_player_id' => null,
            'session_id' => null,
            'game_uid' => 'bookofdead',
            'round_id' => 'round-1',
            'transaction_uid' => $uid,
            'transaction_id' => $uid,
            'idempotency_key' => $uid,
            'request_hash' => hash('sha256', $uid),
            'type' => $type,
            'amount' => '1.00000000',
            'currency' => 'USD',
            'status' => $status,
            'attempts' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
