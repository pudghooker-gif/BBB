<?php

namespace VanguardLTE\Http\Controllers\Web\Backend;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;
use VanguardLTE\B2B\Services\B2BSettlementWorkflowService;
use VanguardLTE\B2B\Services\B2BWebStepUpGuard;
use VanguardLTE\Http\Controllers\Controller;

class B2BSettlementBackofficeController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', '2fa', 'only_for_admin']);
        $this->middleware('permission:access.admin.panel');
    }

    public function index()
    {
        return view('backend.b2b.settlements', [
            'settlements' => $this->settlements(),
        ]);
    }

    public function redirectToIndex()
    {
        return redirect()->route('backend.b2b.settlements.index');
    }

    public function submit(Request $request, B2BSettlementWorkflowService $settlements, B2BWebStepUpGuard $stepUp)
    {
        return $this->apply($request, $settlements, $stepUp, 'submit', 'settlement.submit', 'b2b.settlements.submit');
    }

    public function approve(Request $request, B2BSettlementWorkflowService $settlements, B2BWebStepUpGuard $stepUp)
    {
        return $this->apply($request, $settlements, $stepUp, 'approve', 'settlement.approve', 'b2b.settlements.approve');
    }

    public function reject(Request $request, B2BSettlementWorkflowService $settlements, B2BWebStepUpGuard $stepUp)
    {
        return $this->apply($request, $settlements, $stepUp, 'reject', 'settlement.reject', 'b2b.settlements.approve');
    }

    private function apply(Request $request, B2BSettlementWorkflowService $settlements, B2BWebStepUpGuard $stepUp, $decision, $stepUpAction, $permission)
    {
        $this->validate($request, [
            'settlement_uid' => 'required|string|max:80',
            'reason' => 'required|string|max:1000',
        ]);

        $settlementUid = $request->input('settlement_uid');
        $actor = $this->actor($request);
        $reason = $request->input('reason');
        $context = [
            'permission' => $permission,
            'step_up' => true,
            'source' => 'web_backoffice',
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ];

        try {
            if ($decision === 'submit') {
                $settlement = $settlements->submit($settlementUid, $actor, $reason, $context);
            } elseif ($decision === 'approve') {
                $settlement = $settlements->approve($settlementUid, $actor, $reason, $context);
            } else {
                $settlement = $settlements->reject($settlementUid, $actor, $reason, $context);
            }
        } catch (InvalidArgumentException $e) {
            return $this->failed($e->getMessage());
        } catch (RuntimeException $e) {
            return $this->failed($e->getMessage());
        }

        $stepUp->forget($request, $stepUpAction);

        return redirect()
            ->route('backend.b2b.settlements.index')
            ->with('success', 'Settlement ' . $settlement->settlement_uid . ' ' . $decision . ' recorded.');
    }

    private function failed($message)
    {
        return redirect()
            ->route('backend.b2b.settlements.index')
            ->withErrors(['settlement_workflow' => $message])
            ->withInput();
    }

    private function settlements()
    {
        if (!Schema::hasTable('b2b_settlements')) {
            return collect();
        }

        return DB::table('b2b_settlements')
            ->select(
                'settlement_uid',
                'operator_id',
                'period_start',
                'period_end',
                'currency',
                'ggr_amount',
                'net_amount',
                'status',
                'export_hash',
                'submitted_by',
                'approved_by',
                'rejected_by',
                'updated_at'
            )
            ->whereIn('status', ['exported', 'submitted', 'approved', 'rejected'])
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get();
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
