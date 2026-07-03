<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\B2BApiTestHelpers;
use Tests\TestCase;

class B2BWalletStatusLookupTest extends TestCase
{
    use B2BApiTestHelpers;

    private $operatorA;
    private $operatorB;
    private $secretA = 'status_secret_a_1234567890';
    private $secretB = 'status_secret_b_1234567890';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->resetB2BTables();
        $this->operatorA = $this->createB2BOperator('op_status_a', 'key_status_a', $this->secretA);
        $this->operatorB = $this->createB2BOperator('op_status_b', 'key_status_b', $this->secretB);
        $this->seedStatusLookupData();
    }

    public function testWalletStatusLookupIsScopedAndIncludesOperationalHistory()
    {
        $response = $this->signedGet(
            'op_status_a',
            'key_status_a',
            $this->secretA,
            '/api/b2b/v1/wallet/transactions/shared_status/status',
            'status-lookup-a'
        );

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.transaction.transaction_uid', 'shared_status')
            ->assertJsonPath('data.transaction.status', 'unknown')
            ->assertJsonPath('data.transitions.0.to_status', 'unknown')
            ->assertJsonPath('data.attempts.0.result', 'timeout')
            ->assertJsonPath('data.reconciliation_items.0.reason', 'unknown_result')
            ->assertJsonPath('data.next_actions.0', 'retry_wallet');

        $this->assertEquals((string) $this->operatorA->id, (string) $response->json('data.transaction.operator_id'));
        $this->assertEquals((string) $this->operatorA->id, (string) $response->json('data.transitions.0.operator_id'));
        $this->assertEquals((string) $this->operatorA->id, (string) $response->json('data.attempts.0.operator_id'));
        $this->assertEquals((string) $this->operatorA->id, (string) $response->json('data.reconciliation_items.0.operator_id'));
        $this->assertSame('[REDACTED]', $response->json('data.transitions.0.context.signature'));
        $this->assertSame('[REDACTED]', $response->json('data.attempts.0.request_body.access_token'));
        $this->assertSame('[REDACTED]', $response->json('data.attempts.0.response_body.api_key'));
        $this->assertSame('[REDACTED]', $response->json('data.reconciliation_items.0.context.token'));
        $this->assertStringNotContainsString('legacy-status-secret', json_encode($response->json()));
    }

    public function testWalletStatusLookupDoesNotExposeAnotherOperatorsTransaction()
    {
        $this->signedGet(
            'op_status_a',
            'key_status_a',
            $this->secretA,
            '/api/b2b/v1/wallet/transactions/only_status_b/status',
            'status-lookup-foreign'
        )
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'TRANSACTION_NOT_FOUND');
    }

    public function testWalletStatusLookupValidatesTransactionUid()
    {
        $longTransactionUid = str_repeat('x', 192);

        $this->signedGet(
            'op_status_a',
            'key_status_a',
            $this->secretA,
            '/api/b2b/v1/wallet/transactions/' . $longTransactionUid . '/status',
            'status-lookup-invalid-uid'
        )
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function testReportTransactionDetailRedactsLegacyRawPayloads()
    {
        $response = $this->signedGet(
            'op_status_a',
            'key_status_a',
            $this->secretA,
            '/api/b2b/v1/reports/transactions/shared_status',
            'report-status-redaction'
        );

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.transaction.transaction_uid', 'shared_status')
            ->assertJsonPath('data.attempts.0.request_body.access_token', '[REDACTED]');

        $json = json_encode($response->json());
        $this->assertStringNotContainsString('legacy-status-secret', $json);
        $this->assertStringNotContainsString('legacy-status-password', $json);
        $this->assertStringNotContainsString('legacy-response-secret', $json);
    }

    private function signedGet($operatorUid, $keyId, $secret, $uri, $nonce)
    {
        $headers = $this->signedB2BHeaders($operatorUid, $keyId, $secret, 'GET', $uri, '', $nonce);

        return $this->signedB2BRequest('GET', $uri, '', $headers);
    }

    private function seedStatusLookupData()
    {
        $now = now();

        $transactionA = DB::table('b2b_wallet_transactions')->insertGetId([
            'operator_id' => $this->operatorA->id,
            'session_id' => 'sess_status_a',
            'game_uid' => 'book_status',
            'round_id' => 'round_status',
            'transaction_uid' => 'shared_status',
            'transaction_id' => 'operator_status_a',
            'idempotency_key' => 'status_a',
            'type' => 'bet',
            'amount' => '10.00000000',
            'currency' => 'USD',
            'status' => 'unknown',
            'attempts' => 2,
            'last_error' => 'Wallet result could not be confirmed.',
            'raw_request' => json_encode([
                'player_id' => 'player_status',
                'access_token' => 'legacy-status-secret',
                'metadata' => ['password' => 'legacy-status-password'],
            ]),
            'raw_response' => json_encode([
                'status' => 'failed',
                'secret' => 'legacy-response-secret',
            ]),
            'operator_response_body' => json_encode([
                'status' => 'failed',
                'secret' => 'legacy-response-secret',
            ]),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $transactionB = DB::table('b2b_wallet_transactions')->insertGetId([
            'operator_id' => $this->operatorB->id,
            'session_id' => 'sess_status_b',
            'game_uid' => 'book_status',
            'round_id' => 'round_status',
            'transaction_uid' => 'shared_status',
            'transaction_id' => 'only_status_b',
            'idempotency_key' => 'status_b',
            'type' => 'bet',
            'amount' => '20.00000000',
            'currency' => 'USD',
            'status' => 'dead_letter',
            'attempts' => 3,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('b2b_wallet_transaction_transitions')->insert([
            [
                'wallet_transaction_id' => $transactionA,
                'operator_id' => $this->operatorA->id,
                'transaction_uid' => 'shared_status',
                'from_status' => 'timeout',
                'to_status' => 'unknown',
                'reason' => 'wallet_status_lookup_seed',
                'actor' => 'test',
                'context' => json_encode(['operator' => 'a', 'signature' => 'legacy-status-secret']),
                'created_at' => $now,
            ],
            [
                'wallet_transaction_id' => $transactionB,
                'operator_id' => $this->operatorB->id,
                'transaction_uid' => 'shared_status',
                'from_status' => 'timeout',
                'to_status' => 'dead_letter',
                'reason' => 'wallet_status_lookup_seed',
                'actor' => 'test',
                'context' => json_encode(['operator' => 'b']),
                'created_at' => $now,
            ],
        ]);

        DB::table('b2b_wallet_transaction_attempts')->insert([
            [
                'wallet_transaction_id' => $transactionA,
                'operator_id' => $this->operatorA->id,
                'transaction_uid' => 'shared_status',
                'type' => 'bet',
                'attempt_no' => 2,
                'result' => 'timeout',
                'request_body' => json_encode(['access_token' => 'legacy-status-secret', 'player_id' => 'player_status']),
                'response_body' => json_encode(['api_key' => 'legacy-response-secret', 'status' => 'timeout']),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'wallet_transaction_id' => $transactionB,
                'operator_id' => $this->operatorB->id,
                'transaction_uid' => 'shared_status',
                'type' => 'bet',
                'attempt_no' => 3,
                'result' => 'failed',
                'request_body' => null,
                'response_body' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('b2b_wallet_reconciliation_items')->insert([
            [
                'wallet_transaction_id' => $transactionA,
                'operator_id' => $this->operatorA->id,
                'transaction_uid' => 'shared_status',
                'status' => 'unknown',
                'reason' => 'unknown_result',
                'priority' => 'medium',
                'state' => 'open',
                'context' => json_encode(['operator' => 'a', 'token' => 'legacy-status-secret']),
                'detected_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'wallet_transaction_id' => $transactionB,
                'operator_id' => $this->operatorB->id,
                'transaction_uid' => 'shared_status',
                'status' => 'dead_letter',
                'reason' => 'dead_letter',
                'priority' => 'high',
                'state' => 'open',
                'context' => json_encode(['operator' => 'b']),
                'detected_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
