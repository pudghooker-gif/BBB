<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\B2BApiTestHelpers;
use Tests\TestCase;

class B2BSettlementWorkflowTest extends TestCase
{
    use B2BApiTestHelpers;

    private $operatorA;
    private $operatorB;
    private $secretA = 'settlement_secret_a_1234567890';
    private $secretB = 'settlement_secret_b_1234567890';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->resetB2BTables();
        $this->operatorA = $this->createB2BOperator('op_settlement_a', 'key_settlement_a', $this->secretA);
        $this->operatorB = $this->createB2BOperator('op_settlement_b', 'key_settlement_b', $this->secretB);
        $this->seedWalletTransactions();
    }

    public function testSignedOperatorCanExportTenantScopedSettlementSnapshot()
    {
        $response = $this->signedPost(
            'op_settlement_a',
            'key_settlement_a',
            $this->secretA,
            '/api/b2b/v1/reports/settlements/export',
            [
                'from' => now()->subDays(2)->toDateString(),
                'to' => now()->toDateString(),
                'currency' => 'USD',
                'format' => 'csv',
            ],
            'settlement-export-a'
        );

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.settlement.status', 'exported')
            ->assertJsonPath('data.settlement.currency', 'USD')
            ->assertJsonPath('data.settlement.bets_amount', '100.00000000')
            ->assertJsonPath('data.settlement.wins_amount', '40.00000000')
            ->assertJsonPath('data.settlement.refunds_amount', '5.00000000')
            ->assertJsonPath('data.settlement.ggr_amount', '55.00000000')
            ->assertJsonPath('data.settlement.net_amount', '55.00000000')
            ->assertJsonPath('data.snapshot.totals.transactions', 4)
            ->assertJsonPath('data.snapshot.by_type.bet.count', 1)
            ->assertJsonPath('data.snapshot.by_type.rollback.amount', '7.00000000')
            ->assertJsonPath('data.export.format', 'csv');

        $settlementUid = $response->json('data.settlement.settlement_uid');
        $exportHash = $response->json('data.export.sha256');
        $content = $response->json('data.export.content');

        $this->assertStringStartsWith('stl_', $settlementUid);
        $this->assertSame(64, strlen($exportHash));
        $this->assertStringContainsString('transaction_type,count,amount', $content);
        $this->assertStringContainsString('bet,1,100.00000000', $content);
        $this->assertSame(1, DB::table('b2b_settlements')->where('settlement_uid', $settlementUid)->count());

        $event = DB::table('b2b_operator_audit_events')
            ->where('event_type', 'settlement.exported')
            ->where('subject_id', $settlementUid)
            ->first();

        $this->assertNotNull($event);
        $this->assertSame((string) $this->operatorA->id, (string) $event->operator_id);
        $this->assertSame('api:op_settlement_a', $event->actor);
    }

    public function testSettlementDetailIsTenantScoped()
    {
        $export = $this->signedPost(
            'op_settlement_a',
            'key_settlement_a',
            $this->secretA,
            '/api/b2b/v1/reports/settlements/export',
            [
                'from' => now()->subDays(2)->toDateString(),
                'to' => now()->toDateString(),
                'currency' => 'USD',
            ],
            'settlement-detail-export'
        );

        $settlementUid = $export->json('data.settlement.settlement_uid');

        $operatorAResponse = $this->signedGet('op_settlement_a', 'key_settlement_a', $this->secretA, '/api/b2b/v1/reports/settlements/'.$settlementUid, 'settlement-detail-a');

        $operatorAResponse->assertStatus(200)
            ->assertJsonPath('data.settlement.settlement_uid', $settlementUid);

        $this->assertEquals((string) $this->operatorA->id, (string) $operatorAResponse->json('data.settlement.operator_id'));

        $this->signedGet('op_settlement_b', 'key_settlement_b', $this->secretB, '/api/b2b/v1/reports/settlements/'.$settlementUid, 'settlement-detail-b')
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'SETTLEMENT_NOT_FOUND');
    }

    public function testRepeatedExportKeepsExistingSnapshotFrozen()
    {
        $payload = [
            'from' => now()->subDays(2)->toDateString(),
            'to' => now()->toDateString(),
            'currency' => 'USD',
            'format' => 'csv',
        ];

        $firstExport = $this->signedPost(
            'op_settlement_a',
            'key_settlement_a',
            $this->secretA,
            '/api/b2b/v1/reports/settlements/export',
            $payload,
            'settlement-frozen-first'
        );

        $settlementUid = $firstExport->json('data.settlement.settlement_uid');
        $firstHash = $firstExport->json('data.settlement.export_hash');

        $this->insertWalletTransaction($this->operatorA->id, 'tx_settle_a_late_bet', 'bet', '999.00000000', 'USD', 'success', now());

        $secondExport = $this->signedPost(
            'op_settlement_a',
            'key_settlement_a',
            $this->secretA,
            '/api/b2b/v1/reports/settlements/export',
            $payload,
            'settlement-frozen-second'
        );

        $secondExport->assertStatus(200)
            ->assertJsonPath('data.settlement.settlement_uid', $settlementUid)
            ->assertJsonPath('data.settlement.export_hash', $firstHash)
            ->assertJsonPath('data.settlement.bets_amount', '100.00000000')
            ->assertJsonPath('data.snapshot.totals.transactions', 4);
    }

    public function testSettlementSubmitAndApproveRequireStepUpAndWriteAudit()
    {
        $export = $this->signedPost(
            'op_settlement_a',
            'key_settlement_a',
            $this->secretA,
            '/api/b2b/v1/reports/settlements/export',
            [
                'from' => now()->subDays(2)->toDateString(),
                'to' => now()->toDateString(),
                'currency' => 'USD',
            ],
            'settlement-approval-export'
        );

        $settlementUid = $export->json('data.settlement.settlement_uid');

        $denied = Artisan::call('b2b:submit-settlement', [
            'settlement_uid' => $settlementUid,
            '--actor' => 'finance_user',
            '--reason' => 'Monthly settlement close.',
            '--permission' => 'b2b.settlements.submit',
        ]);

        $this->assertSame(1, $denied);
        $this->assertSame('exported', DB::table('b2b_settlements')->where('settlement_uid', $settlementUid)->value('status'));

        $denial = DB::table('b2b_operator_audit_events')
            ->where('event_type', 'privileged_action.denied')
            ->where('subject_id', 'settlement.submit')
            ->first();

        $this->assertNotNull($denial);
        $this->assertSame('step_up_required', json_decode($denial->metadata, true)['code']);

        $submitted = Artisan::call('b2b:submit-settlement', [
            'settlement_uid' => $settlementUid,
            '--actor' => 'finance_user',
            '--reason' => 'Monthly settlement close.',
            '--permission' => 'b2b.settlements.submit',
            '--confirm' => 'SUBMIT_SETTLEMENT',
        ]);

        $this->assertSame(0, $submitted);
        $this->assertSame('submitted', DB::table('b2b_settlements')->where('settlement_uid', $settlementUid)->value('status'));
        $this->assertSame('finance_user', DB::table('b2b_settlements')->where('settlement_uid', $settlementUid)->value('submitted_by'));

        $approved = Artisan::call('b2b:approve-settlement', [
            'settlement_uid' => $settlementUid,
            'decision' => 'approve',
            '--actor' => 'finance_lead',
            '--reason' => 'Settlement totals match finance reconciliation.',
            '--permission' => 'b2b.settlements.approve',
            '--confirm' => 'APPROVE_SETTLEMENT',
        ]);

        $this->assertSame(0, $approved);
        $this->assertSame('approved', DB::table('b2b_settlements')->where('settlement_uid', $settlementUid)->value('status'));
        $this->assertSame('finance_lead', DB::table('b2b_settlements')->where('settlement_uid', $settlementUid)->value('approved_by'));

        $event = DB::table('b2b_operator_audit_events')
            ->where('event_type', 'settlement.approved')
            ->where('subject_id', $settlementUid)
            ->first();

        $this->assertNotNull($event);
        $metadata = json_decode($event->metadata, true);
        $this->assertSame('b2b.settlements.approve', $metadata['permission']);
        $this->assertTrue($metadata['step_up']);
    }

    private function signedGet($operatorUid, $keyId, $secret, $uri, $nonce)
    {
        $headers = $this->signedB2BHeaders($operatorUid, $keyId, $secret, 'GET', $uri, '', $nonce);

        return $this->signedB2BRequest('GET', $uri, '', $headers);
    }

    private function signedPost($operatorUid, $keyId, $secret, $uri, array $payload, $nonce)
    {
        $body = json_encode($payload);
        $headers = $this->signedB2BHeaders($operatorUid, $keyId, $secret, 'POST', $uri, $body, $nonce);

        return $this->signedB2BRequest('POST', $uri, $body, $headers);
    }

    private function seedWalletTransactions()
    {
        $now = now()->subDay();

        $this->insertWalletTransaction($this->operatorA->id, 'tx_settle_a_bet', 'bet', '100.00000000', 'USD', 'success', $now);
        $this->insertWalletTransaction($this->operatorA->id, 'tx_settle_a_failed_bet', 'bet', '25.00000000', 'USD', 'failed', $now);
        $this->insertWalletTransaction($this->operatorA->id, 'tx_settle_a_win', 'win', '40.00000000', 'USD', 'success', $now);
        $this->insertWalletTransaction($this->operatorA->id, 'tx_settle_a_refund', 'refund', '5.00000000', 'USD', 'success', $now);
        $this->insertWalletTransaction($this->operatorA->id, 'tx_settle_a_rollback', 'rollback', '7.00000000', 'USD', 'success', $now);
        $this->insertWalletTransaction($this->operatorA->id, 'tx_settle_a_eur', 'bet', '999.00000000', 'EUR', 'success', $now);
        $this->insertWalletTransaction($this->operatorB->id, 'tx_settle_b_bet', 'bet', '200.00000000', 'USD', 'success', $now);
    }

    private function insertWalletTransaction($operatorId, $transactionUid, $type, $amount, $currency, $status, $createdAt)
    {
        DB::table('b2b_wallet_transactions')->insert([
            'operator_id' => $operatorId,
            'session_id' => 'sess_'.$transactionUid,
            'game_uid' => 'book_settlement',
            'round_id' => 'round_'.$transactionUid,
            'transaction_uid' => $transactionUid,
            'transaction_id' => $transactionUid,
            'idempotency_key' => sha1($operatorId.'|'.$transactionUid),
            'type' => $type,
            'amount' => $amount,
            'currency' => $currency,
            'status' => $status,
            'attempts' => 1,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
