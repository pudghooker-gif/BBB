<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\B2BApiTestHelpers;
use Tests\TestCase;
use VanguardLTE\B2B\Models\B2BOperator;
use VanguardLTE\B2B\Models\B2BWalletTransaction;
use VanguardLTE\B2B\Services\B2BBackofficeDashboardQuery;

class B2BBackofficeDashboardQueryTest extends TestCase
{
    use B2BApiTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetB2BTables();
    }

    public function testDashboardSnapshotAggregatesB2BOperationsWithoutRawPayloads()
    {
        $operator = $this->createB2BOperator('op_backoffice_a', 'key_backoffice_a', 'secret-a');
        $degraded = $this->createB2BOperator('op_backoffice_b', 'key_backoffice_b', 'secret-b', [
            'status' => B2BOperator::STATUS_DEGRADED,
            'circuit_open_until' => now()->addMinutes(10),
        ]);

        $this->createB2BSession($operator, 'player-a', 'sess-backoffice-a', 'bookofdead', ['status' => 'active']);
        $this->createB2BSession($degraded, 'player-b', 'sess-backoffice-b', 'bookofdead', ['status' => 'closed']);

        DB::table('b2b_wallet_transactions')->insert([
            $this->walletTransactionRow($operator->id, B2BWalletTransaction::TYPE_BET, B2BWalletTransaction::STATUS_PENDING, 'pending-1'),
            $this->walletTransactionRow($operator->id, B2BWalletTransaction::TYPE_WIN, B2BWalletTransaction::STATUS_UNKNOWN, 'unknown-1'),
            $this->walletTransactionRow($operator->id, B2BWalletTransaction::TYPE_ROLLBACK, B2BWalletTransaction::STATUS_MANUAL_REVIEW, 'manual-1'),
        ]);

        DB::table('b2b_wallet_reconciliation_items')->insert([
            [
                'operator_id' => $operator->id,
                'wallet_transaction_id' => null,
                'transaction_uid' => 'unknown-1',
                'status' => 'open',
                'reason' => 'unknown_status',
                'priority' => 'high',
                'state' => 'open',
                'detected_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('b2b_settlements')->insert([
            [
                'operator_id' => $operator->id,
                'settlement_uid' => 'settlement-backoffice-1',
                'currency' => 'USD',
                'status' => 'submitted',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $snapshot = app(B2BBackofficeDashboardQuery::class)->snapshot();

        $this->assertSame(2, $snapshot['summary']['operators_total']);
        $this->assertSame(1, $snapshot['summary']['operators_active']);
        $this->assertSame(1, $snapshot['summary']['operators_degraded']);
        $this->assertSame(1, $snapshot['summary']['operator_circuits_open']);
        $this->assertSame(1, $snapshot['summary']['sessions_active']);
        $this->assertSame(1, $snapshot['summary']['wallet_pending']);
        $this->assertSame(1, $snapshot['summary']['wallet_unknown']);
        $this->assertSame(1, $snapshot['summary']['wallet_manual_review']);
        $this->assertSame(1, $snapshot['summary']['reconciliation_open']);
        $this->assertSame(1, $snapshot['summary']['settlements_submitted']);
        $this->assertSame(1, $snapshot['wallet_statuses']['pending']);
        $this->assertSame(1, $snapshot['wallet_statuses']['unknown']);
        $this->assertCount(3, $snapshot['recent_wallet_transactions']);
        $this->assertCount(1, $snapshot['recent_reconciliation_items']);
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
