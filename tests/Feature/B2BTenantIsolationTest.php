<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\B2BApiTestHelpers;
use Tests\TestCase;

class B2BTenantIsolationTest extends TestCase
{
    use B2BApiTestHelpers;

    private $operatorA;
    private $operatorB;
    private $secretA = 'tenant_secret_a_1234567890';
    private $secretB = 'tenant_secret_b_1234567890';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->resetB2BTables();
        $this->operatorA = $this->createB2BOperator('op_a', 'key_a', $this->secretA);
        $this->operatorB = $this->createB2BOperator('op_b', 'key_b', $this->secretB);
        $this->seedTenantData();
    }

    public function testSessionListAndDetailAreScopedToSignedOperator()
    {
        $response = $this->signedGet('op_a', 'key_a', $this->secretA, '/api/b2b/v1/sessions', 'tenant-sessions-list');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.session_uid', 'sess_a');

        $this->signedGet('op_a', 'key_a', $this->secretA, '/api/b2b/v1/sessions/sess_b', 'tenant-sessions-detail')
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'SESSION_NOT_FOUND');

        $longSessionUid = str_repeat('x', 192);
        $this->signedGet('op_a', 'key_a', $this->secretA, '/api/b2b/v1/sessions/' . $longSessionUid, 'tenant-sessions-invalid-uid')
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function testSessionListSupportsBoundedFiltersAndSorting()
    {
        $this->createB2BSession($this->operatorA, 'player_filter', 'sess_filter_z', 'book_z', [
            'status' => 'closed',
            'created_at' => now()->subMinutes(2),
            'updated_at' => now()->subMinutes(2),
        ]);
        $this->createB2BSession($this->operatorA, 'player_filter', 'sess_filter_a', 'book_a', [
            'status' => 'closed',
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);
        $this->createB2BSession($this->operatorB, 'player_filter', 'sess_filter_foreign', 'book_a', [
            'status' => 'closed',
        ]);

        $this->signedGet(
            'op_a',
            'key_a',
            $this->secretA,
            '/api/b2b/v1/sessions?status=closed&player_id=player_filter&sort=game_id&limit=1',
            'tenant-sessions-filter-sort'
        )
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.session_uid', 'sess_filter_a')
            ->assertJsonPath('data.0.game_uid', 'book_a')
            ->assertJsonPath('meta.limit', 1)
            ->assertJsonPath('meta.count', 1)
            ->assertJsonPath('meta.matched_count', 2)
            ->assertJsonPath('meta.sort', 'game_id')
            ->assertJsonPath('meta.filters.status', 'closed')
            ->assertJsonPath('meta.filters.player_id', 'player_filter');

        $this->signedGet(
            'op_a',
            'key_a',
            $this->secretA,
            '/api/b2b/v1/sessions?status=unknown',
            'tenant-sessions-invalid-status'
        )
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->signedGet(
            'op_a',
            'key_a',
            $this->secretA,
            '/api/b2b/v1/sessions?sort=operator_id',
            'tenant-sessions-invalid-sort'
        )
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function testReportsTransactionsAndSettlementsAreScopedToSignedOperator()
    {
        $transactions = $this->signedGet('op_a', 'key_a', $this->secretA, '/api/b2b/v1/reports/transactions', 'tenant-report-transactions');

        $transactions->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.transaction_uid', 'tx_a');

        $this->signedGet('op_a', 'key_a', $this->secretA, '/api/b2b/v1/reports/transactions/tx_b', 'tenant-report-detail')
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'TRANSACTION_NOT_FOUND');

        $longTransactionUid = str_repeat('x', 192);
        $this->signedGet('op_a', 'key_a', $this->secretA, '/api/b2b/v1/reports/transactions/' . $longTransactionUid, 'tenant-report-invalid-detail-uid')
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $settlements = $this->signedGet('op_a', 'key_a', $this->secretA, '/api/b2b/v1/reports/settlements', 'tenant-settlements');

        $settlements->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(1, 'data');

        $this->assertEquals((string) $this->operatorA->id, (string) $settlements->json('data.0.operator_id'));
    }

    public function testTransactionReportSupportsBoundedFiltersSortingAndValidation()
    {
        $now = now();
        $playerA = DB::table('b2b_operator_players')->insertGetId([
            'operator_id' => $this->operatorA->id,
            'external_player_id' => 'report_player',
            'currency' => 'USD',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $playerB = DB::table('b2b_operator_players')->insertGetId([
            'operator_id' => $this->operatorB->id,
            'external_player_id' => 'report_player',
            'currency' => 'USD',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('b2b_wallet_transactions')->insert([
            [
                'operator_id' => $this->operatorA->id,
                'operator_player_id' => $playerA,
                'session_id' => 'sess_report_a_low',
                'transaction_uid' => 'tx_report_low',
                'transaction_id' => 'tx_report_low',
                'idempotency_key' => 'idem_report_low',
                'type' => 'bet',
                'game_uid' => 'book_report',
                'round_id' => 'round_report',
                'amount' => '2.00000000',
                'currency' => 'USD',
                'status' => 'success',
                'created_at' => $now->copy()->subMinutes(2),
                'updated_at' => $now->copy()->subMinutes(2),
            ],
            [
                'operator_id' => $this->operatorA->id,
                'operator_player_id' => $playerA,
                'session_id' => 'sess_report_a_high',
                'transaction_uid' => 'tx_report_high',
                'transaction_id' => 'tx_report_high',
                'idempotency_key' => 'idem_report_high',
                'type' => 'bet',
                'game_uid' => 'book_report',
                'round_id' => 'round_report',
                'amount' => '9.00000000',
                'currency' => 'USD',
                'status' => 'success',
                'created_at' => $now->copy()->subMinute(),
                'updated_at' => $now->copy()->subMinute(),
            ],
            [
                'operator_id' => $this->operatorB->id,
                'operator_player_id' => $playerB,
                'session_id' => 'sess_report_foreign',
                'transaction_uid' => 'tx_report_foreign',
                'transaction_id' => 'tx_report_foreign',
                'idempotency_key' => 'idem_report_foreign',
                'type' => 'bet',
                'game_uid' => 'book_report',
                'round_id' => 'round_report',
                'amount' => '1.00000000',
                'currency' => 'USD',
                'status' => 'success',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $from = now()->subDay()->toDateString();
        $to = now()->addDay()->toDateString();
        $uri = '/api/b2b/v1/reports/transactions?from=' . $from . '&to=' . $to . '&status=success&type=bet&player_id=report_player&game_id=book_report&round_id=round_report&currency=USD&sort=amount&limit=1';

        $this->signedGet('op_a', 'key_a', $this->secretA, $uri, 'tenant-report-filter-sort')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.transaction_uid', 'tx_report_low')
            ->assertJsonPath('meta.limit', 1)
            ->assertJsonPath('meta.count', 1)
            ->assertJsonPath('meta.matched_count', 2)
            ->assertJsonPath('meta.sort', 'amount')
            ->assertJsonPath('meta.filters.status', 'success')
            ->assertJsonPath('meta.filters.type', 'bet')
            ->assertJsonPath('meta.filters.currency', 'USD')
            ->assertJsonPath('meta.filters.game_id', 'book_report');

        $this->signedGet('op_a', 'key_a', $this->secretA, '/api/b2b/v1/reports/transactions?status=void', 'tenant-report-invalid-status')
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->signedGet('op_a', 'key_a', $this->secretA, '/api/b2b/v1/reports/transactions?sort=operator_id', 'tenant-report-invalid-sort')
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $badPeriod = '/api/b2b/v1/reports/transactions?from=' . now()->addDay()->toDateString() . '&to=' . now()->subDay()->toDateString();
        $this->signedGet('op_a', 'key_a', $this->secretA, $badPeriod, 'tenant-report-invalid-period')
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function testSettlementReportSupportsBoundedFiltersSortingAndValidation()
    {
        $now = now();

        DB::table('b2b_settlements')->insert([
            [
                'settlement_uid' => 'stl_report_low',
                'operator_id' => $this->operatorA->id,
                'period_start' => $now->copy()->subDay(),
                'period_end' => $now,
                'currency' => 'USD',
                'ggr_amount' => '3.00000000',
                'net_amount' => '3.00000000',
                'status' => 'approved',
                'created_at' => $now->copy()->subMinutes(2),
                'updated_at' => $now->copy()->subMinutes(2),
            ],
            [
                'settlement_uid' => 'stl_report_high',
                'operator_id' => $this->operatorA->id,
                'period_start' => $now->copy()->subDay(),
                'period_end' => $now,
                'currency' => 'USD',
                'ggr_amount' => '8.00000000',
                'net_amount' => '8.00000000',
                'status' => 'approved',
                'created_at' => $now->copy()->subMinute(),
                'updated_at' => $now->copy()->subMinute(),
            ],
            [
                'settlement_uid' => 'stl_report_foreign',
                'operator_id' => $this->operatorB->id,
                'period_start' => $now->copy()->subDay(),
                'period_end' => $now,
                'currency' => 'USD',
                'ggr_amount' => '1.00000000',
                'net_amount' => '1.00000000',
                'status' => 'approved',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $from = now()->subDay()->toDateString();
        $to = now()->addDay()->toDateString();
        $uri = '/api/b2b/v1/reports/settlements?from=' . $from . '&to=' . $to . '&status=approved&currency=USD&sort=net_amount&limit=1';

        $this->signedGet('op_a', 'key_a', $this->secretA, $uri, 'tenant-settlement-filter-sort')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.settlement_uid', 'stl_report_low')
            ->assertJsonPath('meta.limit', 1)
            ->assertJsonPath('meta.count', 1)
            ->assertJsonPath('meta.matched_count', 2)
            ->assertJsonPath('meta.sort', 'net_amount')
            ->assertJsonPath('meta.filters.status', 'approved')
            ->assertJsonPath('meta.filters.currency', 'USD');

        $this->signedGet('op_a', 'key_a', $this->secretA, '/api/b2b/v1/reports/settlements?status=void', 'tenant-settlement-invalid-status')
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->signedGet('op_a', 'key_a', $this->secretA, '/api/b2b/v1/reports/settlements?sort=operator_id', 'tenant-settlement-invalid-sort')
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $badPeriod = '/api/b2b/v1/reports/settlements?from=' . now()->addDay()->toDateString() . '&to=' . now()->subDay()->toDateString();
        $this->signedGet('op_a', 'key_a', $this->secretA, $badPeriod, 'tenant-settlement-invalid-period')
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function testAggregateReportsValidateFiltersAndPeriods()
    {
        $this->signedGet('op_a', 'key_a', $this->secretA, '/api/b2b/v1/reports/summary?status=void', 'tenant-summary-invalid-status')
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->signedGet('op_a', 'key_a', $this->secretA, '/api/b2b/v1/reports/summary?currency=US', 'tenant-summary-invalid-currency')
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $badPeriod = '/api/b2b/v1/reports/ggr?from=' . now()->addDay()->toDateString() . '&to=' . now()->subDay()->toDateString();
        $this->signedGet('op_a', 'key_a', $this->secretA, $badPeriod, 'tenant-ggr-invalid-period')
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function testWalletAttemptsAreScopedToSignedOperator()
    {
        $response = $this->signedGet(
            'op_a',
            'key_a',
            $this->secretA,
            '/api/b2b/v1/wallet/transactions/shared_tx/attempts?limit=1',
            'tenant-attempts'
        );

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.result', 'success')
            ->assertJsonPath('meta.transaction_uid', 'shared_tx')
            ->assertJsonPath('meta.limit', 1)
            ->assertJsonPath('meta.count', 1);

        $this->assertEquals((string) $this->operatorA->id, (string) $response->json('data.0.operator_id'));

        $this->signedGet(
            'op_a',
            'key_a',
            $this->secretA,
            '/api/b2b/v1/wallet/transactions/shared_tx/attempts?limit=101',
            'tenant-attempts-invalid-limit'
        )
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $longTransactionUid = str_repeat('x', 192);
        $this->signedGet(
            'op_a',
            'key_a',
            $this->secretA,
            '/api/b2b/v1/wallet/transactions/' . $longTransactionUid . '/attempts',
            'tenant-attempts-invalid-uid'
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

    private function seedTenantData()
    {
        $now = now();

        $playerA = DB::table('b2b_operator_players')->insertGetId([
            'operator_id' => $this->operatorA->id,
            'external_player_id' => 'player_a',
            'currency' => 'USD',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $playerB = DB::table('b2b_operator_players')->insertGetId([
            'operator_id' => $this->operatorB->id,
            'external_player_id' => 'player_b',
            'currency' => 'USD',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('b2b_game_sessions')->insert([
            [
                'operator_id' => $this->operatorA->id,
                'operator_player_id' => $playerA,
                'session_uid' => 'sess_a',
                'game_uid' => 'book_of_a',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'operator_id' => $this->operatorB->id,
                'operator_player_id' => $playerB,
                'session_uid' => 'sess_b',
                'game_uid' => 'book_of_b',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $transactionA = DB::table('b2b_wallet_transactions')->insertGetId([
            'operator_id' => $this->operatorA->id,
            'operator_player_id' => $playerA,
            'session_id' => 'sess_a',
            'transaction_uid' => 'tx_a',
            'transaction_id' => 'operator_tx_a',
            'idempotency_key' => 'idem_a',
            'type' => 'bet',
            'amount' => '10.00000000',
            'currency' => 'USD',
            'status' => 'success',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $transactionB = DB::table('b2b_wallet_transactions')->insertGetId([
            'operator_id' => $this->operatorB->id,
            'operator_player_id' => $playerB,
            'session_id' => 'sess_b',
            'transaction_uid' => 'tx_b',
            'transaction_id' => 'operator_tx_b',
            'idempotency_key' => 'idem_b',
            'type' => 'bet',
            'amount' => '20.00000000',
            'currency' => 'USD',
            'status' => 'success',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('b2b_wallet_transaction_attempts')->insert([
            [
                'wallet_transaction_id' => $transactionA,
                'operator_id' => $this->operatorA->id,
                'transaction_uid' => 'shared_tx',
                'type' => 'bet',
                'attempt_no' => 1,
                'result' => 'success',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'wallet_transaction_id' => $transactionB,
                'operator_id' => $this->operatorB->id,
                'transaction_uid' => 'shared_tx',
                'type' => 'bet',
                'attempt_no' => 1,
                'result' => 'failed',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('b2b_settlements')->insert([
            [
                'operator_id' => $this->operatorA->id,
                'period_start' => $now,
                'period_end' => $now,
                'currency' => 'USD',
                'ggr_amount' => '10.00000000',
                'status' => 'draft',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'operator_id' => $this->operatorB->id,
                'period_start' => $now,
                'period_end' => $now,
                'currency' => 'USD',
                'ggr_amount' => '20.00000000',
                'status' => 'draft',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
