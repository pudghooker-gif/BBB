<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\B2BApiTestHelpers;
use Tests\TestCase;

class B2BRequestValidationTest extends TestCase
{
    use B2BApiTestHelpers;

    private $operatorUid = 'op_validation';
    private $keyId = 'key_validation';
    private $secret = 'validation_secret_1234567890';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->resetB2BTables();
        $this->createB2BOperator($this->operatorUid, $this->keyId, $this->secret);
    }

    public function testWalletBetRequiresValidatedPayloadBeforeLedgerRow()
    {
        $body = json_encode([
            'player_id' => 'player_1',
            'game_id' => 'book_of_validation',
            'amount' => '10.00000000',
            'currency' => 'USD',
        ]);

        $this->signedPost('/api/b2b/v1/wallet/bet', $body, 'wallet-invalid-bet')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->assertSame(0, DB::table('b2b_wallet_transactions')->count());
    }

    public function testWalletAmountMustBeDecimalString()
    {
        $body = json_encode([
            'player_id' => 'player_1',
            'game_id' => 'book_of_validation',
            'session_id' => 'sess_validation',
            'round_id' => 'round_validation',
            'transaction_id' => 'tx_validation',
            'amount' => 10.0,
            'currency' => 'USD',
        ]);

        $this->signedPost('/api/b2b/v1/wallet/bet', $body, 'wallet-invalid-amount')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->assertSame(0, DB::table('b2b_wallet_transactions')->count());
    }

    public function testWalletCurrencyMustBeAllowedBeforeLedgerRow()
    {
        $body = json_encode([
            'player_id' => 'player_1',
            'game_id' => 'book_of_validation',
            'session_id' => 'sess_validation',
            'round_id' => 'round_validation',
            'transaction_id' => 'tx_validation',
            'amount' => '10.00000000',
            'currency' => 'EUR',
        ]);

        $this->signedPost('/api/b2b/v1/wallet/bet', $body, 'wallet-invalid-currency')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'CURRENCY_NOT_ALLOWED');

        $this->assertSame(0, DB::table('b2b_wallet_transactions')->count());
    }

    public function testLaunchValidationRejectsMissingFieldsBeforeSessionCreation()
    {
        $body = json_encode([
            'player_id' => 'player_1',
            'currency' => 'USD',
        ]);

        $this->signedPost('/api/b2b/v1/games/launch', $body, 'launch-invalid-payload')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->assertSame(0, DB::table('b2b_game_sessions')->count());
    }

    private function signedPost($uri, $body, $nonce)
    {
        $headers = $this->signedB2BHeaders($this->operatorUid, $this->keyId, $this->secret, 'POST', $uri, $body, $nonce);

        return $this->signedB2BRequest('POST', $uri, $body, $headers);
    }
}
