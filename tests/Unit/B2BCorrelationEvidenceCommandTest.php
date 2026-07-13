<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class B2BCorrelationEvidenceCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetTables();
        $this->createTables();
    }

    protected function tearDown(): void
    {
        $this->resetTables();
        parent::tearDown();
    }

    public function testCorrelationEvidenceCommandWritesRedactedArtifact()
    {
        $artifact = storage_path('framework/testing/b2b-correlation-validation.json');
        if (!is_dir(dirname($artifact))) {
            mkdir(dirname($artifact), 0777, true);
        }
        @unlink($artifact);

        DB::table('b2b_wallet_transaction_attempts')->insert([
            'operator_id' => 1,
            'transaction_uid' => 'tx-correlation-raw',
            'type' => 'bet',
            'attempt_no' => 1,
            'result' => 'success',
            'request_body' => json_encode([
                'amount' => '1.00000000',
                '_context' => [
                    'request_id' => 'req-correlation-raw',
                    'transaction_uid' => 'tx-correlation-raw',
                ],
                'token' => '[REDACTED]',
            ]),
            'response_body' => json_encode(['status' => 'ok']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('b2b_wallet_callback_logs')->insert([
            'operator_id' => 1,
            'direction' => 'outbound',
            'request_headers' => json_encode([
                'X-Request-Id' => 'req-callback-raw',
                'X-B2B-Transaction-Uid' => 'tx-callback-raw',
            ]),
            'request_body' => json_encode(['status' => 'success']),
            'response_body' => json_encode(['ok' => true]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('b2b_provider_requests')->insert([
            'operator_id' => 1,
            'provider' => 'goldsvet_internal',
            'game_uid' => 'game-correlation',
            'session_id' => 'session-correlation-raw',
            'request_uid' => 'provider-request-raw',
            'action' => 'prepare_launch',
            'status' => 'success',
            'request_payload' => json_encode(['session_uid' => 'session-correlation-raw']),
            'response_payload' => json_encode(['ok' => true, 'redirect_prepared' => true]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $exitCode = Artisan::call('b2b:correlation-evidence', [
            '--artifact' => $artifact,
            '--limit' => 10,
        ]);

        $this->assertSame(0, $exitCode, Artisan::output());
        $this->assertFileExists($artifact);

        $payload = json_decode(file_get_contents($artifact), true);
        $this->assertSame('passed', $payload['status']);
        $this->assertSame(1, $payload['wallet']['transaction_attempts_complete_context']);
        $this->assertSame(1, $payload['wallet']['callback_logs_complete_context']);
        $this->assertSame(1, $payload['provider']['requests_complete_context']);
        $this->assertSame(0, $payload['secret_scan']['leaks_found']);
        $this->assertNotEmpty($payload['sample_hashes']);

        $artifactContents = file_get_contents($artifact);
        $this->assertStringNotContainsString('req-correlation-raw', $artifactContents);
        $this->assertStringNotContainsString('tx-correlation-raw', $artifactContents);
        $this->assertStringNotContainsString('provider-request-raw', $artifactContents);
        $this->assertStringNotContainsString('session-correlation-raw', $artifactContents);
        $this->assertStringNotContainsString('log-shipping-secret-probe', $artifactContents);
        $this->assertStringNotContainsString('Bearer log.shipping.secret', $artifactContents);
    }

    private function createTables()
    {
        Schema::create('b2b_wallet_transaction_attempts', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('operator_id')->nullable();
            $table->string('transaction_uid')->nullable();
            $table->string('type')->nullable();
            $table->integer('attempt_no')->default(1);
            $table->string('result')->default('pending');
            $table->longText('request_body')->nullable();
            $table->longText('response_body')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });

        Schema::create('b2b_wallet_callback_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('operator_id')->nullable();
            $table->string('direction')->nullable();
            $table->json('request_headers')->nullable();
            $table->json('request_body')->nullable();
            $table->json('response_body')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('b2b_provider_requests', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('operator_id')->nullable();
            $table->string('provider')->nullable();
            $table->string('game_uid')->nullable();
            $table->string('session_id')->nullable();
            $table->string('request_uid')->nullable();
            $table->string('action')->nullable();
            $table->string('status')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    private function resetTables()
    {
        Schema::dropIfExists('b2b_provider_requests');
        Schema::dropIfExists('b2b_wallet_callback_logs');
        Schema::dropIfExists('b2b_wallet_transaction_attempts');
    }
}
