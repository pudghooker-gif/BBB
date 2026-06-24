<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\B2BApiTestHelpers;
use Tests\TestCase;
use VanguardLTE\B2B\Services\WalletManualActionService;

class B2BWalletManualActionTest extends TestCase
{
    use B2BApiTestHelpers;

    private $operator;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->resetB2BTables();
        $this->operator = $this->createB2BOperator('op_manual', 'key_manual', 'manual_secret_1234567890');
    }

    public function testManualReviewActionRecordsAuditTransitionAndReconciliationItem()
    {
        $transactionId = $this->insertWalletTransaction('tx_manual_review', 'unknown');

        $result = app(WalletManualActionService::class)->apply(
            'tx_manual_review',
            'mark-review',
            'Operator status cannot be confirmed automatically.',
            'ops_user',
            $this->operator->id
        );

        $this->assertEquals((string) $transactionId, (string) $result['wallet_transaction_id']);
        $this->assertSame('unknown', $result['from_status']);
        $this->assertSame('manual_review', $result['to_status']);

        $transaction = DB::table('b2b_wallet_transactions')->where('id', $transactionId)->first();
        $this->assertSame('manual_review', $transaction->status);

        $manualAction = DB::table('b2b_wallet_manual_actions')->where('wallet_transaction_id', $transactionId)->first();
        $this->assertSame('mark-review', $manualAction->action);
        $this->assertSame('unknown', $manualAction->from_status);
        $this->assertSame('manual_review', $manualAction->to_status);
        $this->assertSame('ops_user', $manualAction->actor);

        $transition = DB::table('b2b_wallet_transaction_transitions')
            ->where('wallet_transaction_id', $transactionId)
            ->orderByDesc('id')
            ->first();

        $this->assertSame('unknown', $transition->from_status);
        $this->assertSame('manual_review', $transition->to_status);
        $this->assertSame('manual_mark-review', $transition->reason);
        $this->assertSame('manual:ops_user', $transition->actor);

        $item = DB::table('b2b_wallet_reconciliation_items')->where('wallet_transaction_id', $transactionId)->first();
        $this->assertSame('manual_review', $item->status);
        $this->assertSame('manual_review', $item->reason);
        $this->assertSame('medium', $item->priority);
        $this->assertSame('open', $item->state);
    }

    public function testManualResolveSuccessClosesOpenReconciliationItems()
    {
        $transactionId = $this->insertWalletTransaction('tx_manual_success', 'manual_review');

        DB::table('b2b_wallet_reconciliation_items')->insert([
            'wallet_transaction_id' => $transactionId,
            'operator_id' => $this->operator->id,
            'transaction_uid' => 'tx_manual_success',
            'status' => 'manual_review',
            'reason' => 'manual_review',
            'priority' => 'medium',
            'state' => 'open',
            'detected_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(WalletManualActionService::class)->apply(
            'tx_manual_success',
            'resolve-success',
            'Operator confirmed transaction was accepted.',
            'finance_user',
            $this->operator->id
        );

        $transaction = DB::table('b2b_wallet_transactions')->where('id', $transactionId)->first();
        $this->assertSame('success', $transaction->status);
        $this->assertNull($transaction->last_error);

        $item = DB::table('b2b_wallet_reconciliation_items')->where('wallet_transaction_id', $transactionId)->first();
        $this->assertSame('resolved', $item->state);
        $this->assertNotNull($item->resolved_at);

        $manualActions = DB::table('b2b_wallet_manual_actions')->where('wallet_transaction_id', $transactionId)->count();
        $this->assertSame(1, $manualActions);
    }

    public function testManualActionCommandRequiresActorAndReason()
    {
        $this->insertWalletTransaction('tx_manual_command_guard', 'unknown');

        $exitCode = Artisan::call('b2b:wallet-manual-action', [
            'transaction_uid' => 'tx_manual_command_guard',
            'action' => 'mark-review',
            '--operator-id' => $this->operator->id,
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertSame(0, DB::table('b2b_wallet_manual_actions')->count());
    }

    public function testManualActionCommandAppliesAuditedDeadLetter()
    {
        $transactionId = $this->insertWalletTransaction('tx_manual_command', 'unknown');

        $exitCode = Artisan::call('b2b:wallet-manual-action', [
            'transaction_uid' => 'tx_manual_command',
            'action' => 'dead-letter',
            '--operator-id' => $this->operator->id,
            '--actor' => 'ops_user',
            '--reason' => 'Provider could not confirm final state.',
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertSame('dead_letter', DB::table('b2b_wallet_transactions')->where('id', $transactionId)->value('status'));
        $this->assertSame('dead-letter', DB::table('b2b_wallet_manual_actions')->where('wallet_transaction_id', $transactionId)->value('action'));
    }

    private function insertWalletTransaction($transactionUid, $status)
    {
        return DB::table('b2b_wallet_transactions')->insertGetId([
            'operator_id' => $this->operator->id,
            'session_id' => 'sess_manual',
            'game_uid' => 'book_manual',
            'round_id' => 'round_manual',
            'transaction_uid' => $transactionUid,
            'transaction_id' => $transactionUid,
            'idempotency_key' => sha1($transactionUid),
            'type' => 'bet',
            'amount' => '10.00000000',
            'currency' => 'USD',
            'status' => $status,
            'attempts' => 2,
            'raw_request' => json_encode([
                'transaction_id' => $transactionUid,
                'round_id' => 'round_manual',
                'amount' => '10.00000000',
                'currency' => 'USD',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
