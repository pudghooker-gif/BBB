<?php

namespace VanguardLTE\Http\Controllers\Web\Backend;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;
use VanguardLTE\B2B\Services\B2BOperatorConfigurationService;
use VanguardLTE\B2B\Services\B2BWebStepUpGuard;
use VanguardLTE\Http\Controllers\Controller;

class B2BOperatorBackofficeController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', '2fa', 'only_for_admin']);
        $this->middleware('permission:access.admin.panel');
    }

    public function index()
    {
        return view('backend.b2b.operators', [
            'operators' => $this->operators(),
        ]);
    }

    public function redirectToIndex()
    {
        return redirect()->route('backend.b2b.operators.index');
    }

    public function update(Request $request, B2BOperatorConfigurationService $operators, B2BWebStepUpGuard $stepUp)
    {
        $this->validate($request, [
            'operator_uid' => 'required|string|max:80',
            'name' => 'required|string|max:191',
            'shop_id' => 'nullable|integer|min:0',
            'base_url' => 'nullable|url|max:255',
            'wallet_callback_url' => 'nullable|url|max:255',
            'default_currency' => 'required|string|size:3',
            'allowed_currencies' => 'nullable|string|max:500',
            'allowed_countries' => 'nullable|string|max:500',
            'ip_whitelist' => 'nullable|string|max:1000',
            'max_rps' => 'required|integer|min:1|max:100000',
            'wallet_timeout_ms' => 'required|integer|min:100|max:120000',
            'connect_timeout_ms' => 'required|integer|min:100|max:60000',
            'circuit_breaker_threshold' => 'required|integer|min:1|max:1000',
            'circuit_breaker_cooldown_seconds' => 'required|integer|min:1|max:86400',
            'reason' => 'required|string|max:1000',
        ]);

        try {
            $operator = $operators->update($request->input('operator_uid'), $request->all(), $this->actor($request), $request->input('reason'), $this->context($request, 'b2b.operators.update'));
        } catch (InvalidArgumentException $e) {
            return $this->failed($e->getMessage());
        } catch (RuntimeException $e) {
            return $this->failed($e->getMessage());
        }

        $stepUp->forget($request, 'operator.update');

        return redirect()
            ->route('backend.b2b.operators.index')
            ->with('success', 'B2B operator ' . $operator->operator_uid . ' updated.');
    }

    public function suspend(Request $request, B2BOperatorConfigurationService $operators, B2BWebStepUpGuard $stepUp)
    {
        return $this->statusChange($request, $operators, $stepUp, 'suspend', 'operator.suspend');
    }

    public function resume(Request $request, B2BOperatorConfigurationService $operators, B2BWebStepUpGuard $stepUp)
    {
        return $this->statusChange($request, $operators, $stepUp, 'resume', 'operator.resume');
    }

    private function statusChange(Request $request, B2BOperatorConfigurationService $operators, B2BWebStepUpGuard $stepUp, $decision, $stepUpAction)
    {
        $this->validate($request, [
            'operator_uid' => 'required|string|max:80',
            'reason' => 'required|string|max:1000',
        ]);

        try {
            $operator = $decision === 'resume'
                ? $operators->resume($request->input('operator_uid'), $this->actor($request), $request->input('reason'), $this->context($request, 'b2b.operators.suspend'))
                : $operators->suspend($request->input('operator_uid'), $this->actor($request), $request->input('reason'), $this->context($request, 'b2b.operators.suspend'));
        } catch (InvalidArgumentException $e) {
            return $this->failed($e->getMessage());
        } catch (RuntimeException $e) {
            return $this->failed($e->getMessage());
        }

        $stepUp->forget($request, $stepUpAction);

        return redirect()
            ->route('backend.b2b.operators.index')
            ->with('success', 'B2B operator ' . $operator->operator_uid . ' ' . $decision . ' recorded.');
    }

    private function failed($message)
    {
        return redirect()
            ->route('backend.b2b.operators.index')
            ->withErrors(['operator_workflow' => $message])
            ->withInput();
    }

    private function operators()
    {
        if (!Schema::hasTable('b2b_operators')) {
            return collect();
        }

        return DB::table('b2b_operators')
            ->select(
                'operator_uid',
                'name',
                'shop_id',
                'status',
                'base_url',
                'wallet_callback_url',
                'default_currency',
                'allowed_currencies',
                'allowed_countries',
                'ip_whitelist',
                'max_rps',
                'wallet_timeout_ms',
                'connect_timeout_ms',
                'circuit_breaker_threshold',
                'circuit_breaker_cooldown_seconds',
                'updated_at'
            )
            ->orderBy('id', 'desc')
            ->limit(100)
            ->get();
    }

    private function context(Request $request, $permission)
    {
        return [
            'permission' => $permission,
            'step_up' => true,
            'source' => 'web_backoffice',
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ];
    }

    private function actor(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return 'web:unknown';
        }

        foreach (['username', 'email'] as $field) {
            if (isset($user->{$field}) && trim((string) $user->{$field}) !== '') {
                return 'web:' . trim((string) $user->{$field});
            }
        }

        if (method_exists($user, 'getAuthIdentifier')) {
            return 'web:user:' . $user->getAuthIdentifier();
        }

        return 'web:user';
    }
}
