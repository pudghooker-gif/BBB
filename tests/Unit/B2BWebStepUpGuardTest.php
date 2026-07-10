<?php

namespace Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use VanguardLTE\B2B\Services\B2BWebStepUpGuard;

class B2BWebStepUpGuardTest extends TestCase
{
    public function testUnknownActionIsDenied()
    {
        $request = $this->requestForUser($this->userWithRole('super_admin', 1));

        $result = app(B2BWebStepUpGuard::class)->authorize($request, 'unknown.action');

        $this->assertFalse($result['ok']);
        $this->assertSame('unknown_action', $result['code']);
    }

    public function testMissingPermissionIsDenied()
    {
        $request = $this->requestForUser($this->userWithRole('read_only', 2));

        $result = app(B2BWebStepUpGuard::class)->authorize($request, 'api_key.rotate');

        $this->assertFalse($result['ok']);
        $this->assertSame('permission_required', $result['code']);
        $this->assertSame('b2b.credentials.rotate', $result['required_permission']);
    }

    public function testConfirmStoresSessionBoundStepUp()
    {
        $guard = app(B2BWebStepUpGuard::class);
        $request = $this->requestForUser($this->userWithRole('integration_manager', 3));

        $confirmed = $guard->confirm($request, 'api_key.rotate', 'ROTATE_API_KEY', 'correct-password');
        $authorized = $guard->authorize($request, 'api_key.rotate');

        $this->assertTrue($confirmed['ok']);
        $this->assertTrue($authorized['ok']);
        $this->assertSame('b2b.credentials.rotate', $authorized['permission']);
        $this->assertArrayHasKey('password_verified_at', $authorized);
        $this->assertTrue($request->session()->has($guard->sessionKey('api_key.rotate')));
    }

    public function testWrongConfirmationWrongPasswordAndExpiredSessionAreDenied()
    {
        config(['b2b_admin.web_step_up_ttl_seconds' => 1]);

        $guard = app(B2BWebStepUpGuard::class);
        $request = $this->requestForUser($this->userWithRole('finance', 4));

        $wrong = $guard->confirm($request, 'wallet.manual_action', 'WRONG');
        $this->assertFalse($wrong['ok']);
        $this->assertSame('step_up_required', $wrong['code']);

        $wrongPassword = $guard->confirm($request, 'wallet.manual_action', 'MANUAL_WALLET_ACTION', 'wrong-password');
        $this->assertFalse($wrongPassword['ok']);
        $this->assertSame('current_password_required', $wrongPassword['code']);

        $request->session()->put($guard->sessionKey('wallet.manual_action'), [
            'user_id' => '4',
            'verified_at' => time() - 5,
            'password_verified_at' => time() - 5,
        ]);

        $expired = $guard->authorize($request, 'wallet.manual_action');
        $this->assertFalse($expired['ok']);
        $this->assertSame('step_up_expired', $expired['code']);
        $this->assertFalse($request->session()->has($guard->sessionKey('wallet.manual_action')));
    }

    public function testSessionWithoutPasswordVerificationMarkerIsDenied()
    {
        $guard = app(B2BWebStepUpGuard::class);
        $request = $this->requestForUser($this->userWithRole('integration_manager', 5));

        $request->session()->put($guard->sessionKey('api_key.revoke'), [
            'user_id' => '5',
            'verified_at' => time(),
        ]);

        $result = $guard->authorize($request, 'api_key.revoke');

        $this->assertFalse($result['ok']);
        $this->assertSame('step_up_password_required', $result['code']);
        $this->assertFalse($request->session()->has($guard->sessionKey('api_key.revoke')));
    }

    public function testStepUpIsBoundToTheAuthenticatedUser()
    {
        $guard = app(B2BWebStepUpGuard::class);
        $request = $this->requestForUser($this->userWithRole('integration_manager', 6));

        $confirmed = $guard->confirm($request, 'api_key.revoke', 'REVOKE_API_KEY', 'correct-password');
        $this->assertTrue($confirmed['ok']);

        $request->setUserResolver(function () {
            return $this->userWithRole('integration_manager', 7);
        });

        $result = $guard->authorize($request, 'api_key.revoke');

        $this->assertFalse($result['ok']);
        $this->assertSame('step_up_user_mismatch', $result['code']);
    }

    private function requestForUser($user)
    {
        $request = Request::create('/backend/b2b/step-up/api_key.rotate', 'POST');
        $session = new Store('test', new ArraySessionHandler(120));
        $session->start();
        $request->setLaravelSession($session);
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        return $request;
    }

    private function userWithRole($role, $id)
    {
        return new class($role, $id) {
            public $role;
            public $password;
            private $id;

            public function __construct($role, $id)
            {
                $this->role = (object) ['slug' => $role, 'name' => $role];
                $this->id = $id;
                $this->password = Hash::make('correct-password');
            }

            public function hasPermission($permission)
            {
                return false;
            }

            public function getAuthIdentifier()
            {
                return $this->id;
            }

            public function getAuthPassword()
            {
                return $this->password;
            }
        };
    }
}
