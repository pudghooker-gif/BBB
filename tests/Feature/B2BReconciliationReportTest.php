<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\B2BApiTestHelpers;
use Tests\TestCase;

class B2BReconciliationReportTest extends TestCase
{
    use B2BApiTestHelpers;

    private $operatorA;
    private $operatorB;
    private $secretA = 'reconcile_report_secret_a_1234567890';
    private $secretB = 'reconcile_report_secret_b_1234567890';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->resetB2BTables();
        $this->operatorA = $this->createB2BOperator('op_recon_report_a', 'key_recon_report_a', $this->secretA);
        $this->operatorB = $this->createB2BOperator('op_recon_report_b', 'key_recon_report_b', $this->secretB);
        $this->seedReconciliationData();
    }

    public function testReconciliationReportAggregatesOpenExposureWithoutDoubleCountingTransactions()
    {
        $response = $this->signedGet(
            'op_recon_report_a',
            'key_recon_report_a',
            $this->secretA,
            '/api/b2b/v1/reports/reconciliation?from='.now()->subDays(2)->toDateString().'&to='.now()->addDay()->toDateString().'&limit=10',
            'reconciliation-report-a'
        );

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.totals.items', 4)
            ->assertJsonPath('data.totals.open', 2)
            ->assertJsonPath('data.totals.in_progress', 1)
            ->assertJsonPath('data.totals.resolved', 1)
            ->assertJsonPath('data.totals.high_priority_open', 1)
            ->assertJsonPath('data.totals.unresolved_items', 3)
            ->assertJsonPath('data.totals.unresolved_transactions', 2)
            ->assertJsonPath('data.by_reason.rollback_required.count', 1)
            ->assertJsonPath('data.by_reason.manual_review.count', 1)
            ->assertJsonPath('data.by_reason.unknown_result.count', 2)
            ->assertJsonPath('data.open_exposure.USD.amount', '15.00000000')
            ->assertJsonPath('data.open_exposure.USD.transactions', 2)
            ->assertJsonPath('data.aging_buckets.lt_1h.count', 1)
            ->assertJsonPath('data.aging_buckets.1h_24h.count', 2)
            ->assertJsonCount(3, 'data.oldest_open_items');

        $json = json_encode($response->json());
        $this->assertStringContainsString('tx_recon_a_rollback', $json);
        $this->assertStringContainsString('tx_recon_a_unknown', $json);
        $this->assertStringNotContainsString('tx_recon_b_rollback', $json);
    }

    public function testReconciliationReportIsTenantScopedAndSupportsCurrencyFilter()
    {
        $operatorAResponse = $this->signedGet(
            'op_recon_report_a',
            'key_recon_report_a',
            $this->secretA,
            '/api/b2b/v1/reports/reconciliation?currency=EUR',
            'reconciliation-report-a-eur'
        );

        $operatorAResponse->assertStatus(200)
            ->assertJsonPath('data.totals.items', 0);

        $this->assertNull($operatorAResponse->json('data.open_exposure.USD'));

        $operatorBResponse = $this->signedGet(
            'op_recon_report_b',
            'key_recon_report_b',
            $this->secretB,
            '/api/b2b/v1/reports/reconciliation?from='.now()->subDays(2)->toDateString().'&to='.now()->addDay()->toDateString(),
            'reconciliation-report-b'
        );

        $operatorBResponse->assertStatus(200)
            ->assertJsonPath('data.totals.items', 1)
            ->assertJsonPath('data.totals.unresolved_transactions', 1)
            ->assertJsonPath('data.open_exposure.EUR.amount', '99.00000000')
            ->assertJsonPath('data.open_exposure.EUR.transactions', 1);

        $this->assertStringNotContainsString('tx_recon_a_rollback', json_encode($operatorBResponse->json()));
    }

    public function testReconciliationReportSupportsTransactionDimensionFilters()
    {
        $uri = '/api/b2b/v1/reports/reconciliation'
            . '?from='.now()->subDays(2)->toDateString()
            . '&to='.now()->addDay()->toDateString()
            . '&state=in_progress'
            . '&reason=unknown_result'
            . '&priority=medium'
            . '&game_id=book_recon'
            . '&round_id=round_tx_recon_a_unknown'
            . '&limit=1';

        $response = $this->signedGet(
            'op_recon_report_a',
            'key_recon_report_a',
            $this->secretA,
            $uri,
            'reconciliation-report-dimension-filters'
        );

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.totals.items', 1)
            ->assertJsonPath('data.totals.open', 0)
            ->assertJsonPath('data.totals.in_progress', 1)
            ->assertJsonPath('data.totals.unresolved_items', 1)
            ->assertJsonPath('data.totals.unresolved_transactions', 1)
            ->assertJsonPath('data.by_state.in_progress.count', 1)
            ->assertJsonPath('data.by_reason.unknown_result.count', 1)
            ->assertJsonPath('data.by_priority.medium.count', 1)
            ->assertJsonPath('data.open_exposure.USD.amount', '5.00000000')
            ->assertJsonPath('data.open_exposure.USD.transactions', 1)
            ->assertJsonCount(1, 'data.oldest_open_items')
            ->assertJsonPath('data.oldest_open_items.0.transaction_uid', 'tx_recon_a_unknown')
            ->assertJsonPath('data.oldest_open_items.0.round_id', 'round_tx_recon_a_unknown');

        $this->assertStringNotContainsString('tx_recon_a_rollback', json_encode($response->json()));
    }

    public function testReconciliationReportValidatesFiltersAndPeriods()
    {
        $this->signedGet(
            'op_recon_report_a',
            'key_recon_report_a',
            $this->secretA,
            '/api/b2b/v1/reports/reconciliation?limit=101',
            'reconciliation-invalid-limit'
        )
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->signedGet(
            'op_recon_report_a',
            'key_recon_report_a',
            $this->secretA,
            '/api/b2b/v1/reports/reconciliation?state=closed',
            'reconciliation-invalid-state'
        )
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->signedGet(
            'op_recon_report_a',
            'key_recon_report_a',
            $this->secretA,
            '/api/b2b/v1/reports/reconciliation?priority=critical',
            'reconciliation-invalid-priority'
        )
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $badPeriod = '/api/b2b/v1/reports/reconciliation?from=' . now()->addDay()->toDateString() . '&to=' . now()->subDay()->toDateString();
        $this->signedGet(
            'op_recon_report_a',
            'key_recon_report_a',
            $this->secretA,
            $badPeriod,
            'reconciliation-invalid-period'
        )
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    private function signedGet($operatorUid, $keyId, $secret, $uri, $nonce)
    {
        $headers = $this->signedB2BHeaders($operatorUid, $keyId, $secret, 'GET', $uri, '', $nonce);

        return $this->signedB2BRequest('GET', $uri, '', $headers);
    }

    private function seedReconciliationData()
    {
        $now = now();
        $aRollback = $this->insertTransaction($this->operatorA->id, 'tx_recon_a_rollback', 'rollback_required', '10.00000000', 'USD');
        $aUnknown = $this->insertTransaction($this->operatorA->id, 'tx_recon_a_unknown', 'unknown', '5.00000000', 'USD');
        $bRollback = $this->insertTransaction($this->operatorB->id, 'tx_recon_b_rollback', 'rollback_required', '99.00000000', 'EUR');

        DB::table('b2b_wallet_reconciliation_items')->insert([
            [
                'wallet_transaction_id' => $aRollback,
                'operator_id' => $this->operatorA->id,
                'transaction_uid' => 'tx_recon_a_rollback',
                'status' => 'rollback_required',
                'reason' => 'rollback_required',
                'priority' => 'high',
                'state' => 'open',
                'detected_at' => $now->copy()->subHours(3),
                'resolved_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'wallet_transaction_id' => $aRollback,
                'operator_id' => $this->operatorA->id,
                'transaction_uid' => 'tx_recon_a_rollback',
                'status' => 'manual_review',
                'reason' => 'manual_review',
                'priority' => 'medium',
                'state' => 'open',
                'detected_at' => $now->copy()->subHours(2),
                'resolved_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'wallet_transaction_id' => $aUnknown,
                'operator_id' => $this->operatorA->id,
                'transaction_uid' => 'tx_recon_a_unknown',
                'status' => 'unknown',
                'reason' => 'unknown_result',
                'priority' => 'medium',
                'state' => 'in_progress',
                'detected_at' => $now->copy()->subMinutes(20),
                'resolved_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'wallet_transaction_id' => $aUnknown,
                'operator_id' => $this->operatorA->id,
                'transaction_uid' => 'tx_recon_a_unknown',
                'status' => 'success',
                'reason' => 'unknown_result',
                'priority' => 'medium',
                'state' => 'resolved',
                'detected_at' => $now->copy()->subMinutes(10),
                'resolved_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'wallet_transaction_id' => $bRollback,
                'operator_id' => $this->operatorB->id,
                'transaction_uid' => 'tx_recon_b_rollback',
                'status' => 'rollback_required',
                'reason' => 'rollback_required',
                'priority' => 'high',
                'state' => 'open',
                'detected_at' => $now->copy()->subHours(4),
                'resolved_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    private function insertTransaction($operatorId, $transactionUid, $status, $amount, $currency)
    {
        return DB::table('b2b_wallet_transactions')->insertGetId([
            'operator_id' => $operatorId,
            'session_id' => 'sess_'.$transactionUid,
            'game_uid' => 'book_recon',
            'round_id' => 'round_'.$transactionUid,
            'transaction_uid' => $transactionUid,
            'transaction_id' => $transactionUid,
            'idempotency_key' => sha1($operatorId.'|'.$transactionUid),
            'type' => 'bet',
            'amount' => $amount,
            'currency' => $currency,
            'status' => $status,
            'attempts' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
