<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\B2BApiTestHelpers;
use Tests\TestCase;
use VanguardLTE\B2B\Services\WalletRollbackRecoveryService;

class B2BWalletRollbackRecoveryTest extends TestCase
{
    use B2BApiTestHelpers;

    private $operator;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->resetB2BTables();
        $this->operator = $this->createB2BOperator('op_rollback', 'key_rollback', 'rollback_secret_1234567890', [
            'wallet_callback_url' => 'https://wallet-rollback.test/callback',
        ]);
    }

    public function testRollbackRecoveryMarksTransactionReversedAndResolvesOpenItems()
    {
        Http::fake([
            'wallet-rollback.test/*' => Http::response([
                'status' => 'accepted',
                'api_key' => 'rollback-response-secret',
            ], 200),
        ]);

        $transactionId = $this->insertRollbackTransaction('tx_rollback_success');
        $this->insertReconciliationItem($transactionId, 'tx_rollback_success', 'rollback_required');

        $result = app(WalletRollbackRecoveryService::class)->recover(10);

        $this->assertSame(1, $result['processed']);
        $this->assertSame(1, $result['reversed']);
        $this->assertSame(0, $result['failed']);
        $this->assertSame(0, $result['manual_review']);

        $transaction = DB::table('b2b_wallet_transactions')->where('id', $transactionId)->first();
        $this->assertSame('reversed', $transaction->status);
        $this->assertNull($transaction->last_error);

        $operatorResponse = json_decode($transaction->operator_response_body, true);
        $this->assertSame('accepted', $operatorResponse['status']);
        $this->assertSame('[REDACTED]', $operatorResponse['api_key']);

        $transition = DB::table('b2b_wallet_transaction_transitions')
            ->where('wallet_transaction_id', $transactionId)
            ->orderByDesc('id')
            ->first();

        $this->assertSame('rollback_required', $transition->from_status);
        $this->assertSame('reversed', $transition->to_status);
        $this->assertSame('wallet_rollback_recovery_result', $transition->reason);

        $attempt = DB::table('b2b_wallet_transaction_attempts')
            ->where('wallet_transaction_id', $transactionId)
            ->first();

        $this->assertSame('rollback', $attempt->type);
        $this->assertSame('success', $attempt->result);

        $requestBody = json_decode($attempt->request_body, true);
        $this->assertSame('rollback', $requestBody['action']);
        $this->assertSame('rollback_tx_rollback_success', $requestBody['transaction_id']);
        $this->assertSame('tx_rollback_success', $requestBody['original_transaction_id']);
        $this->assertSame('rollback_required', $requestBody['recovery_reason']);

        $responseBody = json_decode($attempt->response_body, true);
        $this->assertSame('[REDACTED]', $responseBody['api_key']);

        $item = DB::table('b2b_wallet_reconciliation_items')
            ->where('wallet_transaction_id', $transactionId)
            ->first();

        $this->assertSame('resolved', $item->state);
        $this->assertNotNull($item->resolved_at);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://wallet-rollback.test/callback'
                && $request['action'] === 'rollback'
                && $request['transaction_id'] === 'rollback_tx_rollback_success'
                && $request['original_transaction_id'] === 'tx_rollback_success';
        });
    }

    public function testRollbackRecoveryKeepsRollbackRequiredOpenBeforeBudgetIsExhausted()
    {
        config(['b2b.wallet_rollback_max_attempts' => 2]);
        Http::fake([
            'wallet-rollback.test/*' => Http::response([
                'status' => 'failed',
                'api_key' => 'rollback-failed-secret',
            ], 500),
        ]);

        $transactionId = $this->insertRollbackTransaction('tx_rollback_retry');

        $result = app(WalletRollbackRecoveryService::class)->recover(10);

        $this->assertSame(1, $result['processed']);
        $this->assertSame(0, $result['reversed']);
        $this->assertSame(1, $result['failed']);
        $this->assertSame(0, $result['manual_review']);

        $transaction = DB::table('b2b_wallet_transactions')->where('id', $transactionId)->first();
        $this->assertSame('rollback_required', $transaction->status);
        $this->assertSame(500, (int) $transaction->operator_response_code);

        $item = DB::table('b2b_wallet_reconciliation_items')
            ->where('wallet_transaction_id', $transactionId)
            ->first();

        $this->assertSame('rollback_required', $item->reason);
        $this->assertSame('high', $item->priority);
        $this->assertSame('open', $item->state);
        $context = json_decode($item->context, true);
        $this->assertSame(1, $context['rollback_recovery']['attempts']);
        $this->assertSame(2, $context['rollback_recovery']['max_attempts']);
    }

    public function testRollbackRecoveryMovesToManualReviewWhenBudgetIsExhausted()
    {
        config(['b2b.wallet_rollback_max_attempts' => 2]);
        Http::fake([
            'wallet-rollback.test/*' => Http::response(['status' => 'failed'], 500),
        ]);

        $transactionId = $this->insertRollbackTransaction('tx_rollback_budget');
        $this->insertReconciliationItem($transactionId, 'tx_rollback_budget', 'rollback_required');
        $this->insertRollbackAttempt($transactionId, 'tx_rollback_budget');

        $result = app(WalletRollbackRecoveryService::class)->recover(10);

        $this->assertSame(1, $result['processed']);
        $this->assertSame(0, $result['reversed']);
        $this->assertSame(0, $result['failed']);
        $this->assertSame(1, $result['manual_review']);

        $transaction = DB::table('b2b_wallet_transactions')->where('id', $transactionId)->first();
        $this->assertSame('manual_review', $transaction->status);
        $this->assertStringContainsString('Rollback recovery budget exhausted', $transaction->last_error);

        $transition = DB::table('b2b_wallet_transaction_transitions')
            ->where('wallet_transaction_id', $transactionId)
            ->orderByDesc('id')
            ->first();

        $this->assertSame('rollback_required', $transition->from_status);
        $this->assertSame('manual_review', $transition->to_status);
        $this->assertSame('wallet_rollback_recovery_budget_exhausted', $transition->reason);

        $rollbackItem = DB::table('b2b_wallet_reconciliation_items')
            ->where('wallet_transaction_id', $transactionId)
            ->where('reason', 'rollback_required')
            ->first();
        $this->assertSame('resolved', $rollbackItem->state);

        $manualItem = DB::table('b2b_wallet_reconciliation_items')
            ->where('wallet_transaction_id', $transactionId)
            ->where('reason', 'manual_review')
            ->first();
        $this->assertSame('open', $manualItem->state);
        $this->assertSame('medium', $manualItem->priority);
    }

    private function insertRollbackTransaction($transactionUid)
    {
        return DB::table('b2b_wallet_transactions')->insertGetId([
            'operator_id' => $this->operator->id,
            'session_id' => 'sess_rollback',
            'game_uid' => 'book_rollback',
            'round_id' => 'round_rollback',
            'transaction_uid' => $transactionUid,
            'transaction_id' => $transactionUid,
            'idempotency_key' => sha1($transactionUid),
            'type' => 'bet',
            'amount' => '10.00000000',
            'currency' => 'USD',
            'status' => 'rollback_required',
            'attempts' => 1,
            'raw_request' => json_encode([
                'player_id' => 'player_rollback',
                'game_id' => 'book_rollback',
                'session_id' => 'sess_rollback',
                'round_id' => 'round_rollback',
                'transaction_id' => $transactionUid,
                'amount' => '10.00000000',
                'currency' => 'USD',
                'access_token' => 'rollback-request-secret',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertReconciliationItem($transactionId, $transactionUid, $reason)
    {
        DB::table('b2b_wallet_reconciliation_items')->insert([
            'wallet_transaction_id' => $transactionId,
            'operator_id' => $this->operator->id,
            'transaction_uid' => $transactionUid,
            'status' => 'rollback_required',
            'reason' => $reason,
            'priority' => 'high',
            'state' => 'open',
            'context' => json_encode(['token' => 'rollback-item-secret']),
            'detected_at' => now()->subMinute(),
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);
    }

    private function insertRollbackAttempt($transactionId, $transactionUid)
    {
        DB::table('b2b_wallet_transaction_attempts')->insert([
            'wallet_transaction_id' => $transactionId,
            'operator_id' => $this->operator->id,
            'transaction_uid' => $transactionUid,
            'type' => 'rollback',
            'attempt_no' => 1,
            'result' => 'failed',
            'http_status' => 500,
            'request_body' => json_encode(['transaction_id' => 'rollback_'.$transactionUid]),
            'response_body' => json_encode(['status' => 'failed']),
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);
    }
}
