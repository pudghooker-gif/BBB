<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
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

    public function testLogShippingCheckCommandWritesRedactedEvidenceArtifact()
    {
        $directory = storage_path('framework/testing/b2b-log-shipping');
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $logFile = $directory . DIRECTORY_SEPARATOR . 'b2b-log-shipping-test.log';
        $artifact = $directory . DIRECTORY_SEPARATOR . 'b2b-log-shipping-validation.log';
        @unlink($logFile);
        @unlink($artifact);

        config([
            'b2b.structured_logging_enabled' => true,
            'b2b.structured_log_channel' => 'b2b_log_shipping_test',
            'logging.channels.b2b_log_shipping_test' => [
                'driver' => 'single',
                'path' => $logFile,
                'level' => 'debug',
                'tap' => [\VanguardLTE\Logging\B2BJsonFormatter::class],
            ],
        ]);
        Log::forgetChannel('b2b_log_shipping_test');

        $exitCode = Artisan::call('b2b:log-shipping-check', [
            '--artifact' => $artifact,
            '--marker' => 'log-shipping-test-marker',
            '--log-file' => $logFile,
        ]);

        $this->assertSame(0, $exitCode, Artisan::output());
        $this->assertFileExists($artifact);

        $payload = json_decode(file_get_contents($artifact), true);
        $this->assertSame('passed', $payload['status']);
        $this->assertSame('b2b_log_shipping_test', $payload['channel']);
        $this->assertSame('log-shipping-test-marker', $payload['marker']);
        $this->assertTrue($payload['event_found']);
        $this->assertTrue($payload['json_parsed']);
        $this->assertTrue($payload['redaction_verified']);

        $logContents = file_get_contents($logFile);
        $this->assertStringContainsString('observability.log_shipping_check', $logContents);
        $this->assertStringContainsString('log-shipping-test-marker', $logContents);
        $this->assertStringContainsString(B2BPayloadRedactor::REDACTED, $logContents);
        $this->assertStringNotContainsString('log-shipping-secret-probe', $logContents);
        $this->assertStringNotContainsString('Bearer log.shipping.secret', $logContents);
    }
}
