<?php

namespace VanguardLTE\Http\Middleware;

use Closure;
use VanguardLTE\B2B\Services\B2BAdminAccessControl;

class AuthorizeB2BAdminPermission
{
    private $access;

    public function __construct(B2BAdminAccessControl $access)
    {
        $this->access = $access;
    }

    public function handle($request, Closure $next, $permission)
    {
        if ($this->access->userHasPermission($request->user(), $permission)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'error' => 'b2b_permission_required',
                'message' => 'B2B admin permission is required: ' . $permission,
                'required_permission' => $permission,
            ], 403);
        }

        abort(403, 'B2B admin permission is required: ' . $permission);
    }
}
