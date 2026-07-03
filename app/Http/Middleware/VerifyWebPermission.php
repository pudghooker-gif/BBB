<?php

namespace VanguardLTE\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Guard;
use VanguardLTE\Exceptions\Authorization\PermissionDeniedException;

class VerifyWebPermission
{
    protected $auth;

    public function __construct(Guard $auth)
    {
        $this->auth = $auth;
    }

    public function handle($request, Closure $next, $permission)
    {
        if ($this->auth->check() && $this->auth->user()->hasPermission($permission)) {
            return $next($request);
        }

        throw new PermissionDeniedException($permission);
    }
}
