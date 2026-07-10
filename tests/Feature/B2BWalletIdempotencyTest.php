<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\B2BApiTestHelpers;
use Tests\TestCase;

class B2BWalletIdempotencyTest extends TestCase
{
    use B2BApiTestHelpers;

    private $operatorUid = 'op_idempotency';
    private $keyId = 'key_idempotency';
    private $secret = 'idempotency_secret_1234567890';
    private $operator;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->resetB2BTables();
        $this->operator = $this->createB2BOperator($this->operatorUid, $this->keyId, $this->secret, [
            'wallet_callback_url' => 'http://wallet.example/callback',
        ]);
        $this->createB2BSession($this->operator, 'player_1', 'sess_idempotency', 'book_of_idempotency');

        Http::fake([
            'wallet.example/*' => Http::response([
                'status' => 'ok',
                'balance' => '100.00000000',
                'access_token' => 'operator-response-token',
                'nested' => [
                    'signature' => 'operator-response-signature',
                    'safe' => 'kept',
                ],
            ], 200),
        ]);
    }

    public function testExactDuplicateWalletTransactionReturnsStoredResultWithoutSecondCallback()
    {
        $body = $this->walletBody('10.00000000');

        $first = $this->signedPost('/api/b2b/v1/wallet/bet', $body, 'idempotency-first');
        $first->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'success');

        $second = $this->signedPost('/api/b2b/v1/wallet/bet', $body, 'idempotency-duplicate');
        $second->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.duplicate', true)
            ->assertJsonPath('data.status', 'success');

        $this->assertSame(1, DB::table('b2b_wallet_transactions')->count());
        Http::assertSentCount(1);
    }

    public function testExactDuplicateWalletMutationsReturnStoredResultWithoutSecondCallback()
    {
        $sent = 0;

        foreach (['bet', 'win', 'refund', 'rollback'] as $type) {
            $body = $this->walletBody('10.00000000', 'tx_duplicate_' . $type, 'round_duplicate_' . $type);
            $uri = '/api/b2b/v1/wallet/' . $type;

            $this->signedPost($uri, $body, 'idempotency-' . $type . '-first')
                ->assertStatus(200)
                ->assertJsonPath('success', true)
                ->assertJsonPath('data.status', 'success');
            $sent++;

            $this->signedPost($uri, $body, 'idempotency-' . $type . '-duplicate')
                ->assertStatus(200)
                ->assertJsonPath('success', true)
                ->assertJsonPath('data.duplicate', true)
                ->assertJsonPath('data.status', 'success');

            $this->assertSame(
                1,
                DB::table('b2b_wallet_transactions')->where('type', $type)->where('transaction_id', 'tx_duplicate_' . $type)->count()
            );
            Http::assertSentCount($sent);
        }
    }

    public function testChangedPayloadForSameWalletTransactionIsRejectedAsConflict()
    {
        $this->signedPost('/api/b2b/v1/wallet/bet', $this->walletBody('10.00000000'), 'idempotency-conflict-first')
            ->assertStatus(200);

        $conflict = $this->signedPost('/api/b2b/v1/wallet/bet', $this->walletBody('20.00000000'), 'idempotency-conflict-second');
        $conflict->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('error.code', 'IDEMPOTENCY_CONFLICT');

        $this->assertSame(1, DB::table('b2b_wallet_transactions')->count());
        Http::assertSentCount(1);
    }

    public function testChangedPayloadForSameWalletMutationIsRejectedAsConflictForEveryMutationType()
    {
        $sent = 0;

        foreach (['bet', 'win', 'refund', 'rollback'] as $type) {
            $uri = '/api/b2b/v1/wallet/' . $type;

            $this->signedPost($uri, $this->walletBody('10.00000000', 'tx_conflict_' . $type, 'round_conflict_' . $type), 'idempotency-conflict-' . $type . '-first')
                ->assertStatus(200);
            $sent++;

            $this->signedPost($uri, $this->walletBody('20.00000000', 'tx_conflict_' . $type, 'round_conflict_' . $type), 'idempotency-conflict-' . $type . '-second')
                ->assertStatus(409)
                ->assertJsonPath('success', false)
                ->assertJsonPath('status', 'error')
                ->assertJsonPath('error.code', 'IDEMPOTENCY_CONFLICT');

            $this->assertSame(
                1,
                DB::table('b2b_wallet_transactions')->where('type', $type)->where('transaction_id', 'tx_conflict_' . $type)->count()
            );
            Http::assertSentCount($sent);
        }
    }

    public function testWalletPayloadStorageRedactsSensitiveFields()
    {
        $this->signedPost('/api/b2b/v1/wallet/bet', $this->walletBody('10.00000000'), 'idempotency-redaction')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $transaction = DB::table('b2b_wallet_transactions')->first();
        $rawRequest = json_decode($transaction->raw_request, true);
        $operatorResponse = json_decode($transaction->operator_response_body, true);
        $rawResponse = json_decode($transaction->raw_response, true);
        $attempt = DB::table('b2b_wallet_transaction_attempts')->first();
        $attemptRequest = json_decode($attempt->request_body, true);
        $attemptResponse = json_decode($attempt->response_body, true);

        $this->assertSame('player_1', $rawRequest['player_id']);
        $this->assertSame('book_of_idempotency', $rawRequest['game_id']);
        $this->assertSame('[REDACTED]', $rawRequest['metadata']['access_token']);
        $this->assertSame('[REDACTED]', $rawRequest['metadata']['nested']['password']);
        $this->assertSame('1', $rawRequest['metadata']['nested']['a']);

        $this->assertSame('[REDACTED]', $operatorResponse['access_token']);
        $this->assertSame('[REDACTED]', $operatorResponse['nested']['signature']);
        $this->assertSame('kept', $operatorResponse['nested']['safe']);
        $this->assertSame('[REDACTED]', $rawResponse['body']['access_token']);

        $this->assertSame('bet', $attemptRequest['action']);
        $this->assertSame('[REDACTED]', $attemptRequest['metadata']['access_token']);
        $this->assertSame('[REDACTED]', $attemptResponse['access_token']);

        $storedPayloads = implode("\n", [
            $transaction->raw_request,
            $transaction->operator_response_body,
            $transaction->raw_response,
            $attempt->request_body,
            $attempt->response_body,
        ]);
        $this->assertStringNotContainsString('request-token-secret', $storedPayloads);
        $this->assertStringNotContainsString('request-password-secret', $storedPayloads);
        $this->assertStringNotContainsString('operator-response-token', $storedPayloads);
        $this->assertStringNotContainsString('operator-response-signature', $storedPayloads);
    }

    private function signedPost($uri, $body, $nonce)
    {
        $headers = $this->signedB2BHeaders($this->operatorUid, $this->keyId, $this->secret, 'POST', $uri, $body, $nonce);

        return $this->signedB2BRequest('POST', $uri, $body, $headers);
    }

    private function walletBody($amount, $transactionId = 'tx_idempotency', $roundId = 'round_idempotency')
    {
        return json_encode([
            'player_id' => 'player_1',
            'game_id' => 'book_of_idempotency',
            'session_id' => 'sess_idempotency',
            'round_id' => $roundId,
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'currency' => 'USD',
            'metadata' => [
                'bet_line' => 'main',
                'nested' => [
                    'b' => '2',
                    'a' => '1',
                    'password' => 'request-password-secret',
                ],
                'access_token' => 'request-token-secret',
            ],
        ]);
    }
}
