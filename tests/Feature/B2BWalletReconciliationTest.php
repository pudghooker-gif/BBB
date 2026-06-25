<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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
        Http::fake([
            'wallet-reconcile.test/*' => Http::response(['status' => 'unknown'], 200),
        ]);
        $this->operator = $this->createB2BOperator('op_reconcile', 'key_reconcile', 'reconcile_secret_1234567890', [
            'wallet_callback_url' => 'https://wallet-reconcile.test/callback',
        ]);
    }

    public function testReconciliationMovesStalePendingTransactionToUnknownAndOpensItem()
    {
        $transactionId = $this->insertWalletTransaction('tx_stale_pending', 'pending', 0, now()->subMinutes(20));

        $result = app(WalletReconciliationService::class)->scan(10, 5);

        $this->assertSame(1, $result['processed']);
        $this->assertSame(1, $result['opened']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame(1, $result['transitioned_unknown']);
        $this->assertSame(1, $result['status_lookups']);
        $this->assertSame(0, $result['status_resolved']);

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

        $context = json_decode($item->context, true);
        $this->assertSame('unknown', $context['status_lookup']['lookup_status']);
        $this->assertFalse($context['status_lookup']['final']);
    }

    public function testReconciliationOpensRetryBudgetItemWithoutChangingTimeoutStatus()
    {
        config(['b2b.wallet_retry_max_attempts' => 3]);
        $transactionId = $this->insertWalletTransaction('tx_retry_budget_scan', 'timeout', 3, now()->subMinutes(2));

        $result = app(WalletReconciliationService::class)->scan(10, 5);

        $this->assertSame(1, $result['processed']);
        $this->assertSame(1, $result['opened']);
        $this->assertSame(0, $result['transitioned_unknown']);
        $this->assertSame(0, $result['status_lookups']);

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
        $this->assertSame(0, $result['status_lookups']);
        $this->assertSame(1, DB::table('b2b_wallet_reconciliation_items')->where('wallet_transaction_id', $transactionId)->count());
    }

    public function testReconciliationResolvesUnknownTransactionFromOperatorStatusLookup()
    {
        Http::fake([
            'wallet-success.test/*' => Http::response([
                'transaction_status' => 'settled',
                'api_key' => 'lookup-response-secret',
            ], 200),
        ]);
        DB::table('b2b_operators')
            ->where('id', $this->operator->id)
            ->update(['wallet_callback_url' => 'https://wallet-success.test/callback']);

        $transactionId = $this->insertWalletTransaction('tx_lookup_success', 'unknown', 1, now()->subMinutes(2));
        DB::table('b2b_wallet_reconciliation_items')->insert([
            'wallet_transaction_id' => $transactionId,
            'operator_id' => $this->operator->id,
            'transaction_uid' => 'tx_lookup_success',
            'status' => 'unknown',
            'reason' => 'unknown_result',
            'priority' => 'medium',
            'state' => 'open',
            'context' => json_encode(['token' => 'existing-reconcile-secret']),
            'detected_at' => now()->subMinute(),
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);

        $result = app(WalletReconciliationService::class)->scan(10, 5);

        $this->assertSame(1, $result['processed']);
        $this->assertSame(0, $result['opened']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame(1, $result['status_lookups']);
        $this->assertSame(1, $result['status_resolved']);

        $transaction = DB::table('b2b_wallet_transactions')->where('id', $transactionId)->first();
        $this->assertSame('success', $transaction->status);
        $this->assertNull($transaction->last_error);

        $operatorResponse = json_decode($transaction->operator_response_body, true);
        $this->assertSame('settled', $operatorResponse['transaction_status']);
        $this->assertSame('[REDACTED]', $operatorResponse['api_key']);

        $transition = DB::table('b2b_wallet_transaction_transitions')
            ->where('wallet_transaction_id', $transactionId)
            ->orderByDesc('id')
            ->first();

        $this->assertSame('unknown', $transition->from_status);
        $this->assertSame('success', $transition->to_status);
        $this->assertSame('wallet_reconciliation_status_lookup', $transition->reason);

        $attempt = DB::table('b2b_wallet_transaction_attempts')
            ->where('wallet_transaction_id', $transactionId)
            ->first();

        $this->assertSame('transaction_status', $attempt->type);
        $this->assertSame('success', $attempt->result);
        $this->assertSame(200, (int) $attempt->http_status);

        $attemptRequest = json_decode($attempt->request_body, true);
        $this->assertSame('transaction_status', $attemptRequest['action']);
        $this->assertSame('tx_lookup_success', $attemptRequest['transaction_uid']);

        $attemptResponse = json_decode($attempt->response_body, true);
        $this->assertSame('[REDACTED]', $attemptResponse['api_key']);

        $item = DB::table('b2b_wallet_reconciliation_items')
            ->where('wallet_transaction_id', $transactionId)
            ->first();

        $this->assertSame('resolved', $item->state);
        $this->assertNotNull($item->resolved_at);
        $context = json_decode($item->context, true);
        $this->assertSame('wallet_reconciliation_status_lookup', $context['resolved_by']);
        $this->assertSame('success', $context['resolved_status']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://wallet-success.test/callback'
                && $request['action'] === 'transaction_status'
                && $request['transaction_uid'] === 'tx_lookup_success';
        });
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
