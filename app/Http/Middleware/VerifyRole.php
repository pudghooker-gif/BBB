<?php

namespace VanguardLTE\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Guard;
use VanguardLTE\Exceptions\Authorization\RoleDeniedException;

class VerifyRole
{
    protected $auth;

    public function __construct(Guard $auth)
    {
        $this->auth = $auth;
    }

    public function handle($request, Closure $next, $role)
    {
        if ($this->auth->check() && $this->auth->user()->hasRole($role)) {
            return $next($request);
        }

        throw new RoleDeniedException($role);
    }
}
