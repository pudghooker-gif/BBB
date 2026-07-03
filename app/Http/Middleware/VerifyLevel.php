<?php

namespace VanguardLTE\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Guard;
use VanguardLTE\Exceptions\Authorization\LevelDeniedException;

class VerifyLevel
{
    protected $auth;

    public function __construct(Guard $auth)
    {
        $this->auth = $auth;
    }

    public function handle($request, Closure $next, $level)
    {
        if ($this->auth->check() && $this->auth->user()->level() >= $level) {
            return $next($request);
        }

        throw new LevelDeniedException($level);
    }
}
