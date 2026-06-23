<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\B2BApiTestHelpers;
use Tests\TestCase;
use VanguardLTE\B2B\Services\WalletReconciliationService;

class B2BWalletReconciliationTest extends TestCase
{
    use B2BApiTestHelpers;

    private $operator;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->resetB2BTables();
        $this->operator = $this->createB2BOperator('op_reconcile', 'key_reconcile', 'reconcile_secret_1234567890');
    }

    public function testReconciliationMovesStalePendingTransactionToUnknownAndOpensItem()
    {
        $transactionId = $this->insertWalletTransaction('tx_stale_pending', 'pending', 0, now()->subMinutes(20));

        $result = app(WalletReconciliationService::class)->scan(10, 5);

        $this->assertSame(1, $result['processed']);
        $this->assertSame(1, $result['opened']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame(1, $result['transitioned_unknown']);

        $transaction = DB::table('b2b_wallet_transactions')->where('id', $transactionId)->first();
        $this->assertSame('unknown', $transaction->status);

        $transition = DB::table('b2b_wallet_transaction_transitions')
            ->where('wallet_transaction_id', $transactionId)
            ->orderByDesc('id')
            ->first();

        $this->assertSame('pending', $transition->from_status);
        $this->assertSame('unknown', $transition->to_status);
        $this->assertSame('wallet_reconciliation_stale_pending', $transition->reason);

        $item = DB::table('b2b_wallet_reconciliation_items')
            ->where('wallet_transaction_id', $transactionId)
            ->first();

        $this->assertSame('unknown', $item->status);
        $this->assertSame('unknown_result', $item->reason);
        $this->assertSame('medium', $item->priority);
        $this->assertSame('open', $item->state);
    }

    public function testReconciliationOpensRetryBudgetItemWithoutChangingTimeoutStatus()
    {
        config(['b2b.wallet_retry_max_attempts' => 3]);
        $transactionId = $this->insertWalletTransaction('tx_retry_budget_scan', 'timeout', 3, now()->subMinutes(2));

        $result = app(WalletReconciliationService::class)->scan(10, 5);

        $this->assertSame(1, $result['processed']);
        $this->assertSame(1, $result['opened']);
        $this->assertSame(0, $result['transitioned_unknown']);

        $transaction = DB::table('b2b_wallet_transactions')->where('id', $transactionId)->first();
        $this->assertSame('timeout', $transaction->status);

        $item = DB::table('b2b_wallet_reconciliation_items')
            ->where('wallet_transaction_id', $transactionId)
            ->first();

        $this->assertSame('retry_budget_exhausted', $item->reason);
        $this->assertSame('timeout', $item->status);
        $this->assertSame('normal', $item->priority);
    }

    public function testReconciliationUpdatesExistingOpenItemInsteadOfDuplicating()
    {
        $transactionId = $this->insertWalletTransaction('tx_duplicate_recon', 'dead_letter', 4, now()->subMinutes(1));

        app(WalletReconciliationService::class)->scan(10, 5);
        $result = app(WalletReconciliationService::class)->scan(10, 5);

        $this->assertSame(1, $result['processed']);
        $this->assertSame(0, $result['opened']);
        $this->assertSame(1, $result['updated']);
        $this->assertSame(1, DB::table('b2b_wallet_reconciliation_items')->where('wallet_transaction_id', $transactionId)->count());
    }

    private function insertWalletTransaction($transactionUid, $status, $attempts, $createdAt)
    {
        return DB::table('b2b_wallet_transactions')->insertGetId([
            'operator_id' => $this->operator->id,
            'session_id' => 'sess_reconcile',
            'game_uid' => 'book_reconcile',
            'round_id' => 'round_reconcile',
            'transaction_uid' => $transactionUid,
            'transaction_id' => $transactionUid,
            'idempotency_key' => sha1($transactionUid),
            'type' => 'bet',
            'amount' => '10.00000000',
            'currency' => 'USD',
            'status' => $status,
            'attempts' => $attempts,
            'raw_request' => json_encode([
                'transaction_id' => $transactionUid,
                'round_id' => 'round_reconcile',
                'amount' => '10.00000000',
                'currency' => 'USD',
            ]),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
