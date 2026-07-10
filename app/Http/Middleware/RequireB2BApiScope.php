<?php

namespace VanguardLTE\Http\Middleware;

use Closure;
use VanguardLTE\B2B\Services\B2BApiKeyScopePolicy;
use VanguardLTE\B2B\Support\B2BApiResponse;

class RequireB2BApiScope
{
    private $scopes;

    public function __construct(B2BApiKeyScopePolicy $scopes)
    {
        $this->scopes = $scopes;
    }

    public function handle($request, Closure $next, ...$requiredScopes)
    {
        $apiKey = $request->attributes->get('b2b_api_key');
        if (!$apiKey) {
            return B2BApiResponse::error($request, 'B2B_SCOPE_DENIED', 'B2B API key context is missing.', 403);
        }

        $storedScopes = isset($apiKey->scopes) ? $apiKey->scopes : null;
        if (!$this->scopes->allows($storedScopes, $requiredScopes)) {
            return B2BApiResponse::error($request, 'B2B_SCOPE_DENIED', 'API key is missing a required B2B scope.', 403, null, [
                'required_scopes' => $this->scopes->normalize($requiredScopes, false),
            ]);
        }

        return $next($request);
    }
}
