<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use VanguardLTE\B2B\Models\B2BOperator;
use VanguardLTE\B2B\Models\B2BOperatorApiKey;
use VanguardLTE\B2B\Services\B2BSignature;

class B2BSignatureTest extends TestCase
{
    private $secret = 'test_secret_1234567890';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->resetTables();
        $this->createOperator('op_demo', 'key_demo', $this->secret, ['127.0.0.0/8']);
    }

    public function testCanonicalSignatureAllowsOperatorRequest()
    {
        $headers = $this->signedHeaders('GET', '/api/b2b/v1/operator/me', '', 'nonce-ok');

        $response = $this->signedRequest('GET', '/api/b2b/v1/operator/me', '', $headers);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.operator.id', 'op_demo');

        $this->assertNotEmpty($response->headers->get('X-Request-Id'));
    }

    public function testReplayNonceIsRejected()
    {
        $headers = $this->signedHeaders('GET', '/api/b2b/v1/operator/me', '', 'nonce-replay');

        $this->signedRequest('GET', '/api/b2b/v1/operator/me', '', $headers)->assertStatus(200);

        $this->signedRequest('GET', '/api/b2b/v1/operator/me', '', $headers)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'B2B_REPLAY_DETECTED');
    }

    public function testBodyHashMismatchIsRejected()
    {
        $body = json_encode(['player_id' => 'p1']);
        $headers = $this->signedHeaders('POST', '/api/b2b/v1/wallet/balance', $body, 'nonce-body');
        $headers['X-Body-Hash'] = B2BSignature::bodyHash('tampered');

        $this->signedRequest('POST', '/api/b2b/v1/wallet/balance', $body, $headers)
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'B2B_BODY_HASH_MISMATCH');
    }

    public function testIpOutsideAllowlistIsRejected()
    {
        $headers = $this->signedHeaders('GET', '/api/b2b/v1/operator/me', '', 'nonce-ip');

        $this->signedRequest('GET', '/api/b2b/v1/operator/me', '', $headers, '10.10.10.10')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'B2B_AUTH_FAILED');
    }

    private function signedRequest($method, $uri, $body, array $headers, $remoteAddr = '127.0.0.1')
    {
        $server = [
            'REMOTE_ADDR' => $remoteAddr,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ];

        foreach ($headers as $name => $value) {
            $server['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $this->call($method, $uri, [], [], [], $server, $body);
    }

    private function signedHeaders($method, $path, $body, $nonce)
    {
        $timestamp = (string) time();
        $bodyHash = B2BSignature::bodyHash($body);
        $pathOnly = parse_url($path, PHP_URL_PATH) ?: '/';
        $queryString = parse_url($path, PHP_URL_QUERY) ?: '';
        $canonical = B2BSignature::canonicalFromParts($method, $pathOnly, $queryString, $bodyHash, $timestamp, $nonce);

        return [
            'X-Operator-Id' => 'op_demo',
            'X-Api-Key' => 'key_demo',
            'X-Timestamp' => $timestamp,
            'X-Nonce' => $nonce,
            'X-Body-Hash' => $bodyHash,
            'X-Signature' => hash_hmac('sha256', $canonical, $this->secret),
        ];
    }

    private function createOperator($operatorUid, $keyId, $secret, array $ipWhitelist)
    {
        $operator = B2BOperator::create([
            'operator_uid' => $operatorUid,
            'name' => 'Demo Operator',
            'status' => B2BOperator::STATUS_ACTIVE,
            'default_currency' => 'USD',
            'allowed_currencies' => ['USD'],
            'ip_whitelist' => $ipWhitelist,
        ]);

        B2BOperatorApiKey::create([
            'operator_id' => $operator->id,
            'key_id' => $keyId,
            'secret_encrypted' => Crypt::encryptString($secret),
            'status' => B2BOperatorApiKey::STATUS_ACTIVE,
        ]);
    }

    private function resetTables()
    {
        foreach ([
            'b2b_wallet_transactions',
            'b2b_game_sessions',
            'b2b_operator_players',
            'b2b_operator_api_keys',
            'b2b_operators',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('b2b_operators', function (Blueprint $table) {
            $table->increments('id');
            $table->string('operator_uid')->unique();
            $table->string('name');
            $table->integer('shop_id')->nullable();
            $table->string('status')->default('active');
            $table->string('base_url')->nullable();
            $table->string('wallet_callback_url')->nullable();
            $table->string('default_currency', 3)->default('USD');
            $table->json('allowed_currencies')->nullable();
            $table->json('ip_whitelist')->nullable();
            $table->json('settings')->nullable();
            $table->integer('failure_count')->default(0);
            $table->integer('max_rps')->default(50);
            $table->integer('wallet_timeout_ms')->default(5000);
            $table->integer('connect_timeout_ms')->default(1500);
            $table->timestamp('circuit_open_until')->nullable();
            $table->timestamps();
        });

        Schema::create('b2b_operator_api_keys', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('operator_id');
            $table->string('key_id');
            $table->text('secret_encrypted');
            $table->string('status')->default('active');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('b2b_operator_players', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('operator_id');
            $table->string('external_player_id');
            $table->string('currency', 3);
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('b2b_game_sessions', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('operator_id');
            $table->integer('operator_player_id');
            $table->string('session_uid')->unique();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('b2b_wallet_transactions', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('operator_id');
            $table->integer('operator_player_id')->nullable();
            $table->string('transaction_uid')->nullable();
            $table->string('type')->nullable();
            $table->decimal('amount', 20, 8)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }
}
