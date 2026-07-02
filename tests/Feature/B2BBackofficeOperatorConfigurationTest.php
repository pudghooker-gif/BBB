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

class B2BBackofficeOperatorConfigurationTest extends TestCase
{
    use B2BApiTestHelpers;

    private $operator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\VanguardLTE\Http\Middleware\Checker::class);
        $this->withoutMiddleware(\VanguardLTE\Http\Middleware\Google2FAMiddleware::class);
        Cache::flush();
        $this->resetB2BTables();
        $this->createLegacyBackendTables();
        $this->operator = $this->createB2BOperator('op_web_operator', 'key_web_operator', 'web_operator_secret_1234567890');
    }

    public function testOperatorConfigurationScreenListsOperators()
    {
        $this->actingAs($this->adminUser())
            ->get('/backend/b2b/operators')
            ->assertStatus(200)
            ->assertSee('B2B Operators')
            ->assertSee('op_web_operator');
    }

    public function testOperatorUpdateRequiresWebStepUp()
    {
        $response = $this->actingAs($this->adminUser())
            ->withSession(['_token' => 'test-token'])
            ->post('/backend/b2b/operators/update', $this->operatorUpdatePayload([
                '_token' => 'test-token',
                'name' => 'Denied Operator Name',
            ]));

        $this->assertStringContainsString('/backend/b2b/step-up/operator.update', $response->headers->get('Location'));
        $this->assertSame($this->operator->name, DB::table('b2b_operators')->where('operator_uid', 'op_web_operator')->value('name'));
    }

    public function testOperatorUpdateAppliesWithFreshWebStepUp()
    {
        $admin = $this->adminUser();
        $guard = app(B2BWebStepUpGuard::class);

        $response = $this->actingAs($admin)
            ->withSession([
                '_token' => 'test-token',
                $guard->sessionKey('operator.update') => [
                    'user_id' => (string) $admin->getAuthIdentifier(),
                    'verified_at' => time(),
                ],
            ])
            ->post('/backend/b2b/operators/update', $this->operatorUpdatePayload([
                '_token' => 'test-token',
                'name' => 'Updated Operator',
                'base_url' => 'https://operator.example.test',
                'wallet_callback_url' => 'https://operator.example.test/wallet',
                'allowed_currencies' => 'usd, eur',
                'allowed_countries' => 'us ca',
                'ip_whitelist' => "192.0.2.10\n2001:db8::/32",
                'max_rps' => 77,
            ]));

        $response->assertRedirect(route('backend.b2b.operators.index'));
        $response->assertSessionMissing($guard->sessionKey('operator.update'));

        $row = DB::table('b2b_operators')->where('operator_uid', 'op_web_operator')->first();
        $this->assertSame('Updated Operator', $row->name);
        $this->assertSame('https://operator.example.test', $row->base_url);
        $this->assertSame('USD', $row->default_currency);
        $this->assertSame(['USD', 'EUR'], json_decode($row->allowed_currencies, true));
        $this->assertSame(['US', 'CA'], json_decode($row->allowed_countries, true));
        $this->assertSame(['192.0.2.10', '2001:db8::/32'], json_decode($row->ip_whitelist, true));
        $this->assertSame(77, (int) $row->max_rps);

        $metadata = $this->auditMetadata('operator.updated', 'op_web_operator');
        $this->assertSame('b2b.operators.update', $metadata['permission']);
        $this->assertTrue($metadata['step_up']);
        $this->assertSame('web_backoffice', $metadata['source']);
        $this->assertContains('name', $metadata['changed_fields']);
        $this->assertContains('ip_whitelist', $metadata['changed_fields']);
    }

    public function testOperatorSuspendAndResumeApplyWithFreshWebStepUp()
    {
        $admin = $this->adminUser();
        $guard = app(B2BWebStepUpGuard::class);

        $suspend = $this->actingAs($admin)
            ->withSession([
                '_token' => 'test-token',
                $guard->sessionKey('operator.suspend') => [
                    'user_id' => (string) $admin->getAuthIdentifier(),
                    'verified_at' => time(),
                ],
            ])
            ->post('/backend/b2b/operators/suspend', [
                '_token' => 'test-token',
                'operator_uid' => 'op_web_operator',
                'reason' => 'Incident response.',
            ]);

        $suspend->assertRedirect(route('backend.b2b.operators.index'));
        $suspend->assertSessionMissing($guard->sessionKey('operator.suspend'));
        $this->assertSame('suspended', DB::table('b2b_operators')->where('operator_uid', 'op_web_operator')->value('status'));

        $suspendedMetadata = $this->auditMetadata('operator.suspended', 'op_web_operator');
        $this->assertSame('b2b.operators.suspend', $suspendedMetadata['permission']);
        $this->assertTrue($suspendedMetadata['step_up']);
        $this->assertSame('web_backoffice', $suspendedMetadata['source']);

        $resume = $this->actingAs($admin)
            ->withSession([
                '_token' => 'test-token',
                $guard->sessionKey('operator.resume') => [
                    'user_id' => (string) $admin->getAuthIdentifier(),
                    'verified_at' => time(),
                ],
            ])
            ->post('/backend/b2b/operators/resume', [
                '_token' => 'test-token',
                'operator_uid' => 'op_web_operator',
                'reason' => 'Incident resolved.',
            ]);

        $resume->assertRedirect(route('backend.b2b.operators.index'));
        $resume->assertSessionMissing($guard->sessionKey('operator.resume'));
        $this->assertSame('active', DB::table('b2b_operators')->where('operator_uid', 'op_web_operator')->value('status'));

        $resumedMetadata = $this->auditMetadata('operator.resumed', 'op_web_operator');
        $this->assertSame('b2b.operators.suspend', $resumedMetadata['permission']);
        $this->assertTrue($resumedMetadata['step_up']);
        $this->assertSame('web_backoffice', $resumedMetadata['source']);
    }

    private function operatorUpdatePayload(array $overrides = [])
    {
        return array_merge([
            'operator_uid' => 'op_web_operator',
            'name' => 'Configured Operator',
            'shop_id' => 0,
            'base_url' => 'https://operator.example.test',
            'wallet_callback_url' => 'https://operator.example.test/wallet',
            'default_currency' => 'USD',
            'allowed_currencies' => 'USD',
            'allowed_countries' => 'US',
            'ip_whitelist' => '192.0.2.10',
            'max_rps' => 50,
            'wallet_timeout_ms' => 5000,
            'connect_timeout_ms' => 1500,
            'circuit_breaker_threshold' => 5,
            'circuit_breaker_cooldown_seconds' => 30,
            'reason' => 'Operator configuration change.',
        ], $overrides);
    }

    private function auditMetadata($eventType, $subjectId)
    {
        $event = DB::table('b2b_operator_audit_events')
            ->where('event_type', $eventType)
            ->where('subject_id', $subjectId)
            ->first();

        $this->assertNotNull($event);

        return json_decode($event->metadata, true);
    }

    private function adminUser()
    {
        return new class extends AuthenticatableUser {
            public $id = 780;
            public $role_id = 6;
            public $shop_id = 0;
            public $shop = null;
            public $username = 'b2b_ops';
            public $email = 'b2b_ops@example.test';

            public function hasPermission($permission, $allRequired = true)
            {
                $allowedPermissions = [
                    'access.admin.panel',
                    'b2b.operators.update',
                    'b2b.operators.suspend',
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
