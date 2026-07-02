<?php

namespace Tests\Unit;

use Tests\TestCase;
use VanguardLTE\B2B\Services\B2BPayloadRedactor;
use VanguardLTE\B2B\Services\B2BStructuredEventLogger;

class B2BStructuredEventLoggerTest extends TestCase
{
    public function testPayloadIsStructuredAndRedacted()
    {
        $logger = new B2BStructuredEventLogger(new B2BPayloadRedactor());

        $payload = $logger->payload('warning', 'api.auth_failed', [
            'request_id' => 'req-1',
            'operator_uid' => 'operator-a',
            'metadata' => [
                'token' => 'secret-token',
                'note' => 'authorization: Bearer abc.def.ghi',
            ],
        ]);

        $this->assertSame('b2b', $payload['component']);
        $this->assertSame('api.auth_failed', $payload['event']);
        $this->assertSame('warning', $payload['level']);
        $this->assertSame('req-1', $payload['request_id']);
        $this->assertSame('operator-a', $payload['operator_uid']);
        $this->assertSame(B2BPayloadRedactor::REDACTED, $payload['metadata']['token']);
        $this->assertStringContainsString(B2BPayloadRedactor::REDACTED, $payload['metadata']['note']);
        $this->assertStringNotContainsString('abc.def.ghi', $payload['metadata']['note']);
    }

    public function testPayloadNormalizesInvalidLevelAndLimitsLongStrings()
    {
        $logger = new B2BStructuredEventLogger(new B2BPayloadRedactor());

        $payload = $logger->payload('invalid', 'audit.event', [
            'message' => str_repeat('a', 2100),
        ]);

        $this->assertSame('info', $payload['level']);
        $this->assertSame(2003, strlen($payload['message']));
        $this->assertStringEndsWith('...', $payload['message']);
    }
}
