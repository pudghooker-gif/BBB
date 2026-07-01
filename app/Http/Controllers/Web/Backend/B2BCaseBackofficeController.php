<?php

namespace VanguardLTE\Http\Controllers\Web\Backend;

use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;
use VanguardLTE\B2B\Services\B2BCaseManagementService;
use VanguardLTE\B2B\Services\B2BWebStepUpGuard;
use VanguardLTE\Http\Controllers\Controller;

class B2BCaseBackofficeController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', '2fa', 'only_for_admin']);
        $this->middleware('permission:access.admin.panel');
    }

    public function index(B2BCaseManagementService $cases)
    {
        return $this->view($cases);
    }

    public function redirectToIndex()
    {
        return redirect()->route('backend.b2b.cases.index');
    }

    public function claim(Request $request, B2BCaseManagementService $cases, B2BWebStepUpGuard $stepUp)
    {
        return $this->mutate($request, $cases, $stepUp, 'claim', 'case.claim');
    }

    public function resolve(Request $request, B2BCaseManagementService $cases, B2BWebStepUpGuard $stepUp)
    {
        return $this->mutate($request, $cases, $stepUp, 'resolve', 'case.resolve');
    }

    public function reopen(Request $request, B2BCaseManagementService $cases, B2BWebStepUpGuard $stepUp)
    {
        return $this->mutate($request, $cases, $stepUp, 'reopen', 'case.reopen');
    }

    private function mutate(Request $request, B2BCaseManagementService $cases, B2BWebStepUpGuard $stepUp, $method, $stepUpAction)
    {
        $this->validate($request, [
            'case_id' => 'required|integer|min:1',
            'reason' => 'required|string|max:1000',
        ]);

        try {
            $case = $cases->{$method}(
                $request->input('case_id'),
                $this->actor($request),
                $request->input('reason'),
                $this->context($request)
            );
        } catch (InvalidArgumentException $e) {
            return $this->failed($e->getMessage());
        } catch (RuntimeException $e) {
            return $this->failed($e->getMessage());
        }

        $stepUp->forget($request, $stepUpAction);

        return redirect()
            ->route('backend.b2b.cases.index')
            ->with('success', 'B2B case #' . $case->id . ' updated.');
    }

    private function view(B2BCaseManagementService $cases)
    {
        return view('backend.b2b.cases', [
            'cases' => $cases->cases(),
        ]);
    }

    private function failed($message)
    {
        return redirect()
            ->route('backend.b2b.cases.index')
            ->withErrors(['b2b_case' => $message])
            ->withInput();
    }

    private function context(Request $request)
    {
        return [
            'permission' => 'b2b.cases.manage',
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
