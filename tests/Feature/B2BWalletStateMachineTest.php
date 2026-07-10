<?php

namespace Tests\Feature;

use InvalidArgumentException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\B2BApiTestHelpers;
use Tests\TestCase;
use VanguardLTE\B2B\Services\WalletTransactionStateMachine;
use VanguardLTE\B2B\Services\WalletTransactionService;

class B2BWalletStateMachineTest extends TestCase
{
    use B2BApiTestHelpers;

    private $operator;
    private $operatorUid = 'op_wallet_state';
    private $keyId = 'key_wallet_state';
    private $secret = 'wallet_state_secret_1234567890';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->resetB2BTables();
        $this->operator = $this->createB2BOperator($this->operatorUid, $this->keyId, $this->secret, [
            'wallet_callback_url' => 'http://wallet.example/callback',
        ]);
        $this->createB2BSession($this->operator, 'player_state', 'sess_state', 'book_of_state');

        Http::fake([
            'wallet.example/*' => Http::response([
                'status' => 'ok',
                'balance' => '100.00000000',
            ], 200),
        ]);
    }

    public function testWalletCallbackCreatesAppendOnlyStatusTransitions()
    {
        $body = $this->walletBody('tx_state_machine', 'round_state_machine', '10.00000000');

        $this->signedPost('/api/b2b/v1/wallet/bet', $body, 'wallet-state-machine', 'req-wallet-state')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('request_id', 'req-wallet-state')
            ->assertJsonPath('data.status', 'success');

        $transaction = DB::table('b2b_wallet_transactions')
            ->where('transaction_id', 'tx_state_machine')
            ->first();

        $this->assertNotNull($transaction);
        $this->assertSame('success', $transaction->status);

        $transitions = DB::table('b2b_wallet_transaction_transitions')
            ->where('wallet_transaction_id', $transaction->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $transitions);
        $this->assertNull($transitions[0]->from_status);
        $this->assertSame('pending', $transitions[0]->to_status);
        $this->assertSame('wallet_transaction_created', $transitions[0]->reason);
        $this->assertSame('pending', $transitions[1]->from_status);
        $this->assertSame('success', $transitions[1]->to_status);
        $this->assertSame('wallet_callback_result', $transitions[1]->reason);

        $transitionContext = json_decode($transitions[1]->context, true);
        $this->assertSame('req-wallet-state', $transitionContext['request_id']);

        $attempt = DB::table('b2b_wallet_transaction_attempts')
            ->where('wallet_transaction_id', $transaction->id)
            ->first();
        $attemptRequest = json_decode($attempt->request_body, true);

        $this->assertSame('req-wallet-state', $attemptRequest['_context']['request_id']);
        $this->assertSame($transaction->transaction_uid, $attemptRequest['_context']['transaction_uid']);

        Http::assertSent(function ($request) use ($transaction) {
            return $request->url() === 'http://wallet.example/callback'
                && $request->hasHeader('X-Request-Id', 'req-wallet-state')
                && $request->hasHeader('X-B2B-Transaction-Uid', $transaction->transaction_uid);
        });
    }

    public function testRetryMovesFailedTransactionThroughTransitionLog()
    {
        $transactionId = $this->insertWalletTransaction('tx_retry_success', 'round_retry_success', 'failed', 1);

        $result = app(WalletTransactionService::class)->retryPending(1);

        $this->assertSame(1, $result['processed']);
        $this->assertSame(0, $result['dead_lettered']);

        $transaction = DB::table('b2b_wallet_transactions')->where('id', $transactionId)->first();
        $this->assertSame('success', $transaction->status);
        $this->assertSame(2, (int) $transaction->attempts);

        $transition = DB::table('b2b_wallet_transaction_transitions')
            ->where('wallet_transaction_id', $transactionId)
            ->orderByDesc('id')
            ->first();

        $this->assertSame('failed', $transition->from_status);
        $this->assertSame('success', $transition->to_status);
        $this->assertSame('wallet_retry_result', $transition->reason);
        Http::assertSentCount(1);
    }

    public function testRetryBudgetMovesTransactionToDeadLetterWithoutCallback()
    {
        config(['b2b.wallet_retry_max_attempts' => 2]);

        $transactionId = $this->insertWalletTransaction('tx_retry_budget', 'round_retry_budget', 'timeout', 2);

        $result = app(WalletTransactionService::class)->retryPending(1);

        $this->assertSame(1, $result['processed']);
        $this->assertSame(1, $result['dead_lettered']);

        $transaction = DB::table('b2b_wallet_transactions')->where('id', $transactionId)->first();
        $this->assertSame('dead_letter', $transaction->status);
        $this->assertSame(2, (int) $transaction->attempts);

        $transition = DB::table('b2b_wallet_transaction_transitions')
            ->where('wallet_transaction_id', $transactionId)
            ->orderByDesc('id')
            ->first();

        $this->assertSame('timeout', $transition->from_status);
        $this->assertSame('dead_letter', $transition->to_status);
        $this->assertSame('wallet_retry_budget_exhausted', $transition->reason);
        Http::assertSentCount(0);
    }

    public function testIllegalStateTransitionDoesNotMutateTransactionOrAppendTransition()
    {
        $transactionId = $this->insertWalletTransaction('tx_illegal_transition', 'round_illegal_transition', 'success', 1);
        $transaction = DB::table('b2b_wallet_transactions')->where('id', $transactionId)->first();
        $transitionCount = DB::table('b2b_wallet_transaction_transitions')
            ->where('wallet_transaction_id', $transactionId)
            ->count();

        try {
            app(WalletTransactionStateMachine::class)->transition($transaction, 'failed', 'illegal_test_transition');
            $this->fail('Illegal wallet transition should throw.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Illegal wallet transaction transition from success to failed', $e->getMessage());
        }

        $fresh = DB::table('b2b_wallet_transactions')->where('id', $transactionId)->first();
        $this->assertSame('success', $fresh->status);
        $this->assertSame(
            $transitionCount,
            DB::table('b2b_wallet_transaction_transitions')->where('wallet_transaction_id', $transactionId)->count()
        );
    }

    public function testTerminalStateTransitionRulesPreventSilentFinancialRewrites()
    {
        $machine = app(WalletTransactionStateMachine::class);

        $this->assertFalse($machine->canTransition('success', 'failed'));
        $this->assertFalse($machine->canTransition('reversed', 'success'));
        $this->assertFalse($machine->canTransition('dead_letter', 'success'));
        $this->assertTrue($machine->canTransition('success', 'rollback_required'));
        $this->assertTrue($machine->canTransition('rollback_required', 'reversed'));
        $this->assertTrue($machine->canTransition('dead_letter', 'manual_review'));
    }

    private function signedPost($uri, $body, $nonce, $requestId = null)
    {
        $headers = $this->signedB2BHeaders($this->operatorUid, $this->keyId, $this->secret, 'POST', $uri, $body, $nonce);
        if ($requestId !== null) {
            $headers['X-Request-Id'] = $requestId;
        }

        return $this->signedB2BRequest('POST', $uri, $body, $headers);
    }

    private function walletBody($transactionId, $roundId, $amount)
    {
        return json_encode([
            'player_id' => 'player_state',
            'game_id' => 'book_of_state',
            'session_id' => 'sess_state',
            'round_id' => $roundId,
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'currency' => 'USD',
        ]);
    }

    private function insertWalletTransaction($transactionId, $roundId, $status, $attempts)
    {
        return DB::table('b2b_wallet_transactions')->insertGetId([
            'operator_id' => $this->operator->id,
            'operator_player_id' => null,
            'session_id' => 'sess_state',
            'game_uid' => 'book_of_state',
            'round_id' => $roundId,
            'transaction_uid' => $transactionId.'_uid',
            'transaction_id' => $transactionId,
            'idempotency_key' => sha1($transactionId),
            'request_hash' => str_repeat('a', 64),
            'type' => 'bet',
            'amount' => '10.00000000',
            'currency' => 'USD',
            'status' => $status,
            'raw_request' => $this->walletBody($transactionId, $roundId, '10.00000000'),
            'attempts' => $attempts,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
