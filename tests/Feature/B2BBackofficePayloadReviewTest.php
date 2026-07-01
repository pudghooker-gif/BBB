<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\B2BApiTestHelpers;
use Tests\TestCase;
use VanguardLTE\B2B\Services\B2BWebStepUpGuard;

class B2BBackofficePayloadReviewTest extends TestCase
{
    use B2BApiTestHelpers;

    private $operator;
    private $attemptId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\VanguardLTE\Http\Middleware\Checker::class);
        $this->withoutMiddleware(\PragmaRX\Google2FALaravel\Middleware::class);
        Cache::flush();
        $this->resetB2BTables();
        $this->createLegacyBackendTables();

        $this->operator = $this->createB2BOperator('op_payload_review', 'key_payload_review', 'payload_review_secret_1234567890');
        $this->attemptId = $this->createWalletAttempt();
    }

    public function testPayloadScreenListsRedactedAttempts()
    {
        $this->actingAs($this->adminUser())
            ->get('/backend/b2b/payloads')
            ->assertStatus(200)
            ->assertSee('B2B Payload Review')
            ->assertSee('tx_payload_review')
            ->assertSee('[REDACTED]')
            ->assertDontSee('raw-request-secret')
            ->assertDontSee('raw-response-secret');
    }

    public function testRawPayloadRequiresWebStepUp()
    {
        $response = $this->actingAs($this->adminUser())
            ->withSession(['_token' => 'test-token'])
            ->post('/backend/b2b/payloads/raw', [
                '_token' => 'test-token',
                'attempt_id' => $this->attemptId,
                'reason' => 'Investigating operator wallet timeout.',
            ]);

        $this->assertStringContainsString('/backend/b2b/step-up/payload.view_raw', $response->headers->get('Location'));
        $this->assertSame(0, DB::table('b2b_operator_audit_events')->where('event_type', 'payload.raw_viewed')->count());
    }

    public function testRawPayloadDisplaysWithFreshWebStepUpAndAudits()
    {
        $admin = $this->adminUser();
        $guard = app(B2BWebStepUpGuard::class);

        $response = $this->actingAs($admin)
            ->withHeaders(['User-Agent' => 'PayloadReviewTest/1.0'])
            ->withSession([
                '_token' => 'test-token',
                $guard->sessionKey('payload.view_raw') => [
                    'user_id' => (string) $admin->getAuthIdentifier(),
                    'verified_at' => time(),
                ],
            ])
            ->post('/backend/b2b/payloads/raw', [
                '_token' => 'test-token',
                'attempt_id' => $this->attemptId,
                'reason' => 'Investigating operator wallet timeout.',
            ]);

        $response->assertStatus(200);
        $response->assertSee('Raw Attempt #' . $this->attemptId);
        $response->assertSee('raw-request-secret');
        $response->assertSee('raw-response-secret');
        $response->assertSessionMissing($guard->sessionKey('payload.view_raw'));

        $event = DB::table('b2b_operator_audit_events')
            ->where('event_type', 'payload.raw_viewed')
            ->where('subject_id', (string) $this->attemptId)
            ->first();

        $this->assertNotNull($event);
        $this->assertSame($this->operator->id, (int) $event->operator_id);
        $this->assertSame('web:b2b_payload_ops', $event->actor);
        $this->assertSame('Investigating operator wallet timeout.', $event->reason);
        $this->assertNotEmpty($event->ip_address);
        $this->assertNotEmpty($event->user_agent);

        $metadata = json_decode($event->metadata, true);
        $this->assertSame('tx_payload_review', $metadata['transaction_uid']);
        $this->assertSame('bet', $metadata['type']);
        $this->assertSame('b2b.payloads.view_raw', $metadata['permission']);
        $this->assertTrue($metadata['step_up']);
        $this->assertSame('web_backoffice', $metadata['source']);
    }

    private function createWalletAttempt()
    {
        $transactionId = DB::table('b2b_wallet_transactions')->insertGetId([
            'operator_id' => $this->operator->id,
            'transaction_uid' => 'tx_payload_review',
            'transaction_id' => 'operator_tx_payload_review',
            'type' => 'bet',
            'amount' => '10.00000000',
            'currency' => 'USD',
            'status' => 'pending',
            'raw_request' => json_encode(['player_id' => 'player_payload']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('b2b_wallet_transaction_attempts')->insertGetId([
            'wallet_transaction_id' => $transactionId,
            'operator_id' => $this->operator->id,
            'transaction_uid' => 'tx_payload_review',
            'type' => 'bet',
            'attempt_no' => 1,
            'url' => 'https://operator.example.test/wallet',
            'timeout_ms' => 5000,
            'http_status' => 504,
            'result' => 'timeout',
            'duration_ms' => 5001,
            'request_body' => json_encode([
                'access_token' => 'raw-request-secret',
                'player_id' => 'player_payload',
            ]),
            'response_body' => json_encode([
                'api_key' => 'raw-response-secret',
                'status' => 'timeout',
            ]),
            'error' => 'timeout',
            'started_at' => now(),
            'finished_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function adminUser()
    {
        return new class extends AuthenticatableUser {
            public $id = 781;
            public $role_id = 6;
            public $shop_id = 0;
            public $shop = null;
            public $username = 'b2b_payload_ops';
            public $email = 'b2b_payload_ops@example.test';

            public function hasPermission($permission, $allRequired = true)
            {
                $allowedPermissions = [
                    'access.admin.panel',
                    'b2b.payloads.view_redacted',
                    'b2b.payloads.view_raw',
                ];

                if (is_array($permission)) {
                    $matches = array_intersect($permission, $allowedPermissions);

                    return $allRequired
                        ? count($matches) === count($permission)
                        : count($matches) > 0;
                }

                return in_array($permission, $allowedPermissions, true);
            }

            public function hasRole($role)
            {
                if (is_array($role)) {
                    return in_array('admin', $role, true);
                }

                return $role === 'admin';
            }

            public function present()
            {
                return (object) [
                    'id' => $this->id,
                    'shop_id' => $this->shop_id,
                    'role_id' => $this->role_id,
                    'balance' => '0.00',
                    'count_balance' => '0.00',
                    'shop' => $this->shop,
                    'username' => $this->username,
                ];
            }

            public function shops_array()
            {
                return [];
            }

            public function getAuthIdentifier()
            {
                return (string) $this->id;
            }
        };
    }

    private function createLegacyBackendTables()
    {
        Schema::dropIfExists('jpg');
        Schema::dropIfExists('open_shift');
        Schema::dropIfExists('shops');

        Schema::create('shops', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->decimal('percent', 8, 2)->default(0);
            $table->boolean('is_blocked')->default(false);
            $table->timestamps();
        });

        Schema::create('jpg', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('shop_id')->nullable();
            $table->decimal('percent', 8, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('open_shift', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('shop_id')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->timestamps();
        });
    }
}
