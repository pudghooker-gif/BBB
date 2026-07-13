<?php

namespace VanguardLTE\Http\Controllers\Web\Backend;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;
use VanguardLTE\B2B\Services\B2BWebStepUpGuard;
use VanguardLTE\B2B\Services\WalletManualActionService;
use VanguardLTE\Http\Controllers\Controller;

class B2BWalletManualActionController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', '2fa', 'only_for_admin']);
        $this->middleware('permission:access.admin.panel');
    }

    public function index(Request $request, WalletManualActionService $manualActions)
    {
        return view('backend.b2b.wallet-manual-actions', [
            'actions' => $manualActions->supportedActions(),
            'transactions' => $this->candidateTransactions(),
            'form' => $this->formDefaults($request),
        ]);
    }

    public function store(Request $request, WalletManualActionService $manualActions, B2BWebStepUpGuard $stepUp)
    {
        $this->validate($request, [
            'transaction_uid' => 'required|string|max:191',
            'operator_id' => 'nullable|integer|min:1',
            'action' => 'required|string|max:80',
            'reason' => 'required|string|max:1000',
            'redirect_to' => 'nullable|string|max:2048',
        ]);

        $operatorId = $request->input('operator_id');
        try {
            $result = $manualActions->apply(
                $request->input('transaction_uid'),
                $request->input('action'),
                $request->input('reason'),
                $this->actor($request),
                $operatorId === null || $operatorId === '' ? null : $operatorId,
                [
                    'permission' => 'b2b.wallet.manual_action',
                    'step_up' => true,
                    'source' => 'web_backoffice',
                    'ip_address' => $request->ip(),
                    'user_agent' => substr((string) $request->userAgent(), 0, 500),
                ]
            );
        } catch (InvalidArgumentException $e) {
            return $this->failed($e->getMessage());
        } catch (RuntimeException $e) {
            return $this->failed($e->getMessage());
        }

        $stepUp->forget($request, 'wallet.manual_action');

        return redirect($this->safeRedirect($request->input('redirect_to'), route('backend.b2b.wallet_manual_actions.index')))
            ->with('success', 'Manual wallet action applied to ' . ($result['transaction_uid'] ?: $request->input('transaction_uid')) . '.');
    }

    private function failed($message)
    {
        return redirect()
            ->route('backend.b2b.wallet_manual_actions.index')
            ->withErrors(['wallet_manual_action' => $message])
            ->withInput();
    }

    private function candidateTransactions()
    {
        if (!Schema::hasTable('b2b_wallet_transactions')) {
            return collect();
        }

        return DB::table('b2b_wallet_transactions')
            ->select('id', 'operator_id', 'transaction_uid', 'transaction_id', 'type', 'status', 'amount', 'currency', 'attempts', 'last_error', 'created_at')
            ->whereIn('status', ['failed', 'timeout', 'unknown', 'rollback_required', 'manual_review', 'dead_letter'])
            ->orderBy('id', 'desc')
            ->limit(25)
            ->get();
    }

    private function formDefaults(Request $request)
    {
        return [
            'transaction_uid' => substr(trim((string) $request->query('transaction_uid', '')), 0, 191),
            'operator_id' => substr(trim((string) $request->query('operator_id', '')), 0, 20),
            'action' => substr(trim((string) $request->query('action', 'mark-review')), 0, 80),
            'reason' => substr(trim((string) $request->query('reason', '')), 0, 1000),
            'redirect_to' => $this->safeRedirect($request->query('redirect_to', ''), ''),
        ];
    }

    private function safeRedirect($target, $fallback)
    {
        $target = trim((string) $target);
        if ($target !== ''
            && strpos($target, '/backend/b2b') === 0
            && strpos($target, '//') !== 0
            && strpos($target, "\n") === false
            && strpos($target, "\r") === false) {
            return $target;
        }

        return $fallback;
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
