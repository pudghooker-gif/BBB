<?php

namespace VanguardLTE\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use VanguardLTE\Support\TwoFactor\Google2FAService;

class Google2FAMiddleware
{
    private $google2fa;

    public function __construct(Google2FAService $google2fa)
    {
        $this->google2fa = $google2fa;
    }

    public function handle(Request $request, Closure $next)
    {
        if ($this->google2fa->canPassWithoutChallenge($request)) {
            return $next($request);
        }

        $status = $this->google2fa->checkRequest($request);

        if ($status === Google2FAService::OTP_VALID) {
            return $next($request);
        }

        return $this->google2fa->makeChallengeResponse($request, $status);
    }
}
