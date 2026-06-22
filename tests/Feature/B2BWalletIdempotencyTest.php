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

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->resetB2BTables();
        $this->createB2BOperator($this->operatorUid, $this->keyId, $this->secret, [
            'wallet_callback_url' => 'http://wallet.example/callback',
        ]);

        Http::fake([
            'wallet.example/*' => Http::response([
                'status' => 'ok',
                'balance' => '100.00000000',
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

    private function signedPost($uri, $body, $nonce)
    {
        $headers = $this->signedB2BHeaders($this->operatorUid, $this->keyId, $this->secret, 'POST', $uri, $body, $nonce);

        return $this->signedB2BRequest('POST', $uri, $body, $headers);
    }

    private function walletBody($amount)
    {
        return json_encode([
            'player_id' => 'player_1',
            'game_id' => 'book_of_idempotency',
            'session_id' => 'sess_idempotency',
            'round_id' => 'round_idempotency',
            'transaction_id' => 'tx_idempotency',
            'amount' => $amount,
            'currency' => 'USD',
            'metadata' => [
                'bet_line' => 'main',
                'nested' => [
                    'b' => '2',
                    'a' => '1',
                ],
            ],
        ]);
    }
}
