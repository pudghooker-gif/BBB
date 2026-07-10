<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\B2BApiTestHelpers;
use Tests\TestCase;
use VanguardLTE\B2B\Services\B2BSignature;

class B2BSignatureTest extends TestCase
{
    use B2BApiTestHelpers;

    private $operatorUid = 'op_demo';
    private $keyId = 'key_demo';
    private $secret = 'test_secret_1234567890';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->resetB2BTables();
        $this->createB2BOperator($this->operatorUid, $this->keyId, $this->secret);
    }

    public function testCanonicalSignatureAllowsOperatorRequest()
    {
        $headers = $this->signedHeaders('GET', '/api/b2b/v1/operator/me', '', 'nonce-ok');

        $response = $this->signedB2BRequest('GET', '/api/b2b/v1/operator/me', '', $headers);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.operator.id', 'op_demo');

        $this->assertNotEmpty($response->json('request_id'));
        $this->assertNotEmpty($response->headers->get('X-Request-Id'));

        $event = DB::table('b2b_operator_audit_events')
            ->where('event_type', 'api_key.used')
            ->where('subject_id', $this->keyId)
            ->first();

        $this->assertNotNull($event);
        $this->assertSame('api:op_demo', $event->actor);
        $this->assertSame('127.0.0.1', $event->ip_address);

        $metadata = json_decode($event->metadata, true);
        $this->assertSame('GET', $metadata['method']);
        $this->assertSame('/api/b2b/v1/operator/me', $metadata['path']);
        $this->assertArrayNotHasKey('signature', $metadata);
    }

    public function testSignedRequestRequiresRouteApiKeyScope()
    {
        DB::table('b2b_operator_api_keys')
            ->where('key_id', $this->keyId)
            ->update(['scopes' => json_encode(['portal.read'])]);

        $headers = $this->signedHeaders('GET', '/api/b2b/v1/operator/me', '', 'nonce-missing-operator-scope');

        $this->signedB2BRequest('GET', '/api/b2b/v1/operator/me', '', $headers)
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'B2B_SCOPE_DENIED')
            ->assertJsonPath('meta.required_scopes.0', 'operator.read');
    }

    public function testReplayNonceIsRejected()
    {
        $headers = $this->signedHeaders('GET', '/api/b2b/v1/operator/me', '', 'nonce-replay');

        $this->signedB2BRequest('GET', '/api/b2b/v1/operator/me', '', $headers)->assertStatus(200);

        $this->signedB2BRequest('GET', '/api/b2b/v1/operator/me', '', $headers)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'B2B_REPLAY_DETECTED');
    }

    public function testBodyHashMismatchIsRejected()
    {
        $body = json_encode(['player_id' => 'p1']);
        $headers = $this->signedHeaders('POST', '/api/b2b/v1/wallet/balance', $body, 'nonce-body');
        $headers['X-Body-Hash'] = B2BSignature::bodyHash('tampered');

        $this->signedB2BRequest('POST', '/api/b2b/v1/wallet/balance', $body, $headers)
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'B2B_BODY_HASH_MISMATCH');

        $this->assertSame(0, DB::table('b2b_operator_audit_events')->where('event_type', 'api_key.used')->count());
    }

    public function testIpOutsideAllowlistIsRejected()
    {
        $headers = $this->signedHeaders('GET', '/api/b2b/v1/operator/me', '', 'nonce-ip');

        $this->signedB2BRequest('GET', '/api/b2b/v1/operator/me', '', $headers, '10.10.10.10')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'B2B_AUTH_FAILED');
    }

    private function signedHeaders($method, $path, $body, $nonce)
    {
        return $this->signedB2BHeaders($this->operatorUid, $this->keyId, $this->secret, $method, $path, $body, $nonce);
    }
}
