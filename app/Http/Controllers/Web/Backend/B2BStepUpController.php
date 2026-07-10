<?php

namespace VanguardLTE\Http\Controllers\Web\Backend;

use Illuminate\Http\Request;
use VanguardLTE\B2B\Services\B2BWebStepUpGuard;
use VanguardLTE\Http\Controllers\Controller;

class B2BStepUpController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', '2fa', 'only_for_admin']);
        $this->middleware('permission:access.admin.panel');
    }

    public function show(Request $request, $action, B2BWebStepUpGuard $guard)
    {
        $result = $guard->requirement($request, $action);
        if (!$result['ok']) {
            abort($result['code'] === 'unknown_action' ? 404 : 403, $result['message']);
        }

        return view('backend.b2b.step-up', [
            'action' => $action,
            'required_permission' => $result['permission'],
            'required_confirmation' => $result['confirm'],
            'ttl_seconds' => $guard->ttlSeconds(),
            'password_required' => $guard->passwordRequired(),
            'redirect_to' => $this->safeRedirect($request->query('redirect_to')),
        ]);
    }

    public function store(Request $request, $action, B2BWebStepUpGuard $guard)
    {
        $this->validate($request, [
            'confirm' => 'required|string|max:128',
            'current_password' => ($guard->passwordRequired() ? 'required' : 'nullable') . '|string|max:255',
            'redirect_to' => 'nullable|string|max:2048',
        ]);

        $result = $guard->confirm($request, $action, $request->input('confirm'), $request->input('current_password'));
        if (!$result['ok']) {
            return redirect()
                ->route('backend.b2b.step_up.show', ['action' => $action, 'redirect_to' => $this->safeRedirect($request->input('redirect_to'))])
                ->withErrors(['b2b_step_up' => $result['message']])
                ->withInput();
        }

        return redirect($this->safeRedirect($request->input('redirect_to')))
            ->with('success', 'B2B step-up confirmed for ' . $action . '.');
    }

    private function safeRedirect($target)
    {
        $target = trim((string) $target);
        if ($target !== '' && strpos($target, '/') === 0 && strpos($target, '//') !== 0) {
            return $target;
        }

        return route('backend.b2b.dashboard');
    }
}
