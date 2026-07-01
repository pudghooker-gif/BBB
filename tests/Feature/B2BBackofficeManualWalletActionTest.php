<?php

namespace Tests\Feature;

use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\B2BApiTestHelpers;
use Tests\TestCase;
use VanguardLTE\B2B\Services\B2BWebStepUpGuard;

class B2BBackofficeManualWalletActionTest extends TestCase
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
        $this->operator = $this->createB2BOperator('op_web_manual', 'key_web_manual', 'web_manual_secret_1234567890');
    }

    public function testManualWalletActionScreenListsCandidateTransactions()
    {
        $this->insertWalletTransaction('tx_web_manual_screen', 'manual_review');

        $this->actingAs($this->adminUser())
            ->get('/backend/b2b/wallet/manual-actions')
            ->assertStatus(200)
            ->assertSee('B2B Manual Wallet Actions')
            ->assertSee('tx_web_manual_screen');
    }

    public function testManualWalletActionPostRequiresWebStepUp()
    {
        $transactionId = $this->insertWalletTransaction('tx_web_manual_step_up', 'manual_review');

        $response = $this->actingAs($this->adminUser())
            ->withSession(['_token' => 'test-token'])
            ->post('/backend/b2b/wallet/manual-actions', [
                '_token' => 'test-token',
                'transaction_uid' => 'tx_web_manual_step_up',
                'operator_id' => $this->operator->id,
                'action' => 'resolve-success',
                'reason' => 'Provider case WEB-1 confirms success.',
            ]);

        $this->assertStringContainsString('/backend/b2b/step-up/wallet.manual_action', $response->headers->get('Location'));
        $this->assertSame('manual_review', DB::table('b2b_wallet_transactions')->where('id', $transactionId)->value('status'));
        $this->assertSame(0, DB::table('b2b_wallet_manual_actions')->count());
    }

    public function testManualWalletActionPostAppliesWithFreshWebStepUp()
    {
        $admin = $this->adminUser();
        $transactionId = $this->insertWalletTransaction('tx_web_manual_apply', 'manual_review');
        $guard = app(B2BWebStepUpGuard::class);

        $response = $this->actingAs($admin)
            ->withSession([
                '_token' => 'test-token',
                $guard->sessionKey('wallet.manual_action') => [
                    'user_id' => (string) $admin->getAuthIdentifier(),
                    'verified_at' => time(),
                ],
            ])
            ->post('/backend/b2b/wallet/manual-actions', [
                '_token' => 'test-token',
                'transaction_uid' => 'tx_web_manual_apply',
                'operator_id' => $this->operator->id,
                'action' => 'resolve-success',
                'reason' => 'Provider case WEB-2 confirms success.',
            ]);

        $response->assertRedirect(route('backend.b2b.wallet_manual_actions.index'));
        $response->assertSessionMissing($guard->sessionKey('wallet.manual_action'));

        $this->assertSame('success', DB::table('b2b_wallet_transactions')->where('id', $transactionId)->value('status'));
        $this->assertSame('resolve-success', DB::table('b2b_wallet_manual_actions')->where('wallet_transaction_id', $transactionId)->value('action'));

        $audit = DB::table('b2b_operator_audit_events')
            ->where('event_type', 'wallet.manual_action.applied')
            ->where('subject_id', 'tx_web_manual_apply')
            ->first();

        $this->assertNotNull($audit);
        $metadata = json_decode($audit->metadata, true);
        $this->assertSame('b2b.wallet.manual_action', $metadata['permission']);
        $this->assertTrue($metadata['step_up']);
        $this->assertSame('web_backoffice', $metadata['source']);
    }

    private function insertWalletTransaction($transactionUid, $status)
    {
        return DB::table('b2b_wallet_transactions')->insertGetId([
            'operator_id' => $this->operator->id,
            'session_id' => 'sess_web_manual',
            'game_uid' => 'book_web_manual',
            'round_id' => 'round_web_manual',
            'transaction_uid' => $transactionUid,
            'transaction_id' => $transactionUid,
            'idempotency_key' => sha1($transactionUid),
            'type' => 'bet',
            'amount' => '10.00000000',
            'currency' => 'USD',
            'status' => $status,
            'attempts' => 2,
            'last_error' => 'Provider case requires operator review.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function adminUser()
    {
        return new class extends AuthenticatableUser {
            public $id = 777;
            public $role_id = 6;
            public $shop_id = 0;
            public $shop = null;
            public $username = 'b2b_admin';
            public $email = 'b2b_admin@example.test';

            public function hasPermission($permission, $allRequired = true)
            {
                $allowedPermissions = ['access.admin.panel', 'b2b.wallet.manual_action'];

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
