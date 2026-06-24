<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\B2BApiTestHelpers;
use Tests\TestCase;

class B2BOperatorFlowIsolationTest extends TestCase
{
    use B2BApiTestHelpers;

    private $operatorA;
    private $operatorB;
    private $secretA = 'flow_secret_a_1234567890';
    private $secretB = 'flow_secret_b_1234567890';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->resetB2BTables();

        $this->operatorA = $this->createB2BOperator('op_flow_a', 'key_flow_a', $this->secretA, [
            'shop_id' => 10,
            'wallet_callback_url' => 'http://wallet-a.example/callback',
            'base_url' => 'https://operator-a.example',
        ]);
        $this->operatorB = $this->createB2BOperator('op_flow_b', 'key_flow_b', $this->secretB, [
            'shop_id' => 20,
            'wallet_callback_url' => 'http://wallet-b.example/callback',
            'base_url' => 'https://operator-b.example',
        ]);

        $this->seedGames();
        $this->createB2BSession($this->operatorA, 'player_a', 'sess_flow_a', 'book_flow_a');
        $this->createB2BSession($this->operatorB, 'player_b', 'sess_flow_b', 'book_flow_b');

        Http::fake([
            'wallet-a.example/*' => Http::response(['status' => 'ok', 'balance' => '90.00000000'], 200),
            'wallet-b.example/*' => Http::response(['status' => 'ok', 'balance' => '80.00000000'], 200),
        ]);
    }

    public function testGameCatalogFallbackOnlyReturnsSignedOperatorsShopGames()
    {
        $response = $this->signedGet('op_flow_a', 'key_flow_a', $this->secretA, '/api/b2b/v1/games', 'flow-games-a');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.game_uid', 'book_flow_a');
    }

    public function testLaunchRejectsGameFromAnotherOperatorShop()
    {
        $body = json_encode([
            'player_id' => 'player_a',
            'game_id' => 'book_flow_b',
            'currency' => 'USD',
            'return_url' => 'https://operator-a.example/casino',
        ]);

        $this->signedPost('op_flow_a', 'key_flow_a', $this->secretA, '/api/b2b/v1/games/launch', $body, 'flow-launch-foreign')
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'GAME_NOT_AVAILABLE');

        $this->assertSame(2, DB::table('b2b_game_sessions')->count());
    }

    public function testLaunchCreatesSessionOnlyForOperatorsOwnGame()
    {
        $body = json_encode([
            'player_id' => 'player_new',
            'game_id' => 'book_flow_a',
            'currency' => 'USD',
            'return_url' => 'https://operator-a.example/casino',
        ]);

        $response = $this->signedPost('op_flow_a', 'key_flow_a', $this->secretA, '/api/b2b/v1/games/launch', $body, 'flow-launch-own');

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.game_id', 'book_flow_a');

        $session = DB::table('b2b_game_sessions')
            ->where('session_uid', $response->json('data.session_id'))
            ->first();

        $this->assertNotNull($session);
        $this->assertEquals((string) $this->operatorA->id, (string) $session->operator_id);
        $this->assertSame('book_flow_a', $session->game_uid);
    }

    public function testWalletMutationRejectsAnotherOperatorsSessionBeforeLedgerOrCallback()
    {
        $body = json_encode([
            'player_id' => 'player_a',
            'game_id' => 'book_flow_b',
            'session_id' => 'sess_flow_b',
            'round_id' => 'round_foreign_session',
            'transaction_id' => 'tx_foreign_session',
            'amount' => '10.00000000',
            'currency' => 'USD',
        ]);

        $this->signedPost('op_flow_a', 'key_flow_a', $this->secretA, '/api/b2b/v1/wallet/bet', $body, 'flow-wallet-foreign-session')
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'SESSION_NOT_FOUND');

        $this->assertSame(0, DB::table('b2b_wallet_transactions')->count());
        Http::assertSentCount(0);
    }

    public function testWalletMutationAcceptsSignedOperatorsOwnSession()
    {
        $body = json_encode([
            'player_id' => 'player_a',
            'game_id' => 'book_flow_a',
            'session_id' => 'sess_flow_a',
            'round_id' => 'round_own_session',
            'transaction_id' => 'tx_own_session',
            'amount' => '10.00000000',
            'currency' => 'USD',
        ]);

        $this->signedPost('op_flow_a', 'key_flow_a', $this->secretA, '/api/b2b/v1/wallet/bet', $body, 'flow-wallet-own-session')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'success');

        $transaction = DB::table('b2b_wallet_transactions')->where('transaction_id', 'tx_own_session')->first();
        $this->assertNotNull($transaction);
        $this->assertEquals((string) $this->operatorA->id, (string) $transaction->operator_id);
        Http::assertSentCount(1);
    }

    private function signedGet($operatorUid, $keyId, $secret, $uri, $nonce)
    {
        $headers = $this->signedB2BHeaders($operatorUid, $keyId, $secret, 'GET', $uri, '', $nonce);

        return $this->signedB2BRequest('GET', $uri, '', $headers);
    }

    private function signedPost($operatorUid, $keyId, $secret, $uri, $body, $nonce)
    {
        $headers = $this->signedB2BHeaders($operatorUid, $keyId, $secret, 'POST', $uri, $body, $nonce);

        return $this->signedB2BRequest('POST', $uri, $body, $headers);
    }

    private function seedGames()
    {
        DB::table('games')->insert([
            [
                'name' => 'book_flow_a',
                'title' => 'Book Flow A',
                'shop_id' => 10,
                'view' => 1,
                'category' => 'slots',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'book_flow_b',
                'title' => 'Book Flow B',
                'shop_id' => 20,
                'view' => 1,
                'category' => 'slots',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'hidden_flow_a',
                'title' => 'Hidden Flow A',
                'shop_id' => 10,
                'view' => 0,
                'category' => 'slots',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
