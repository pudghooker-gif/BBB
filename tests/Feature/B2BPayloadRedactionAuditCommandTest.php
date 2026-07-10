<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\B2BApiTestHelpers;
use Tests\TestCase;

class B2BPayloadRedactionAuditCommandTest extends TestCase
{
    use B2BApiTestHelpers;

    private $operator;
    private $secret = 'legacy-secret-value';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->resetB2BTables();
        $this->operator = $this->createB2BOperator('op_payload_audit', 'key_payload_audit', 'payload_audit_secret_1234567890');
        $this->seedLegacyPayloads();
    }

    public function testDryRunFindsLegacyPayloadsWithoutPrintingSecrets()
    {
        $artifact = storage_path('framework/testing/payload-redaction-dry-run.json');
        if (file_exists($artifact)) {
            unlink($artifact);
        }

        $exitCode = Artisan::call('b2b:payload-redaction-audit', [
            '--artifact' => $artifact,
        ]);

        $output = Artisan::output();
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('findings:', $output);
        $this->assertStringNotContainsString($this->secret, $output);
        $this->assertFileExists($artifact);

        $artifactBody = file_get_contents($artifact);
        $this->assertStringNotContainsString($this->secret, $artifactBody);
        $report = json_decode($artifactBody, true);
        $this->assertGreaterThan(0, $report['findings']);
        $this->assertSame(0, $report['updated_fields']);

        $this->assertDatabaseStillContainsSecret();
    }

    public function testWriteRedactsLegacyPayloadsAndCleanDryRunPasses()
    {
        $writeExitCode = Artisan::call('b2b:payload-redaction-audit', [
            '--write' => true,
        ]);

        $this->assertSame(0, $writeExitCode);
        $this->assertDatabaseNoLongerContainsSecret();

        $dryRunExitCode = Artisan::call('b2b:payload-redaction-audit');

        $this->assertSame(0, $dryRunExitCode);
        $this->assertStringContainsString('findings: 0', Artisan::output());
    }

    private function seedLegacyPayloads()
    {
        $transactionId = DB::table('b2b_wallet_transactions')->insertGetId([
            'operator_id' => $this->operator->id,
            'transaction_uid' => 'tx_payload_audit',
            'transaction_id' => 'operator_tx_payload_audit',
            'type' => 'bet',
            'amount' => '10.00000000',
            'currency' => 'USD',
            'status' => 'failed',
            'raw_request' => json_encode([
                'player_id' => 'player_payload_audit',
                'access_token' => $this->secret,
            ]),
            'raw_response' => json_encode([
                'status' => 'failed',
                'message' => 'wallet failed token=' . $this->secret,
            ]),
            'operator_response_body' => json_encode([
                'api_key' => $this->secret,
            ]),
            'last_error' => 'callback signature=' . $this->secret,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('b2b_wallet_callback_logs')->insert([
            'operator_id' => $this->operator->id,
            'wallet_transaction_id' => $transactionId,
            'direction' => 'outbound',
            'endpoint' => 'https://wallet.example.test/callback?token=' . $this->secret,
            'http_status' => 500,
            'request_body' => json_encode(['password' => $this->secret]),
            'response_body' => json_encode(['message' => 'authorization: Bearer ' . $this->secret]),
            'error_message' => 'response token=' . $this->secret,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('b2b_wallet_transaction_attempts')->insert([
            'wallet_transaction_id' => $transactionId,
            'operator_id' => $this->operator->id,
            'transaction_uid' => 'tx_payload_audit',
            'type' => 'bet',
            'attempt_no' => 1,
            'url' => 'https://wallet.example.test/callback?signature=' . $this->secret,
            'timeout_ms' => 5000,
            'http_status' => 500,
            'result' => 'failed',
            'request_body' => json_encode(['token' => $this->secret]),
            'response_body' => json_encode(['error' => 'password=' . $this->secret]),
            'error' => 'attempt token=' . $this->secret,
            'started_at' => now(),
            'finished_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('b2b_provider_requests')->insert([
            'operator_id' => $this->operator->id,
            'provider' => 'goldsvet_internal',
            'game_uid' => 'book_payload_audit',
            'session_id' => 'sess_payload_audit',
            'request_uid' => 'provider_payload_audit',
            'action' => 'launch',
            'status' => 'failed',
            'request_payload' => json_encode(['private_key' => $this->secret]),
            'response_payload' => json_encode(['message' => 'secret=' . $this->secret]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assertDatabaseStillContainsSecret()
    {
        $this->assertStringContainsString($this->secret, $this->payloadSnapshot());
    }

    private function assertDatabaseNoLongerContainsSecret()
    {
        $payloads = $this->payloadSnapshot();

        $this->assertStringNotContainsString($this->secret, $payloads);
        $this->assertStringContainsString('[REDACTED]', $payloads);
    }

    private function payloadSnapshot()
    {
        $values = [];

        foreach ([
            'b2b_wallet_transactions' => ['raw_request', 'raw_response', 'operator_response_body', 'last_error'],
            'b2b_wallet_callback_logs' => ['endpoint', 'request_body', 'response_body', 'error_message'],
            'b2b_wallet_transaction_attempts' => ['url', 'request_body', 'response_body', 'error'],
            'b2b_provider_requests' => ['request_payload', 'response_payload'],
        ] as $table => $columns) {
            $row = DB::table($table)->orderBy('id')->first();
            foreach ($columns as $column) {
                $values[] = isset($row->{$column}) ? (string) $row->{$column} : '';
            }
        }

        return implode("\n", $values);
    }
}
