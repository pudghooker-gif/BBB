<?php

namespace Tests\Unit;

use Tests\TestCase;
use VanguardLTE\B2B\Services\B2BPayloadRedactor;

class B2BPayloadRedactorTest extends TestCase
{
    public function testRedactsSensitiveKeysRecursively()
    {
        $redactor = new B2BPayloadRedactor();

        $payload = $redactor->redact([
            'player_id' => 'player_1',
            'amount' => '10.00000000',
            'metadata' => [
                'access_token' => 'token-secret',
                'nested' => [
                    'password' => 'player-password',
                    'safe_note' => 'kept',
                ],
            ],
            'X-B2B-Signature' => 'signature-secret',
        ]);

        $this->assertSame('player_1', $payload['player_id']);
        $this->assertSame('10.00000000', $payload['amount']);
        $this->assertSame(B2BPayloadRedactor::REDACTED, $payload['metadata']['access_token']);
        $this->assertSame(B2BPayloadRedactor::REDACTED, $payload['metadata']['nested']['password']);
        $this->assertSame('kept', $payload['metadata']['nested']['safe_note']);
        $this->assertSame(B2BPayloadRedactor::REDACTED, $payload['X-B2B-Signature']);
    }

    public function testStorageValueRedactsJsonAndKnownTextPatterns()
    {
        $redactor = new B2BPayloadRedactor();

        $json = $redactor->storageValue('{"status":"ok","api_key":"secret-key","balance":"100.00000000"}');
        $decoded = json_decode($json, true);

        $this->assertSame('ok', $decoded['status']);
        $this->assertSame(B2BPayloadRedactor::REDACTED, $decoded['api_key']);
        $this->assertSame('100.00000000', $decoded['balance']);

        $text = $redactor->storageValue('authorization: Bearer abc.def.ghi');
        $this->assertStringContainsString(B2BPayloadRedactor::REDACTED, $text);
        $this->assertStringNotContainsString('abc.def.ghi', $text);
    }

    public function testRedactsKnownTextPatternsInsideJsonValues()
    {
        $redactor = new B2BPayloadRedactor();

        $json = $redactor->storageValue(json_encode([
            'status' => 'failed',
            'error' => 'callback failed token=legacy-secret-value',
            'nested' => [
                'message' => 'authorization: Bearer abc.def.ghi',
            ],
        ]));
        $decoded = json_decode($json, true);

        $this->assertStringContainsString(B2BPayloadRedactor::REDACTED, $decoded['error']);
        $this->assertStringContainsString(B2BPayloadRedactor::REDACTED, $decoded['nested']['message']);
        $this->assertStringNotContainsString('legacy-secret-value', $json);
        $this->assertStringNotContainsString('abc.def.ghi', $json);
    }
}
