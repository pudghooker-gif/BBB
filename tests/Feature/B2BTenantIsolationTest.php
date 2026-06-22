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

        $settlements = $this->signedGet('op_a', 'key_a', $this->secretA, '/api/b2b/v1/reports/settlements', 'tenant-settlements');

        $settlements->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(1, 'data');

        $this->assertEquals((string) $this->operatorA->id, (string) $settlements->json('data.0.operator_id'));
    }

    public function testWalletAttemptsAreScopedToSignedOperator()
    {
        $response = $this->signedGet(
            'op_a',
            'key_a',
            $this->secretA,
            '/api/b2b/v1/wallet/transactions/shared_tx/attempts',
            'tenant-attempts'
        );

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.result', 'success');

        $this->assertEquals((string) $this->operatorA->id, (string) $response->json('data.0.operator_id'));
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
