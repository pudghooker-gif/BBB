<?php

namespace VanguardLTE\Http\Controllers\Web\Backend;

use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;
use VanguardLTE\B2B\Services\B2BCaseManagementService;
use VanguardLTE\B2B\Services\B2BOperatorSupportTicketService;
use VanguardLTE\B2B\Services\B2BWebStepUpGuard;
use VanguardLTE\Http\Controllers\Controller;

class B2BCaseBackofficeController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', '2fa', 'only_for_admin']);
        $this->middleware('permission:access.admin.panel');
    }

    public function index(B2BCaseManagementService $cases, B2BOperatorSupportTicketService $tickets)
    {
        return $this->view($cases, $tickets);
    }

    public function redirectToIndex()
    {
        return redirect()->route('backend.b2b.cases.index');
    }

    public function showCase(Request $request, B2BCaseManagementService $cases, $caseId)
    {
        $this->validate($request, [
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        try {
            $case = $cases->backofficeCaseThread($caseId, (int) $request->query('limit', 50));
        } catch (InvalidArgumentException $e) {
            return $this->failed($e->getMessage());
        } catch (RuntimeException $e) {
            return $this->failed($e->getMessage());
        }

        return view('backend.b2b.case', [
            'case' => $case,
        ]);
    }

    public function showSupportTicket(Request $request, B2BOperatorSupportTicketService $tickets, $ticketUid)
    {
        $this->validate($request, [
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        try {
            $ticket = $tickets->backofficeTicketThread($ticketUid, (int) $request->query('limit', 50));
        } catch (InvalidArgumentException $e) {
            return $this->failed($e->getMessage());
        } catch (RuntimeException $e) {
            return $this->failed($e->getMessage());
        }

        return view('backend.b2b.support-ticket', [
            'ticket' => $ticket,
        ]);
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

    public function commentSupportTicket(Request $request, B2BOperatorSupportTicketService $tickets, B2BWebStepUpGuard $stepUp)
    {
        return $this->mutateSupportTicket($request, $tickets, $stepUp, 'staffComment', 'support_ticket.comment');
    }

    public function closeSupportTicket(Request $request, B2BOperatorSupportTicketService $tickets, B2BWebStepUpGuard $stepUp)
    {
        return $this->mutateSupportTicket($request, $tickets, $stepUp, 'staffClose', 'support_ticket.close');
    }

    public function reopenSupportTicket(Request $request, B2BOperatorSupportTicketService $tickets, B2BWebStepUpGuard $stepUp)
    {
        return $this->mutateSupportTicket($request, $tickets, $stepUp, 'staffReopen', 'support_ticket.reopen');
    }

    private function mutate(Request $request, B2BCaseManagementService $cases, B2BWebStepUpGuard $stepUp, $method, $stepUpAction)
    {
        $this->validate($request, [
            'case_id' => 'required|integer|min:1',
            'reason' => 'required|string|max:1000',
            'redirect_to' => 'nullable|string|max:2048',
        ]);

        try {
            $case = $cases->{$method}(
                $request->input('case_id'),
                $this->actor($request),
                $request->input('reason'),
                $this->context($request)
            );
        } catch (InvalidArgumentException $e) {
            return $this->failed($e->getMessage(), $request);
        } catch (RuntimeException $e) {
            return $this->failed($e->getMessage(), $request);
        }

        $stepUp->forget($request, $stepUpAction);

        return redirect($this->safeRedirect($request->input('redirect_to'), route('backend.b2b.cases.index')))
            ->with('success', 'B2B case #' . $case->id . ' updated.');
    }

    private function mutateSupportTicket(Request $request, B2BOperatorSupportTicketService $tickets, B2BWebStepUpGuard $stepUp, $method, $stepUpAction)
    {
        $messageField = $method === 'staffComment' ? 'message' : 'reason';
        $this->validate($request, [
            'ticket_uid' => 'required|string|max:80',
            $messageField => 'required|string|max:2000',
            'redirect_to' => 'nullable|string|max:2048',
        ]);

        try {
            $ticket = $tickets->{$method}(
                $request->input('ticket_uid'),
                $this->actor($request),
                $request->input($messageField),
                $this->supportTicketContext($request)
            );
        } catch (InvalidArgumentException $e) {
            return $this->failed($e->getMessage(), $request);
        } catch (RuntimeException $e) {
            return $this->failed($e->getMessage(), $request);
        }

        $stepUp->forget($request, $stepUpAction);

        return redirect($this->safeRedirect($request->input('redirect_to'), route('backend.b2b.cases.index')))
            ->with('success', 'B2B support ticket ' . $ticket->ticket_uid . ' updated.');
    }

    private function view(B2BCaseManagementService $cases, B2BOperatorSupportTicketService $tickets)
    {
        return view('backend.b2b.cases', [
            'cases' => $cases->cases(),
            'support_tickets' => $tickets->backofficeTickets(),
        ]);
    }

    private function failed($message, Request $request = null)
    {
        $redirectTo = $request
            ? $this->safeRedirect($request->input('redirect_to'), route('backend.b2b.cases.index'))
            : route('backend.b2b.cases.index');

        return redirect($redirectTo)
            ->withErrors(['b2b_case' => $message])
            ->withInput();
    }

    private function safeRedirect($target, $fallback)
    {
        $target = trim((string) $target);
        if ($target !== '' && strpos($target, '/') === 0 && strpos($target, '//') !== 0) {
            return $target;
        }

        return $fallback;
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

    private function supportTicketContext(Request $request)
    {
        return [
            'permission' => 'b2b.cases.manage',
            'step_up' => true,
            'source' => 'web_backoffice',
            'request_id' => $request->headers->get('X-Request-Id'),
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
