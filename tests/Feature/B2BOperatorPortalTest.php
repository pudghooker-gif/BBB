<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\B2BApiTestHelpers;
use Tests\TestCase;

class B2BOperatorPortalTest extends TestCase
{
    use B2BApiTestHelpers;

    private $operatorA;
    private $operatorB;
    private $secretA = 'portal_secret_a_1234567890';
    private $secretB = 'portal_secret_b_1234567890';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->resetB2BTables();

        $this->operatorA = $this->createB2BOperator('op_portal_a', 'key_portal_a', $this->secretA, [
            'name' => 'Portal Operator A',
            'base_url' => 'https://operator-a.example',
            'wallet_callback_url' => 'https://wallet-a.example/callback',
            'allowed_currencies' => ['USD', 'EUR'],
        ]);
        $this->operatorB = $this->createB2BOperator('op_portal_b', 'key_portal_b', $this->secretB, [
            'name' => 'Portal Operator B',
            'base_url' => 'https://operator-b.example',
            'wallet_callback_url' => 'https://wallet-b.example/callback',
        ]);

        $this->seedPortalData();
    }

    public function testPortalOverviewBootsForSignedOperator()
    {
        $response = $this->signedGet('op_portal_a', 'key_portal_a', $this->secretA, '/api/b2b/v1/portal/overview?limit=5', 'portal-overview-a');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.operator.id', 'op_portal_a')
            ->assertJsonPath('data.operator.wallet_callback_configured', true)
            ->assertJsonPath('data.api_key.key_id', 'key_portal_a')
            ->assertJsonPath('data.summary.players', 1)
            ->assertJsonPath('data.summary.active_sessions', 1)
            ->assertJsonPath('data.summary.wallet_transactions', 2)
            ->assertJsonPath('data.summary.open_reconciliation_items', 1)
            ->assertJsonPath('data.wallet.by_status.success.count', 1)
            ->assertJsonPath('data.sessions.by_status.active.count', 1)
            ->assertJsonPath('data.credentials.by_status.active.count', 1)
            ->assertJsonPath('data.game_assignments.by_status.allowed.count', 1)
            ->assertJsonPath('data.settlements.by_status.submitted.count', 1)
            ->assertJsonPath('data.reconciliation.by_state.open.count', 1)
            ->assertJsonPath('data.callbacks.by_result.server_error.count', 1)
            ->assertJsonPath('data.callbacks.recent_logs.0.endpoint', 'https://wallet-a.example/callback')
            ->assertJsonPath('data.callbacks.recent_attempts.0.endpoint', 'https://wallet-a.example/callback')
            ->assertJsonPath('data.recent_sessions.0.session_uid', 'sess_portal_a')
            ->assertJsonPath('data.recent_transactions.0.transaction_uid', 'tx_portal_a_win');
    }

    public function testPortalOverviewIsTenantScopedAndRedacted()
    {
        $response = $this->signedGet('op_portal_a', 'key_portal_a', $this->secretA, '/api/b2b/v1/portal/overview?limit=10', 'portal-redacted-a');

        $content = $response->getContent();

        $this->assertStringContainsString('tx_portal_a_bet', $content);
        $this->assertStringNotContainsString('tx_portal_b_bet', $content);
        $this->assertStringNotContainsString('sess_portal_b', $content);
        $this->assertStringNotContainsString('secret_encrypted', $content);
        $this->assertStringNotContainsString('raw_request', $content);
        $this->assertStringNotContainsString('raw_response', $content);
        $this->assertStringNotContainsString('super-secret-value', $content);
        $this->assertStringNotContainsString('callback-secret-value', $content);
        $this->assertStringNotContainsString('attempt-secret-value', $content);
        $this->assertStringNotContainsString('wallet-b.example', $content);
    }

    public function testPortalOverviewRequiresSignature()
    {
        $this->get('/api/b2b/v1/portal/overview')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'B2B_AUTH_FAILED');
    }

    public function testSignedOperatorPortalPageIsTenantScopedAndRedacted()
    {
        $response = $this->signedGet('op_portal_a', 'key_portal_a', $this->secretA, '/api/b2b/v1/portal?limit=10', 'portal-page-a');

        $response->assertStatus(200);

        $content = $response->getContent();

        $this->assertStringContainsString('<html', $content);
        $this->assertStringContainsString('Portal Operator A', $content);
        $this->assertStringContainsString('tx_portal_a_bet', $content);
        $this->assertStringContainsString('settlement_portal_a', $content);
        $this->assertStringNotContainsString('Portal Operator B', $content);
        $this->assertStringNotContainsString('tx_portal_b_bet', $content);
        $this->assertStringNotContainsString('sess_portal_b', $content);
        $this->assertStringNotContainsString('secret_encrypted', $content);
        $this->assertStringNotContainsString('super-secret-value', $content);
    }

    public function testSignedOperatorPortalWorkflowPagesAreTenantScopedAndRedacted()
    {
        foreach ([
            'credentials' => 'key_portal_a',
            'games' => 'book_portal_a',
            'sessions' => 'sess_portal_a',
            'transactions' => 'tx_portal_a_bet',
            'settlements' => 'settlement_portal_a',
            'cases' => 'tx_portal_a_win',
            'callbacks' => 'server_error',
            'reports' => '/api/b2b/v1/reports/ggr',
            'docs' => '/api/b2b/v1/reports/transactions',
        ] as $section => $expected) {
            $response = $this->signedGet('op_portal_a', 'key_portal_a', $this->secretA, '/api/b2b/v1/portal/' . $section . '?limit=10', 'portal-section-' . $section);
            $response->assertStatus(200);

            $content = $response->getContent();
            $this->assertStringContainsString('Portal Operator A', $content);
            $this->assertStringContainsString($expected, $content);
            $this->assertStringNotContainsString('Portal Operator B', $content);
            $this->assertStringNotContainsString('tx_portal_b_bet', $content);
            $this->assertStringNotContainsString('sess_portal_b', $content);
            $this->assertStringNotContainsString('secret_encrypted', $content);
            $this->assertStringNotContainsString('raw_request', $content);
            $this->assertStringNotContainsString('raw_response', $content);
            $this->assertStringNotContainsString('super-secret-value', $content);
            $this->assertStringNotContainsString('callback-secret-value', $content);
            $this->assertStringNotContainsString('attempt-secret-value', $content);
            $this->assertStringNotContainsString('wallet-b.example', $content);
        }
    }

    public function testOperatorPortalWorkflowPagesRequireSignature()
    {
        $this->get('/api/b2b/v1/portal/transactions')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'B2B_AUTH_FAILED');
    }

    private function signedGet($operatorUid, $keyId, $secret, $uri, $nonce)
    {
        $headers = $this->signedB2BHeaders($operatorUid, $keyId, $secret, 'GET', $uri, '', $nonce);

        return $this->signedB2BRequest('GET', $uri, '', $headers);
    }

    private function seedPortalData()
    {
        $now = now();
        $playerA = DB::table('b2b_operator_players')->insertGetId([
            'operator_id' => $this->operatorA->id,
            'external_player_id' => 'player_portal_a',
            'currency' => 'USD',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $playerB = DB::table('b2b_operator_players')->insertGetId([
            'operator_id' => $this->operatorB->id,
            'external_player_id' => 'player_portal_b',
            'currency' => 'USD',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('b2b_game_sessions')->insert([
            [
                'operator_id' => $this->operatorA->id,
                'operator_player_id' => $playerA,
                'session_uid' => 'sess_portal_a',
                'game_uid' => 'book_portal_a',
                'provider' => 'goldsvet_internal',
                'mode' => 'real',
                'currency' => 'USD',
                'status' => 'active',
                'expires_at' => $now->copy()->addHour(),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'operator_id' => $this->operatorB->id,
                'operator_player_id' => $playerB,
                'session_uid' => 'sess_portal_b',
                'game_uid' => 'book_portal_b',
                'provider' => 'goldsvet_internal',
                'mode' => 'real',
                'currency' => 'USD',
                'status' => 'active',
                'expires_at' => $now->copy()->addHour(),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $walletA = [
            [
                'operator_id' => $this->operatorA->id,
                'operator_player_id' => $playerA,
                'session_id' => 'sess_portal_a',
                'game_uid' => 'book_portal_a',
                'round_id' => 'round_portal_a',
                'transaction_uid' => 'tx_portal_a_bet',
                'transaction_id' => 'operator_tx_portal_a_bet',
                'type' => 'bet',
                'amount' => '10.00000000',
                'currency' => 'USD',
                'status' => 'success',
                'raw_request' => json_encode(['token' => 'super-secret-value']),
                'raw_response' => json_encode(['secret' => 'super-secret-value']),
                'attempts' => 1,
                'created_at' => $now->copy()->subMinute(),
                'updated_at' => $now->copy()->subMinute(),
            ],
            [
                'operator_id' => $this->operatorA->id,
                'operator_player_id' => $playerA,
                'session_id' => 'sess_portal_a',
                'game_uid' => 'book_portal_a',
                'round_id' => 'round_portal_a',
                'transaction_uid' => 'tx_portal_a_win',
                'transaction_id' => 'operator_tx_portal_a_win',
                'type' => 'win',
                'amount' => '4.00000000',
                'currency' => 'USD',
                'status' => 'pending',
                'raw_request' => null,
                'raw_response' => null,
                'attempts' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('b2b_wallet_transactions')->insert(array_merge($walletA, [[
            'operator_id' => $this->operatorB->id,
            'operator_player_id' => $playerB,
            'session_id' => 'sess_portal_b',
            'game_uid' => 'book_portal_b',
            'round_id' => 'round_portal_b',
            'transaction_uid' => 'tx_portal_b_bet',
            'transaction_id' => 'operator_tx_portal_b_bet',
            'type' => 'bet',
            'amount' => '99.00000000',
            'currency' => 'USD',
            'status' => 'success',
            'raw_request' => null,
            'raw_response' => null,
            'attempts' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]]));

        $walletTransactionA = DB::table('b2b_wallet_transactions')
            ->where('operator_id', $this->operatorA->id)
            ->where('transaction_uid', 'tx_portal_a_bet')
            ->first();
        $walletTransactionB = DB::table('b2b_wallet_transactions')
            ->where('operator_id', $this->operatorB->id)
            ->where('transaction_uid', 'tx_portal_b_bet')
            ->first();

        DB::table('b2b_wallet_callback_logs')->insert([
            [
                'operator_id' => $this->operatorA->id,
                'wallet_transaction_id' => $walletTransactionA->id,
                'direction' => 'outbound',
                'endpoint' => 'https://wallet-a.example/callback?token=callback-secret-value',
                'http_status' => 500,
                'request_body' => json_encode(['access_token' => 'callback-secret-value']),
                'response_body' => json_encode(['signature' => 'callback-secret-value']),
                'duration_ms' => 850,
                'error_message' => 'callback failed token=callback-secret-value',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'operator_id' => $this->operatorB->id,
                'wallet_transaction_id' => $walletTransactionB->id,
                'direction' => 'outbound',
                'endpoint' => 'https://wallet-b.example/callback?token=callback-secret-value-b',
                'http_status' => 200,
                'request_body' => null,
                'response_body' => null,
                'duration_ms' => 90,
                'error_message' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('b2b_wallet_transaction_attempts')->insert([
            [
                'wallet_transaction_id' => $walletTransactionA->id,
                'operator_id' => $this->operatorA->id,
                'transaction_uid' => 'tx_portal_a_bet',
                'type' => 'bet',
                'attempt_no' => 1,
                'url' => 'https://wallet-a.example/callback?signature=attempt-secret-value',
                'timeout_ms' => 5000,
                'http_status' => 504,
                'result' => 'timeout',
                'duration_ms' => 5000,
                'request_body' => json_encode(['access_token' => 'attempt-secret-value']),
                'response_body' => json_encode(['api_key' => 'attempt-secret-value']),
                'error' => 'timeout signature=attempt-secret-value',
                'started_at' => $now->copy()->subSeconds(5),
                'finished_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'wallet_transaction_id' => $walletTransactionB->id,
                'operator_id' => $this->operatorB->id,
                'transaction_uid' => 'tx_portal_b_bet',
                'type' => 'bet',
                'attempt_no' => 1,
                'url' => 'https://wallet-b.example/callback?signature=attempt-secret-value-b',
                'timeout_ms' => 5000,
                'http_status' => 200,
                'result' => 'success',
                'duration_ms' => 90,
                'request_body' => null,
                'response_body' => null,
                'error' => null,
                'started_at' => $now->copy()->subSeconds(1),
                'finished_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('b2b_operator_game_assignments')->insert([
            'operator_id' => $this->operatorA->id,
            'game_uid' => 'book_portal_a',
            'provider' => 'goldsvet_internal',
            'status' => 'allowed',
            'demo_enabled' => true,
            'real_enabled' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('b2b_settlements')->insert([
            'operator_id' => $this->operatorA->id,
            'settlement_uid' => 'settlement_portal_a',
            'period_start' => $now->copy()->subDay(),
            'period_end' => $now,
            'currency' => 'USD',
            'ggr_amount' => '6.00000000',
            'net_amount' => '6.00000000',
            'status' => 'submitted',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('b2b_wallet_reconciliation_items')->insert([
            'operator_id' => $this->operatorA->id,
            'wallet_transaction_id' => null,
            'transaction_uid' => 'tx_portal_a_win',
            'status' => 'pending',
            'reason' => 'provider_pending',
            'priority' => 'high',
            'state' => 'open',
            'detected_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
