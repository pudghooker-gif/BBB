<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\B2BApiTestHelpers;
use Tests\TestCase;
use VanguardLTE\B2B\Contracts\GameProviderInterface;
use VanguardLTE\B2B\Models\B2BGameSession;
use VanguardLTE\B2B\Providers\GoldsvetInternalProvider;

class B2BProviderContractTest extends TestCase
{
    use B2BApiTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->resetB2BTables();
    }

    public function testGoldsvetProviderImplementsOperationalContract()
    {
        $provider = app(GoldsvetInternalProvider::class);

        $this->assertInstanceOf(GameProviderInterface::class, $provider);
        $this->assertSame('goldsvet_internal', $provider->providerCode());
        $this->assertTrue($provider->supportsWalletAction('bet'));
        $this->assertTrue($provider->supportsWalletAction('rollback'));
        $this->assertTrue($provider->supportsWalletAction('transaction_status'));
        $this->assertFalse($provider->supportsWalletAction('unknown'));
        $this->assertTrue($provider->health()['ok']);
        $this->assertSame(GameProviderInterface::CAPABILITY_SUPPORTED, $provider->capability('list_games'));
        $this->assertSame(GameProviderInterface::CAPABILITY_SUPPORTED, $provider->capability('sync_games'));
        $this->assertSame(GameProviderInterface::CAPABILITY_SUPPORTED, $provider->capability('launch'));
        $this->assertSame(GameProviderInterface::CAPABILITY_SUPPORTED, $provider->capability('normalize_transaction'));
        $this->assertSame(GameProviderInterface::CAPABILITY_NOT_APPLICABLE, $provider->capability('close_round'));
        $this->assertSame(GameProviderInterface::CAPABILITY_UNSUPPORTED, $provider->capability('external_free_spins'));

        $contracts = $provider->walletActionContracts();
        foreach (['balance', 'bet', 'win', 'refund', 'rollback', 'transaction_status'] as $action) {
            $this->assertArrayHasKey($action, $contracts);
            $this->assertIsArray($provider->walletActionContract($action));
        }

        $statusContract = $provider->walletActionContract('transaction_status');
        $this->assertContains('transaction_uid', $statusContract['request_fields']);
        $this->assertContains('current_status', $statusContract['request_fields']);
        $this->assertContains('transaction_status', $statusContract['response_fields']);
        $this->assertContains('rollback_required', $statusContract['final_statuses']);
        $this->assertContains('not_found', $statusContract['ambiguous_statuses']);

        $rollbackContract = $provider->walletActionContract('rollback');
        $this->assertSame('transaction_id', $rollbackContract['idempotency_key']);
        $this->assertContains('original_transaction_id', $rollbackContract['request_fields']);
        $this->assertContains('recovery_attempt', $rollbackContract['request_fields']);
        $this->assertContains('accepted', $rollbackContract['terminal_statuses']);
        $this->assertNull($provider->walletActionContract('unknown'));
    }

    public function testGoldsvetProviderListsNormalizedCatalogGames()
    {
        DB::table('games')->insert([
            [
                'name' => 'provider_book_a',
                'title' => 'Provider Book A',
                'shop_id' => 10,
                'view' => 1,
                'category' => 'slots',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'provider_book_hidden',
                'title' => 'Provider Book Hidden',
                'shop_id' => 10,
                'view' => 0,
                'category' => 'slots',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'provider_book_b',
                'title' => 'Provider Book B',
                'shop_id' => 20,
                'view' => 1,
                'category' => 'table',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $provider = app(GoldsvetInternalProvider::class);
        $games = $provider->listGames(['shop_id' => 10]);

        $this->assertCount(1, $games);
        $this->assertSame('provider_book_a', $games[0]['game_uid']);
        $this->assertSame('provider_book_a', $games[0]['provider_game_id']);
        $this->assertSame('provider_book_a', $games[0]['canonical_game_id']);
        $this->assertSame('goldsvet_internal', $games[0]['provider']);
        $this->assertSame('provider-book-a', $games[0]['slug']);
        $this->assertSame('Provider Book A', $games[0]['title']);
        $this->assertSame('slots', $games[0]['category']);
        $this->assertSame('web', $games[0]['platform']);
        $this->assertSame('legacy_launcher', $games[0]['launch_config']['launch_mode']);
        $this->assertSame('active', $games[0]['status']);
        $this->assertSame('games', $games[0]['metadata']['synced_from']);
        $this->assertSame(10, $games[0]['metadata']['shop_id']);
    }

    public function testGoldsvetProviderValidatesAndNormalizesTransactions()
    {
        $provider = app(GoldsvetInternalProvider::class);

        $invalid = $provider->validateIncomingRequest('bet', [
            'player_id' => 'player_provider',
        ]);
        $this->assertFalse($invalid['ok']);
        $this->assertContains('transaction_id', $invalid['missing_fields']);
        $this->assertContains('amount', $invalid['missing_fields']);

        $valid = $provider->validateIncomingRequest('bet', [
            'player_id' => 'player_provider',
            'game_id' => 'provider_book_a',
            'session_id' => 'sess_provider',
            'round_id' => 'round_provider',
            'transaction_id' => 'tx_provider',
            'amount' => '12.34000000',
            'currency' => 'usd',
        ]);
        $this->assertTrue($valid['ok']);

        $normalized = $provider->normalizeTransaction([
            'action' => 'bet',
            'game_uid' => 'provider_book_a',
            'session_id' => 'sess_provider',
            'round_id' => 'round_provider',
            'transaction_id' => 'tx_provider',
            'amount' => '12.34000000',
            'currency' => 'usd',
        ]);

        $this->assertSame('tx_provider', $normalized['transaction_id']);
        $this->assertSame('provider_book_a', $normalized['game_id']);
        $this->assertSame('bet', $normalized['type']);
        $this->assertSame('12.34000000', $normalized['amount']);
        $this->assertSame('USD', $normalized['currency']);
    }

    public function testGoldsvetProviderCanRefreshAndCloseSession()
    {
        $operator = $this->createB2BOperator('op_provider', 'key_provider', 'provider_secret_1234567890');
        $player = $operator->players()->create([
            'external_player_id' => 'player_provider',
            'currency' => 'USD',
            'status' => 'active',
        ]);

        $session = B2BGameSession::create([
            'operator_id' => $operator->id,
            'operator_player_id' => $player->id,
            'session_uid' => 'sess_provider',
            'game_uid' => 'book_of_provider',
            'status' => B2BGameSession::STATUS_ACTIVE,
            'currency' => 'USD',
        ]);

        $provider = app(GoldsvetInternalProvider::class);

        $refresh = $provider->refreshSession($session);
        $this->assertTrue($refresh['ok']);
        $this->assertNotNull($session->fresh()->last_seen_at);

        $close = $provider->closeSession($session->fresh(), 'unit-test-close');
        $this->assertTrue($close['ok']);
        $this->assertSame(B2BGameSession::STATUS_CLOSED, $session->fresh()->status);
        $this->assertNotNull($session->fresh()->closed_at);

        $closeRound = $provider->closeRound($session->fresh(), 'round_provider', 'unit-test-close-round');
        $this->assertFalse($closeRound['ok']);
        $this->assertSame(GameProviderInterface::CAPABILITY_NOT_APPLICABLE, $closeRound['state']);
    }
}
