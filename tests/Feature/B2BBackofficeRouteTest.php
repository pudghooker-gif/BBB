<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\B2BApiTestHelpers;
use Tests\TestCase;

class B2BBackofficeRouteTest extends TestCase
{
    use B2BApiTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\VanguardLTE\Http\Middleware\Checker::class);
        $this->withoutMiddleware(\VanguardLTE\Http\Middleware\Google2FAMiddleware::class);
        Cache::flush();
        $this->resetB2BTables();
        $this->createLegacyBackendTables();
    }

    public function testB2BBackofficeRedirectsGuestsToBackendLogin()
    {
        $this->get('/backend/b2b')
            ->assertRedirect('/backend/login');
    }

    public function testB2BBackofficeDashboardShowsProviderHealth()
    {
        $this->actingAs($this->adminUser())
            ->get('/backend/b2b')
            ->assertStatus(200)
            ->assertSee('Provider Health')
            ->assertSee('goldsvet_internal')
            ->assertSee('yes')
            ->assertDontSee('request_payload')
            ->assertDontSee('response_payload')
            ->assertDontSee('provider-request-secret');
    }

    public function testB2BStepUpRedirectsGuestsToBackendLogin()
    {
        $this->get('/backend/b2b/step-up/api_key.rotate')
            ->assertRedirect('/backend/login');
    }

    public function testB2BManualWalletActionsRedirectGuestsToBackendLogin()
    {
        $this->get('/backend/b2b/wallet/manual-actions')
            ->assertRedirect('/backend/login');
    }

    public function testB2BSettlementsRedirectGuestsToBackendLogin()
    {
        $this->get('/backend/b2b/settlements')
            ->assertRedirect('/backend/login');
    }

    public function testB2BCredentialsRedirectGuestsToBackendLogin()
    {
        $this->get('/backend/b2b/credentials')
            ->assertRedirect('/backend/login');
    }

    public function testB2BOperatorsRedirectGuestsToBackendLogin()
    {
        $this->get('/backend/b2b/operators')
            ->assertRedirect('/backend/login');
    }

    public function testB2BPayloadReviewRedirectsGuestsToBackendLogin()
    {
        $this->get('/backend/b2b/payloads')
            ->assertRedirect('/backend/login');
    }

    public function testB2BCaseManagementRedirectsGuestsToBackendLogin()
    {
        $this->get('/backend/b2b/cases')
            ->assertRedirect('/backend/login');
    }

    public function testB2BAuditTrailRedirectsGuestsToBackendLogin()
    {
        $this->get('/backend/b2b/audit')
            ->assertRedirect('/backend/login');
    }

    public function testB2BAuditTrailExportRedirectsGuestsToBackendLogin()
    {
        $this->get('/backend/b2b/audit/export')
            ->assertRedirect('/backend/login');
    }

    private function adminUser()
    {
        return new class extends AuthenticatableUser {
            public $id = 791;
            public $role_id = 6;
            public $shop_id = 0;
            public $shop = null;
            public $username = 'b2b_operator';
            public $email = 'b2b_operator@example.test';

            public function hasPermission($permission, $allRequired = true)
            {
                $allowedPermissions = ['access.admin.panel', 'b2b.reports.view'];
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
