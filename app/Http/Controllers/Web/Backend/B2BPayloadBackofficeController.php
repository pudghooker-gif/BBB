<?php

namespace VanguardLTE\Http\Controllers\Web\Backend;

use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;
use VanguardLTE\B2B\Services\B2BPayloadReviewService;
use VanguardLTE\B2B\Services\B2BWebStepUpGuard;
use VanguardLTE\Http\Controllers\Controller;

class B2BPayloadBackofficeController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', '2fa', 'only_for_admin']);
        $this->middleware('permission:access.admin.panel');
    }

    public function index(B2BPayloadReviewService $payloads)
    {
        return $this->view($payloads);
    }

    public function redirectToIndex()
    {
        return redirect()->route('backend.b2b.payloads.index');
    }

    public function raw(Request $request, B2BPayloadReviewService $payloads, B2BWebStepUpGuard $stepUp)
    {
        $this->validate($request, [
            'attempt_id' => 'required|integer|min:1',
            'reason' => 'required|string|max:1000',
        ]);

        try {
            $rawAttempt = $payloads->rawAttempt(
                $request->input('attempt_id'),
                $this->actor($request),
                $request->input('reason'),
                $this->context($request)
            );
        } catch (InvalidArgumentException $e) {
            return $this->failed($e->getMessage());
        } catch (RuntimeException $e) {
            return $this->failed($e->getMessage());
        }

        $stepUp->forget($request, 'payload.view_raw');

        return $this->view($payloads, [
            'success' => 'Raw payload displayed for wallet attempt #' . $rawAttempt->id . '.',
            'rawAttempt' => $rawAttempt,
        ]);
    }

    private function view(B2BPayloadReviewService $payloads, array $data = [])
    {
        return view('backend.b2b.payloads', array_merge([
            'attempts' => $payloads->recentAttempts(),
            'success' => null,
            'rawAttempt' => null,
        ], $data));
    }

    private function failed($message)
    {
        return redirect()
            ->route('backend.b2b.payloads.index')
            ->withErrors(['payload_review' => $message])
            ->withInput();
    }

    private function context(Request $request)
    {
        return [
            'permission' => 'b2b.payloads.view_raw',
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
