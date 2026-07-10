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

    public function testGameDetailReturnsOnlySignedOperatorsLegacyGame()
    {
        $this->signedGet('op_flow_a', 'key_flow_a', $this->secretA, '/api/b2b/v1/games/book_flow_a', 'flow-game-detail-own')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.game_uid', 'book_flow_a')
            ->assertJsonPath('data.provider', 'goldsvet_internal')
            ->assertJsonPath('data.title', 'Book Flow A');

        $this->signedGet('op_flow_a', 'key_flow_a', $this->secretA, '/api/b2b/v1/games/book_flow_b', 'flow-game-detail-foreign')
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'GAME_NOT_AVAILABLE');

        $this->signedGet('op_flow_a', 'key_flow_a', $this->secretA, '/api/b2b/v1/games/book_flow_a?mode=practice', 'flow-game-detail-invalid-mode')
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function testGameCatalogSupportsBoundedFiltersAndSorting()
    {
        DB::table('b2b_game_catalog')->insert([
            [
                'game_uid' => 'catalog_sort_zulu',
                'provider' => 'external_provider',
                'title' => 'Zulu Sort',
                'category' => 'slots',
                'demo_supported' => true,
                'real_supported' => true,
                'supported_currencies' => json_encode(['USD']),
                'supported_countries' => json_encode(['BR']),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'game_uid' => 'catalog_sort_alpha',
                'provider' => 'external_provider',
                'title' => 'Alpha Sort',
                'category' => 'slots',
                'demo_supported' => true,
                'real_supported' => true,
                'supported_currencies' => json_encode(['USD']),
                'supported_countries' => json_encode(['BR']),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'game_uid' => 'catalog_sort_table',
                'provider' => 'external_provider',
                'title' => 'Table Sort',
                'category' => 'table',
                'demo_supported' => true,
                'real_supported' => true,
                'supported_currencies' => json_encode(['USD']),
                'supported_countries' => json_encode(['BR']),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->signedGet(
            'op_flow_a',
            'key_flow_a',
            $this->secretA,
            '/api/b2b/v1/games?provider=external_provider&category=slots&currency=USD&country=BR&mode=real&sort=title&limit=1',
            'flow-catalog-filter-sort'
        )
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.game_uid', 'catalog_sort_alpha')
            ->assertJsonPath('data.0.title', 'Alpha Sort')
            ->assertJsonPath('meta.limit', 1)
            ->assertJsonPath('meta.count', 1)
            ->assertJsonPath('meta.available_count', 2)
            ->assertJsonPath('meta.sort', 'title')
            ->assertJsonPath('meta.filters.provider', 'external_provider')
            ->assertJsonPath('meta.filters.category', 'slots')
            ->assertJsonPath('meta.filters.currency', 'USD')
            ->assertJsonPath('meta.filters.country', 'BR')
            ->assertJsonPath('meta.filters.mode', 'real')
            ->assertJsonPath('meta.source', 'b2b_game_catalog');

        $this->signedGet(
            'op_flow_a',
            'key_flow_a',
            $this->secretA,
            '/api/b2b/v1/games?sort=created_at',
            'flow-catalog-invalid-sort'
        )
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
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

    public function testLaunchRejectsReturnUrlOutsideOperatorAllowlistBeforeSessionCreation()
    {
        $body = json_encode([
            'player_id' => 'player_a',
            'game_id' => 'book_flow_a',
            'currency' => 'USD',
            'return_url' => 'https://evil.example/casino',
        ]);

        $this->signedPost('op_flow_a', 'key_flow_a', $this->secretA, '/api/b2b/v1/games/launch', $body, 'flow-launch-bad-return-url')
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'RETURN_URL_NOT_ALLOWED');

        $this->assertSame(2, DB::table('b2b_game_sessions')->count());
    }

    public function testLaunchCreatesSessionOnlyForOperatorsOwnGame()
    {
        $body = json_encode([
            'player_id' => 'player_new',
            'game_id' => 'book_flow_a',
            'currency' => 'USD',
            'return_url' => 'https://operator-a.example/casino',
            'metadata' => [
                'safe_note' => 'operator launch metadata',
                'token' => 'metadata-token-secret',
                'nested' => [
                    'api_secret' => 'metadata-api-secret',
                ],
            ],
        ]);

        $response = $this->signedPost('op_flow_a', 'key_flow_a', $this->secretA, '/api/b2b/v1/games/launch', $body, 'flow-launch-own');

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.game_id', 'book_flow_a');

        $launchUrl = $response->json('data.launch_url');
        $launchToken = basename((string) parse_url($launchUrl, PHP_URL_PATH));
        $this->assertSame(64, strlen($launchToken));

        $session = DB::table('b2b_game_sessions')
            ->where('session_uid', $response->json('data.session_id'))
            ->first();

        $this->assertNotNull($session);
        $this->assertEquals((string) $this->operatorA->id, (string) $session->operator_id);
        $this->assertSame('book_flow_a', $session->game_uid);
        $this->assertSame(hash('sha256', $launchToken), $session->token_hash);
        $this->assertNull($session->launch_url);
        $this->assertSame('https://operator-a.example/casino', $session->return_url);
        $this->assertNotNull($session->expires_at);

        $detail = $this->signedGet('op_flow_a', 'key_flow_a', $this->secretA, '/api/b2b/v1/sessions/' . $session->session_uid, 'flow-launch-detail-no-token');
        $detail->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.session.session_uid', $session->session_uid);

        $sessionPayload = $detail->json('data.session');
        $this->assertArrayNotHasKey('token_hash', $sessionPayload);
        $this->assertArrayNotHasKey('launch_url', $sessionPayload);
        $this->assertArrayNotHasKey('legacy_launch_token', $sessionPayload);
        $this->assertArrayNotHasKey('legacy_launch_url', $sessionPayload);
        $this->assertSame('[REDACTED]', $sessionPayload['metadata']['token']);
        $this->assertSame('[REDACTED]', $sessionPayload['metadata']['nested']['api_secret']);
        $this->assertSame('operator launch metadata', $sessionPayload['metadata']['safe_note']);

        $list = $this->signedGet('op_flow_a', 'key_flow_a', $this->secretA, '/api/b2b/v1/sessions?limit=10', 'flow-launch-list-no-token');
        $list->assertStatus(200)
            ->assertJsonPath('success', true);

        $listedSession = collect($list->json('data'))->firstWhere('session_uid', $session->session_uid);
        $this->assertNotNull($listedSession);
        $this->assertArrayNotHasKey('token_hash', $listedSession);
        $this->assertArrayNotHasKey('launch_url', $listedSession);
        $this->assertArrayNotHasKey('legacy_launch_token', $listedSession);
        $this->assertArrayNotHasKey('legacy_launch_url', $listedSession);
        $this->assertSame('[REDACTED]', $listedSession['metadata']['token']);
        $this->assertSame('[REDACTED]', $listedSession['metadata']['nested']['api_secret']);
    }

    public function testPerApiKeyRateLimitBlocksLaunchBeforeSecondSessionCreation()
    {
        DB::table('b2b_operator_api_keys')
            ->where('key_id', 'key_flow_a')
            ->update(['max_rps' => 1]);

        $body = json_encode([
            'player_id' => 'player_rate',
            'game_id' => 'book_flow_a',
            'currency' => 'USD',
            'return_url' => 'https://operator-a.example/casino',
        ]);

        $this->signedPost('op_flow_a', 'key_flow_a', $this->secretA, '/api/b2b/v1/games/launch', $body, 'flow-launch-rate-1')
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->signedPost('op_flow_a', 'key_flow_a', $this->secretA, '/api/b2b/v1/games/launch', $body, 'flow-launch-rate-2')
            ->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'RATE_LIMITED')
            ->assertJsonPath('meta.rate_scope', 'api_key')
            ->assertJsonPath('meta.limit', 1);

        $this->assertSame(3, DB::table('b2b_game_sessions')->count());
    }

    public function testDedicatedAssignmentsRestrictLegacyFallbackCatalog()
    {
        $this->seedVisibleGame('book_flow_extra_a', 10);
        $this->assignOperatorGame($this->operatorA, 'book_flow_a');

        $response = $this->signedGet('op_flow_a', 'key_flow_a', $this->secretA, '/api/b2b/v1/games', 'flow-games-assigned-a');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.game_uid', 'book_flow_a');
    }

    public function testDedicatedAssignmentsRejectUnassignedLegacyLaunch()
    {
        $this->seedVisibleGame('book_flow_extra_a', 10);
        $this->assignOperatorGame($this->operatorA, 'book_flow_a');

        $body = json_encode([
            'player_id' => 'player_a',
            'game_id' => 'book_flow_extra_a',
            'currency' => 'USD',
            'return_url' => 'https://operator-a.example/casino',
        ]);

        $this->signedPost('op_flow_a', 'key_flow_a', $this->secretA, '/api/b2b/v1/games/launch', $body, 'flow-launch-unassigned')
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'GAME_NOT_AVAILABLE');

        $this->assertSame(2, DB::table('b2b_game_sessions')->count());
    }

    public function testDedicatedBlockedAssignmentRejectsOtherwiseVisibleGame()
    {
        $this->assignOperatorGame($this->operatorA, 'book_flow_a', 'blocked');

        $body = json_encode([
            'player_id' => 'player_a',
            'game_id' => 'book_flow_a',
            'currency' => 'USD',
            'return_url' => 'https://operator-a.example/casino',
        ]);

        $this->signedPost('op_flow_a', 'key_flow_a', $this->secretA, '/api/b2b/v1/games/launch', $body, 'flow-launch-blocked')
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'GAME_NOT_AVAILABLE');

        $this->assertSame(2, DB::table('b2b_game_sessions')->count());
    }

    public function testDedicatedAssignmentsScopeCatalogOnlyGames()
    {
        DB::table('b2b_game_catalog')->insert([
            [
                'game_uid' => 'catalog_flow_a',
                'provider' => 'external_provider',
                'title' => 'Catalog Flow A',
                'category' => 'slots',
                'demo_supported' => true,
                'real_supported' => true,
                'supported_currencies' => json_encode(['USD']),
                'supported_countries' => json_encode(['BR']),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'game_uid' => 'catalog_flow_b',
                'provider' => 'external_provider',
                'title' => 'Catalog Flow B',
                'category' => 'slots',
                'demo_supported' => true,
                'real_supported' => true,
                'supported_currencies' => json_encode(['USD']),
                'supported_countries' => json_encode(['BR']),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        $this->assignOperatorGame($this->operatorA, 'catalog_flow_a', 'allowed', [
            'provider' => 'external_provider',
            'allowed_currencies' => json_encode(['USD']),
            'allowed_countries' => json_encode(['BR']),
        ]);

        $this->signedGet('op_flow_a', 'key_flow_a', $this->secretA, '/api/b2b/v1/games?currency=USD&country=BR', 'flow-catalog-assigned')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.game_uid', 'catalog_flow_a');

        $this->signedGet('op_flow_a', 'key_flow_a', $this->secretA, '/api/b2b/v1/games/catalog_flow_a?currency=USD&country=BR', 'flow-catalog-detail-assigned')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.game_uid', 'catalog_flow_a')
            ->assertJsonPath('data.provider', 'external_provider')
            ->assertJsonPath('data.supported_currencies.0', 'USD');

        $this->signedGet('op_flow_a', 'key_flow_a', $this->secretA, '/api/b2b/v1/games/catalog_flow_b?currency=USD&country=BR', 'flow-catalog-detail-unassigned')
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'GAME_NOT_AVAILABLE');

        $body = json_encode([
            'player_id' => 'player_catalog',
            'game_id' => 'catalog_flow_a',
            'currency' => 'USD',
            'country' => 'BR',
            'return_url' => 'https://operator-a.example/casino',
        ]);

        $this->signedPost('op_flow_a', 'key_flow_a', $this->secretA, '/api/b2b/v1/games/launch', $body, 'flow-launch-catalog-assigned')
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.game_id', 'catalog_flow_a')
            ->assertJsonPath('data.provider', 'external_provider');

        $unassigned = json_encode([
            'player_id' => 'player_catalog',
            'game_id' => 'catalog_flow_b',
            'currency' => 'USD',
            'country' => 'BR',
            'return_url' => 'https://operator-a.example/casino',
        ]);

        $this->signedPost('op_flow_a', 'key_flow_a', $this->secretA, '/api/b2b/v1/games/launch', $unassigned, 'flow-launch-catalog-unassigned')
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'GAME_NOT_AVAILABLE');
    }

    public function testSessionDetailRejectsAnotherOperatorsNumericSessionId()
    {
        $foreignSession = DB::table('b2b_game_sessions')->where('session_uid', 'sess_flow_b')->first();

        $this->signedGet(
            'op_flow_a',
            'key_flow_a',
            $this->secretA,
            '/api/b2b/v1/sessions/' . $foreignSession->id,
            'flow-session-foreign-id'
        )
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'SESSION_NOT_FOUND');
    }

    public function testCloseRejectsAnotherOperatorsNumericSessionId()
    {
        $foreignSession = DB::table('b2b_game_sessions')->where('session_uid', 'sess_flow_b')->first();
        $body = json_encode(['reason' => 'operator_close']);

        $this->signedPost(
            'op_flow_a',
            'key_flow_a',
            $this->secretA,
            '/api/b2b/v1/sessions/' . $foreignSession->id . '/close',
            $body,
            'flow-session-close-foreign-id'
        )
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'SESSION_NOT_FOUND');

        $unchanged = DB::table('b2b_game_sessions')->where('id', $foreignSession->id)->first();
        $this->assertSame('active', $unchanged->status);
        $this->assertNull($unchanged->closed_at);
        $this->assertNull($unchanged->close_reason);
    }

    public function testCloseOwnSessionPersistsReasonAndIsIdempotent()
    {
        $body = json_encode(['reason' => 'player_logout']);

        $this->signedPost(
            'op_flow_a',
            'key_flow_a',
            $this->secretA,
            '/api/b2b/v1/sessions/sess_flow_a/close',
            $body,
            'flow-session-close-own'
        )
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.session_uid', 'sess_flow_a')
            ->assertJsonPath('data.status', 'closed');

        $closed = DB::table('b2b_game_sessions')->where('session_uid', 'sess_flow_a')->first();
        $this->assertSame('closed', $closed->status);
        $this->assertNotNull($closed->closed_at);
        $this->assertSame('player_logout', $closed->close_reason);

        $this->signedPost(
            'op_flow_a',
            'key_flow_a',
            $this->secretA,
            '/api/b2b/v1/sessions/sess_flow_a/close',
            json_encode(['reason' => 'second_close']),
            'flow-session-close-own-repeat'
        )
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'closed');

        $closedAgain = DB::table('b2b_game_sessions')->where('session_uid', 'sess_flow_a')->first();
        $this->assertSame($closed->closed_at, $closedAgain->closed_at);
        $this->assertSame('player_logout', $closedAgain->close_reason);
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

    private function seedVisibleGame($gameUid, $shopId)
    {
        DB::table('games')->insert([
            'name' => $gameUid,
            'title' => $gameUid,
            'shop_id' => $shopId,
            'view' => 1,
            'category' => 'slots',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assignOperatorGame($operator, $gameUid, $status = 'allowed', array $overrides = [])
    {
        DB::table('b2b_operator_game_assignments')->insert(array_merge([
            'operator_id' => $operator->id,
            'game_uid' => $gameUid,
            'provider' => 'goldsvet_internal',
            'status' => $status,
            'demo_enabled' => true,
            'real_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }
}
