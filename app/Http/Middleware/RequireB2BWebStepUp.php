<?php

namespace VanguardLTE\Http\Middleware;

use Closure;
use VanguardLTE\B2B\Services\B2BWebStepUpGuard;

class RequireB2BWebStepUp
{
    private $guard;

    public function __construct(B2BWebStepUpGuard $guard)
    {
        $this->guard = $guard;
    }

    public function handle($request, Closure $next, $action)
    {
        $result = $this->guard->authorize($request, $action);
        if ($result['ok']) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'error' => 'b2b_step_up_required',
                'code' => $result['code'],
                'message' => $result['message'],
                'action' => $action,
                'required_permission' => isset($result['required_permission']) ? $result['required_permission'] : null,
                'required_confirmation' => isset($result['required_confirmation']) ? $result['required_confirmation'] : null,
            ], 403);
        }

        return redirect()
            ->route('backend.b2b.step_up.show', ['action' => $action, 'redirect_to' => $request->getRequestUri()])
            ->withErrors(['b2b_step_up' => $result['message']]);
    }
}
