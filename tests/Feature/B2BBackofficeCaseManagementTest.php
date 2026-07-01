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

class B2BBackofficeCaseManagementTest extends TestCase
{
    use B2BApiTestHelpers;

    private $operator;
    private $transactionId;
    private $caseId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\VanguardLTE\Http\Middleware\Checker::class);
        $this->withoutMiddleware(\PragmaRX\Google2FALaravel\Middleware::class);
        Cache::flush();
        $this->resetB2BTables();
        $this->createLegacyBackendTables();

        $this->operator = $this->createB2BOperator('op_case_review', 'key_case_review', 'case_review_secret_1234567890');
        $this->transactionId = $this->insertWalletTransaction('tx_case_review', 'manual_review');
        $this->caseId = $this->insertCase('open');
    }

    public function testCaseManagementScreenListsRedactedCases()
    {
        $this->actingAs($this->adminUser())
            ->get('/backend/b2b/cases')
            ->assertStatus(200)
            ->assertSee('B2B Case Management')
            ->assertSee('tx_case_review')
            ->assertSee('[REDACTED]')
            ->assertDontSee('case-secret-token');
    }

    public function testCaseClaimRequiresWebStepUp()
    {
        $response = $this->actingAs($this->adminUser())
            ->withSession(['_token' => 'test-token'])
            ->post('/backend/b2b/cases/claim', [
                '_token' => 'test-token',
                'case_id' => $this->caseId,
                'reason' => 'Taking ownership of provider case CASE-1.',
            ]);

        $this->assertStringContainsString('/backend/b2b/step-up/case.claim', $response->headers->get('Location'));
        $this->assertSame('open', DB::table('b2b_wallet_reconciliation_items')->where('id', $this->caseId)->value('state'));
        $this->assertSame(0, DB::table('b2b_operator_audit_events')->where('event_type', 'case.claimed')->count());
    }

    public function testCaseClaimResolveAndReopenApplyWithFreshWebStepUp()
    {
        $admin = $this->adminUser();
        $guard = app(B2BWebStepUpGuard::class);

        $claim = $this->actingAs($admin)
            ->withHeaders(['User-Agent' => 'CaseManagementTest/1.0'])
            ->withSession([
                '_token' => 'test-token',
                $guard->sessionKey('case.claim') => [
                    'user_id' => (string) $admin->getAuthIdentifier(),
                    'verified_at' => time(),
                ],
            ])
            ->post('/backend/b2b/cases/claim', [
                '_token' => 'test-token',
                'case_id' => $this->caseId,
                'reason' => 'Taking ownership of provider case CASE-2.',
            ]);

        $claim->assertRedirect(route('backend.b2b.cases.index'));
        $claim->assertSessionMissing($guard->sessionKey('case.claim'));
        $this->assertSame('in_progress', DB::table('b2b_wallet_reconciliation_items')->where('id', $this->caseId)->value('state'));

        $claimMetadata = $this->auditMetadata('case.claimed');
        $this->assertSame('claim', $claimMetadata['case_action']);
        $this->assertSame('open', $claimMetadata['previous_state']);
        $this->assertSame('in_progress', $claimMetadata['new_state']);
        $this->assertSame('b2b.cases.manage', $claimMetadata['permission']);
        $this->assertTrue($claimMetadata['step_up']);

        $context = $this->caseContext();
        $this->assertSame('web:b2b_case_ops', $context['case_assignment']['assigned_to']);

        $resolve = $this->actingAs($admin)
            ->withSession([
                '_token' => 'test-token',
                $guard->sessionKey('case.resolve') => [
                    'user_id' => (string) $admin->getAuthIdentifier(),
                    'verified_at' => time(),
                ],
            ])
            ->post('/backend/b2b/cases/resolve', [
                '_token' => 'test-token',
                'case_id' => $this->caseId,
                'reason' => 'Provider confirmed the case can be closed.',
            ]);

        $resolve->assertRedirect(route('backend.b2b.cases.index'));
        $resolve->assertSessionMissing($guard->sessionKey('case.resolve'));

        $resolvedCase = DB::table('b2b_wallet_reconciliation_items')->where('id', $this->caseId)->first();
        $this->assertSame('resolved', $resolvedCase->state);
        $this->assertNotNull($resolvedCase->resolved_at);

        $resolveMetadata = $this->auditMetadata('case.resolved');
        $this->assertSame('resolve', $resolveMetadata['case_action']);
        $this->assertSame('in_progress', $resolveMetadata['previous_state']);
        $this->assertSame('resolved', $resolveMetadata['new_state']);

        $reopen = $this->actingAs($admin)
            ->withSession([
                '_token' => 'test-token',
                $guard->sessionKey('case.reopen') => [
                    'user_id' => (string) $admin->getAuthIdentifier(),
                    'verified_at' => time(),
                ],
            ])
            ->post('/backend/b2b/cases/reopen', [
                '_token' => 'test-token',
                'case_id' => $this->caseId,
                'reason' => 'Operator reopened the dispute with new evidence.',
            ]);

        $reopen->assertRedirect(route('backend.b2b.cases.index'));
        $reopen->assertSessionMissing($guard->sessionKey('case.reopen'));

        $reopenedCase = DB::table('b2b_wallet_reconciliation_items')->where('id', $this->caseId)->first();
        $this->assertSame('open', $reopenedCase->state);
        $this->assertNull($reopenedCase->resolved_at);

        $reopenMetadata = $this->auditMetadata('case.reopened');
        $this->assertSame('reopen', $reopenMetadata['case_action']);
        $this->assertSame('resolved', $reopenMetadata['previous_state']);
        $this->assertSame('open', $reopenMetadata['new_state']);
    }

    private function insertWalletTransaction($transactionUid, $status)
    {
        return DB::table('b2b_wallet_transactions')->insertGetId([
            'operator_id' => $this->operator->id,
            'session_id' => 'sess_case_review',
            'game_uid' => 'book_case_review',
            'round_id' => 'round_case_review',
            'transaction_uid' => $transactionUid,
            'transaction_id' => $transactionUid,
            'idempotency_key' => sha1($transactionUid),
            'type' => 'bet',
            'amount' => '12.00000000',
            'currency' => 'USD',
            'status' => $status,
            'attempts' => 3,
            'last_error' => 'Provider case requires follow-up.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertCase($state)
    {
        return DB::table('b2b_wallet_reconciliation_items')->insertGetId([
            'wallet_transaction_id' => $this->transactionId,
            'operator_id' => $this->operator->id,
            'transaction_uid' => 'tx_case_review',
            'status' => 'manual_review',
            'reason' => 'manual_review',
            'priority' => 'medium',
            'state' => $state,
            'context' => json_encode([
                'provider_case' => 'CASE-1',
                'token' => 'case-secret-token',
                'note' => 'Needs operator response.',
            ]),
            'detected_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function auditMetadata($eventType)
    {
        $event = DB::table('b2b_operator_audit_events')
            ->where('event_type', $eventType)
            ->where('subject_id', (string) $this->caseId)
            ->orderBy('id', 'desc')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame($this->operator->id, (int) $event->operator_id);
        $this->assertSame('web:b2b_case_ops', $event->actor);

        return json_decode($event->metadata, true);
    }

    private function caseContext()
    {
        return json_decode(DB::table('b2b_wallet_reconciliation_items')->where('id', $this->caseId)->value('context'), true);
    }

    private function adminUser()
    {
        return new class extends AuthenticatableUser {
            public $id = 782;
            public $role_id = 6;
            public $shop_id = 0;
            public $shop = null;
            public $username = 'b2b_case_ops';
            public $email = 'b2b_case_ops@example.test';

            public function hasPermission($permission, $allRequired = true)
            {
                $allowedPermissions = [
                    'access.admin.panel',
                    'b2b.cases.view',
                    'b2b.cases.manage',
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
