<?php

namespace Tests\Unit;

use Illuminate\Http\Request;
use Tests\TestCase;
use VanguardLTE\B2B\Services\B2BAdminAccessControl;
use VanguardLTE\Http\Middleware\AuthorizeB2BAdminPermission;

class B2BAdminPermissionMiddlewareTest extends TestCase
{
    public function testAllowsUsersWithB2BRolePermission()
    {
        $request = $this->requestWithUser($this->userWithRoles(['auditor']));
        $middleware = new AuthorizeB2BAdminPermission(app(B2BAdminAccessControl::class));

        $response = $middleware->handle($request, function () {
            return response('allowed', 200);
        }, 'b2b.reports.view');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('allowed', $response->getContent());
    }

    public function testDeniesUsersWithoutB2BPermissionAsJson()
    {
        $request = $this->requestWithUser($this->userWithRoles(['support']), true);
        $middleware = new AuthorizeB2BAdminPermission(app(B2BAdminAccessControl::class));

        $response = $middleware->handle($request, function () {
            return response('allowed', 200);
        }, 'b2b.audit.view');

        $payload = json_decode($response->getContent(), true);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('b2b_permission_required', $payload['error']);
        $this->assertSame('b2b.audit.view', $payload['required_permission']);
    }

    private function requestWithUser($user, $expectsJson = false)
    {
        $server = $expectsJson ? ['HTTP_ACCEPT' => 'application/json'] : [];
        $request = Request::create('/backend/b2b', 'GET', [], [], [], $server);
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        return $request;
    }

    private function userWithRoles(array $roleSlugs)
    {
        return new class($roleSlugs) {
            public $roles;

            public function __construct(array $roleSlugs)
            {
                $this->roles = array_map(function ($slug) {
                    return (object) ['slug' => $slug];
                }, $roleSlugs);
            }
        };
    }
}
