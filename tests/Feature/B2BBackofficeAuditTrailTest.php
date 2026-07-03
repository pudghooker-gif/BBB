<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\B2BApiTestHelpers;
use Tests\TestCase;

class B2BBackofficeAuditTrailTest extends TestCase
{
    use B2BApiTestHelpers;

    private $operatorA;
    private $operatorB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\VanguardLTE\Http\Middleware\Checker::class);
        $this->withoutMiddleware(\VanguardLTE\Http\Middleware\Google2FAMiddleware::class);
        Cache::flush();
        $this->resetB2BTables();
        $this->createLegacyBackendTables();

        $this->operatorA = $this->createB2BOperator('op_audit_a', 'key_audit_a', 'audit_secret_a_1234567890');
        $this->operatorB = $this->createB2BOperator('op_audit_b', 'key_audit_b', 'audit_secret_b_1234567890');
        $this->seedAuditEvents();
    }

    public function testAuditScreenListsRedactedEvents()
    {
        $this->actingAs($this->adminUser(true))
            ->get('/backend/b2b/audit')
            ->assertStatus(200)
            ->assertSee('B2B Audit Trail')
            ->assertSee('operator.updated')
            ->assertSee('op_audit_a')
            ->assertSee('web:b2b_auditor')
            ->assertSee('[REDACTED]')
            ->assertDontSee('audit-secret-value')
            ->assertDontSee('api-key-secret');
    }

    public function testAuditScreenFiltersByOperatorAndEvent()
    {
        $this->actingAs($this->adminUser(true))
            ->get('/backend/b2b/audit?operator_uid=op_audit_a&event_type=operator.updated')
            ->assertStatus(200)
            ->assertSee('operator.updated')
            ->assertSee('op_audit_a')
            ->assertDontSee('settlement.approved')
            ->assertDontSee('op_audit_b');
    }

    public function testAuditScreenRequiresAuditPermission()
    {
        $this->actingAs($this->adminUser(false))
            ->get('/backend/b2b/audit')
            ->assertStatus(403);
    }

    private function seedAuditEvents()
    {
        DB::table('b2b_operator_audit_events')->insert([
            [
                'operator_id' => $this->operatorA->id,
                'event_type' => 'operator.updated',
                'subject_type' => 'operator',
                'subject_id' => 'op_audit_a',
                'actor' => 'web:b2b_auditor',
                'reason' => 'Changed callback after token=audit-secret-value rotation.',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'AuditTrailTest/1.0',
                'metadata' => json_encode([
                    'permission' => 'b2b.operators.update',
                    'token' => 'audit-secret-value',
                    'nested' => [
                        'api_key' => 'api-key-secret',
                    ],
                ]),
                'created_at' => now()->subMinute(),
                'updated_at' => now()->subMinute(),
            ],
            [
                'operator_id' => $this->operatorB->id,
                'event_type' => 'settlement.approved',
                'subject_type' => 'settlement',
                'subject_id' => 'settlement_audit_b',
                'actor' => 'web:b2b_finance',
                'reason' => 'Approved settlement.',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'AuditTrailTest/1.0',
                'metadata' => json_encode([
                    'permission' => 'b2b.settlements.approve',
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    private function adminUser($canViewAudit)
    {
        return new class($canViewAudit) extends AuthenticatableUser {
            private $canViewAudit;
            public $id = 782;
            public $role_id = 6;
            public $shop_id = 0;
            public $shop = null;
            public $username = 'b2b_auditor';
            public $email = 'b2b_auditor@example.test';

            public function __construct($canViewAudit = true)
            {
                parent::__construct();
                $this->canViewAudit = $canViewAudit;
            }

            public function hasPermission($permission, $allRequired = true)
            {
                $allowedPermissions = ['access.admin.panel'];
                if ($this->canViewAudit) {
                    $allowedPermissions[] = 'b2b.audit.view';
                }

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
