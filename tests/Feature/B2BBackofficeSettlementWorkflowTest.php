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

class B2BBackofficeSettlementWorkflowTest extends TestCase
{
    use B2BApiTestHelpers;

    private $operator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\VanguardLTE\Http\Middleware\Checker::class);
        $this->withoutMiddleware(\PragmaRX\Google2FALaravel\Middleware::class);
        Cache::flush();
        $this->resetB2BTables();
        $this->createLegacyBackendTables();
        $this->operator = $this->createB2BOperator('op_web_settlement', 'key_web_settlement', 'web_settlement_secret_1234567890');
    }

    public function testSettlementWorkflowScreenListsSettlementCases()
    {
        $this->insertSettlement('stl_web_screen', 'exported');

        $this->actingAs($this->adminUser())
            ->get('/backend/b2b/settlements')
            ->assertStatus(200)
            ->assertSee('B2B Settlements')
            ->assertSee('stl_web_screen');
    }

    public function testSettlementSubmitRequiresWebStepUp()
    {
        $this->insertSettlement('stl_web_submit_step_up', 'exported');

        $response = $this->actingAs($this->adminUser())
            ->withSession(['_token' => 'test-token'])
            ->post('/backend/b2b/settlements/submit', [
                '_token' => 'test-token',
                'settlement_uid' => 'stl_web_submit_step_up',
                'reason' => 'Monthly settlement close.',
            ]);

        $this->assertStringContainsString('/backend/b2b/step-up/settlement.submit', $response->headers->get('Location'));
        $this->assertSame('exported', DB::table('b2b_settlements')->where('settlement_uid', 'stl_web_submit_step_up')->value('status'));
        $this->assertSame(0, DB::table('b2b_operator_audit_events')->where('event_type', 'settlement.submitted')->count());
    }

    public function testSettlementSubmitAppliesWithFreshWebStepUp()
    {
        $admin = $this->adminUser();
        $guard = app(B2BWebStepUpGuard::class);
        $this->insertSettlement('stl_web_submit_apply', 'exported');

        $response = $this->actingAs($admin)
            ->withSession([
                '_token' => 'test-token',
                $guard->sessionKey('settlement.submit') => [
                    'user_id' => (string) $admin->getAuthIdentifier(),
                    'verified_at' => time(),
                ],
            ])
            ->post('/backend/b2b/settlements/submit', [
                '_token' => 'test-token',
                'settlement_uid' => 'stl_web_submit_apply',
                'reason' => 'Monthly settlement close.',
            ]);

        $response->assertRedirect(route('backend.b2b.settlements.index'));
        $response->assertSessionMissing($guard->sessionKey('settlement.submit'));
        $this->assertSame('submitted', DB::table('b2b_settlements')->where('settlement_uid', 'stl_web_submit_apply')->value('status'));
        $this->assertSame('web:b2b_finance', DB::table('b2b_settlements')->where('settlement_uid', 'stl_web_submit_apply')->value('submitted_by'));

        $metadata = $this->auditMetadata('settlement.submitted', 'stl_web_submit_apply');
        $this->assertSame('b2b.settlements.submit', $metadata['permission']);
        $this->assertTrue($metadata['step_up']);
        $this->assertSame('web_backoffice', $metadata['source']);
    }

    public function testSettlementApproveAppliesWithFreshWebStepUp()
    {
        $admin = $this->adminUser();
        $guard = app(B2BWebStepUpGuard::class);
        $this->insertSettlement('stl_web_approve_apply', 'submitted', [
            'submitted_by' => 'web:b2b_finance',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->withSession([
                '_token' => 'test-token',
                $guard->sessionKey('settlement.approve') => [
                    'user_id' => (string) $admin->getAuthIdentifier(),
                    'verified_at' => time(),
                ],
            ])
            ->post('/backend/b2b/settlements/approve', [
                '_token' => 'test-token',
                'settlement_uid' => 'stl_web_approve_apply',
                'reason' => 'Settlement totals match finance reconciliation.',
            ]);

        $response->assertRedirect(route('backend.b2b.settlements.index'));
        $response->assertSessionMissing($guard->sessionKey('settlement.approve'));
        $this->assertSame('approved', DB::table('b2b_settlements')->where('settlement_uid', 'stl_web_approve_apply')->value('status'));
        $this->assertSame('web:b2b_finance', DB::table('b2b_settlements')->where('settlement_uid', 'stl_web_approve_apply')->value('approved_by'));

        $metadata = $this->auditMetadata('settlement.approved', 'stl_web_approve_apply');
        $this->assertSame('b2b.settlements.approve', $metadata['permission']);
        $this->assertTrue($metadata['step_up']);
        $this->assertSame('web_backoffice', $metadata['source']);
    }

    public function testSettlementRejectAppliesWithFreshWebStepUp()
    {
        $admin = $this->adminUser();
        $guard = app(B2BWebStepUpGuard::class);
        $this->insertSettlement('stl_web_reject_apply', 'submitted', [
            'submitted_by' => 'web:b2b_finance',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->withSession([
                '_token' => 'test-token',
                $guard->sessionKey('settlement.reject') => [
                    'user_id' => (string) $admin->getAuthIdentifier(),
                    'verified_at' => time(),
                ],
            ])
            ->post('/backend/b2b/settlements/reject', [
                '_token' => 'test-token',
                'settlement_uid' => 'stl_web_reject_apply',
                'reason' => 'Settlement mismatch requires operator correction.',
            ]);

        $response->assertRedirect(route('backend.b2b.settlements.index'));
        $response->assertSessionMissing($guard->sessionKey('settlement.reject'));
        $this->assertSame('rejected', DB::table('b2b_settlements')->where('settlement_uid', 'stl_web_reject_apply')->value('status'));
        $this->assertSame('web:b2b_finance', DB::table('b2b_settlements')->where('settlement_uid', 'stl_web_reject_apply')->value('rejected_by'));

        $metadata = $this->auditMetadata('settlement.rejected', 'stl_web_reject_apply');
        $this->assertSame('b2b.settlements.approve', $metadata['permission']);
        $this->assertTrue($metadata['step_up']);
        $this->assertSame('web_backoffice', $metadata['source']);
    }

    private function insertSettlement($settlementUid, $status, array $overrides = [])
    {
        $row = array_merge([
            'settlement_uid' => $settlementUid,
            'operator_id' => $this->operator->id,
            'period_start' => now()->subDays(7),
            'period_end' => now(),
            'currency' => 'USD',
            'bets_amount' => '100.00000000',
            'wins_amount' => '40.00000000',
            'refunds_amount' => '5.00000000',
            'ggr_amount' => '55.00000000',
            'aggregator_fee_amount' => '0.00000000',
            'provider_fee_amount' => '0.00000000',
            'net_amount' => '55.00000000',
            'status' => $status,
            'export_format' => 'csv',
            'export_hash' => hash('sha256', $settlementUid),
            'metadata' => json_encode(['totals' => ['net' => '55.00000000']]),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);

        return DB::table('b2b_settlements')->insertGetId($row);
    }

    private function auditMetadata($eventType, $settlementUid)
    {
        $event = DB::table('b2b_operator_audit_events')
            ->where('event_type', $eventType)
            ->where('subject_id', $settlementUid)
            ->first();

        $this->assertNotNull($event);

        return json_decode($event->metadata, true);
    }

    private function adminUser()
    {
        return new class extends AuthenticatableUser {
            public $id = 778;
            public $role_id = 6;
            public $shop_id = 0;
            public $shop = null;
            public $username = 'b2b_finance';
            public $email = 'b2b_finance@example.test';

            public function hasPermission($permission, $allRequired = true)
            {
                $allowedPermissions = [
                    'access.admin.panel',
                    'b2b.reports.view',
                    'b2b.settlements.submit',
                    'b2b.settlements.approve',
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
