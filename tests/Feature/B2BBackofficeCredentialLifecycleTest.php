<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\B2BApiTestHelpers;
use Tests\TestCase;
use VanguardLTE\B2B\Models\B2BOperatorApiKey;
use VanguardLTE\B2B\Services\B2BWebStepUpGuard;

class B2BBackofficeCredentialLifecycleTest extends TestCase
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
        $this->operator = $this->createB2BOperator('op_web_credentials', 'key_initial', 'web_credentials_secret_1234567890');
    }

    public function testCredentialScreenListsOperatorsAndApiKeys()
    {
        $this->actingAs($this->adminUser())
            ->get('/backend/b2b/credentials')
            ->assertStatus(200)
            ->assertSee('B2B Credentials')
            ->assertSee('op_web_credentials')
            ->assertSee('key_initial');
    }

    public function testRotateRequiresWebStepUp()
    {
        $response = $this->actingAs($this->adminUser())
            ->withSession(['_token' => 'test-token'])
            ->post('/backend/b2b/credentials/rotate', [
                '_token' => 'test-token',
                'operator_uid' => 'op_web_credentials',
                'key_id' => 'key_denied',
                'reason' => 'Quarterly key rotation.',
            ]);

        $this->assertStringContainsString('/backend/b2b/step-up/api_key.rotate', $response->headers->get('Location'));
        $this->assertFalse(DB::table('b2b_operator_api_keys')->where('key_id', 'key_denied')->exists());
    }

    public function testRotateCreatesKeyShowsSecretRevokesExistingAndWritesAudit()
    {
        $admin = $this->adminUser();
        $guard = app(B2BWebStepUpGuard::class);

        $response = $this->actingAs($admin)
            ->withSession([
                '_token' => 'test-token',
                $guard->sessionKey('api_key.rotate') => [
                    'user_id' => (string) $admin->getAuthIdentifier(),
                    'verified_at' => time(),
                ],
            ])
            ->post('/backend/b2b/credentials/rotate', [
                '_token' => 'test-token',
                'operator_uid' => 'op_web_credentials',
                'key_id' => 'key_rotated_web',
                'max_rps' => 7,
                'revoke_existing' => '1',
                'reason' => 'Quarterly key rotation.',
            ]);

        $response->assertStatus(200)
            ->assertSee('B2B API key rotated')
            ->assertSee('key_rotated_web')
            ->assertSee('Secret:');
        $response->assertSessionMissing($guard->sessionKey('api_key.rotate'));

        $this->assertSame(B2BOperatorApiKey::STATUS_DISABLED, DB::table('b2b_operator_api_keys')->where('key_id', 'key_initial')->value('status'));
        $this->assertSame(B2BOperatorApiKey::STATUS_ACTIVE, DB::table('b2b_operator_api_keys')->where('key_id', 'key_rotated_web')->value('status'));
        $this->assertSame(7, (int) DB::table('b2b_operator_api_keys')->where('key_id', 'key_rotated_web')->value('max_rps'));

        $rotatedMetadata = $this->auditMetadata('api_key.rotated', 'key_rotated_web');
        $this->assertSame('b2b.credentials.rotate', $rotatedMetadata['permission']);
        $this->assertTrue($rotatedMetadata['step_up']);
        $this->assertSame('web_backoffice', $rotatedMetadata['source']);
        $this->assertSame(1, $rotatedMetadata['disabled_existing']);

        $revokedMetadata = $this->auditMetadata('api_key.revoked', 'key_initial');
        $this->assertSame('key_rotated_web', $revokedMetadata['replacement_key_id']);
        $this->assertSame('web_backoffice', $revokedMetadata['source']);
    }

    public function testRevokeRequiresWebStepUp()
    {
        $response = $this->actingAs($this->adminUser())
            ->withSession(['_token' => 'test-token'])
            ->post('/backend/b2b/credentials/revoke', [
                '_token' => 'test-token',
                'operator_uid' => 'op_web_credentials',
                'key_id' => 'key_initial',
                'reason' => 'Partner requested revocation.',
            ]);

        $this->assertStringContainsString('/backend/b2b/step-up/api_key.revoke', $response->headers->get('Location'));
        $this->assertSame(B2BOperatorApiKey::STATUS_ACTIVE, DB::table('b2b_operator_api_keys')->where('key_id', 'key_initial')->value('status'));
    }

    public function testRevokeDisablesKeyAndWritesAuditWithFreshWebStepUp()
    {
        $admin = $this->adminUser();
        $guard = app(B2BWebStepUpGuard::class);

        $response = $this->actingAs($admin)
            ->withSession([
                '_token' => 'test-token',
                $guard->sessionKey('api_key.revoke') => [
                    'user_id' => (string) $admin->getAuthIdentifier(),
                    'verified_at' => time(),
                ],
            ])
            ->post('/backend/b2b/credentials/revoke', [
                '_token' => 'test-token',
                'operator_uid' => 'op_web_credentials',
                'key_id' => 'key_initial',
                'reason' => 'Partner requested revocation.',
            ]);

        $response->assertRedirect(route('backend.b2b.credentials.index'));
        $response->assertSessionMissing($guard->sessionKey('api_key.revoke'));
        $this->assertSame(B2BOperatorApiKey::STATUS_DISABLED, DB::table('b2b_operator_api_keys')->where('key_id', 'key_initial')->value('status'));

        $metadata = $this->auditMetadata('api_key.revoked', 'key_initial');
        $this->assertSame('b2b.credentials.revoke', $metadata['permission']);
        $this->assertTrue($metadata['step_up']);
        $this->assertSame('web_backoffice', $metadata['source']);
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
            public $id = 779;
            public $role_id = 6;
            public $shop_id = 0;
            public $shop = null;
            public $username = 'b2b_security';
            public $email = 'b2b_security@example.test';

            public function hasPermission($permission, $allRequired = true)
            {
                $allowedPermissions = [
                    'access.admin.panel',
                    'b2b.credentials.rotate',
                    'b2b.credentials.revoke',
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
