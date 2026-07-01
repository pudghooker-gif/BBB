<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Cache;
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
    }
}
