<?php

namespace Tests\Feature;

use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use VanguardLTE\B2B\Services\B2BWebStepUpGuard;

class B2BWebStepUpPasswordTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\VanguardLTE\Http\Middleware\Checker::class);
        $this->withoutMiddleware(\VanguardLTE\Http\Middleware\Google2FAMiddleware::class);
        $this->createLegacyBackendTables();
    }

    public function testStepUpFormRequiresCurrentPassword()
    {
        $this->actingAs($this->adminUser())
            ->get('/backend/b2b/step-up/api_key.rotate')
            ->assertStatus(200)
            ->assertSee('Current Password')
            ->assertSee('ROTATE_API_KEY');
    }

    public function testWrongCurrentPasswordDoesNotCreateStepUpSession()
    {
        $guard = app(B2BWebStepUpGuard::class);

        $response = $this->actingAs($this->adminUser())
            ->withSession(['_token' => 'test-token'])
            ->post('/backend/b2b/step-up/api_key.rotate', [
                '_token' => 'test-token',
                'confirm' => 'ROTATE_API_KEY',
                'current_password' => 'wrong-password',
                'redirect_to' => '/backend/b2b/credentials',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('b2b_step_up');
        $this->assertFalse(session()->has($guard->sessionKey('api_key.rotate')));
    }

    public function testCorrectCurrentPasswordCreatesPasswordVerifiedStepUpSession()
    {
        $admin = $this->adminUser();
        $guard = app(B2BWebStepUpGuard::class);

        $response = $this->actingAs($admin)
            ->withSession(['_token' => 'test-token'])
            ->post('/backend/b2b/step-up/api_key.rotate', [
                '_token' => 'test-token',
                'confirm' => 'ROTATE_API_KEY',
                'current_password' => 'correct-password',
                'redirect_to' => '/backend/b2b/credentials',
            ]);

        $response->assertRedirect('/backend/b2b/credentials');
        $response->assertSessionHas('success');

        $payload = session($guard->sessionKey('api_key.rotate'));
        $this->assertIsArray($payload);
        $this->assertSame((string) $admin->getAuthIdentifier(), $payload['user_id']);
        $this->assertArrayHasKey('verified_at', $payload);
        $this->assertArrayHasKey('password_verified_at', $payload);
    }

    private function adminUser()
    {
        return new class extends AuthenticatableUser {
            public $id = 880;
            public $role_id = 6;
            public $shop_id = 0;
            public $shop = null;
            public $username = 'b2b_step_up_admin';
            public $email = 'b2b_step_up_admin@example.test';
            public $password;

            public function __construct()
            {
                $this->password = Hash::make('correct-password');
            }

            public function hasPermission($permission, $allRequired = true)
            {
                $allowedPermissions = [
                    'access.admin.panel',
                    'b2b.credentials.rotate',
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

            public function getAuthPassword()
            {
                return $this->password;
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
