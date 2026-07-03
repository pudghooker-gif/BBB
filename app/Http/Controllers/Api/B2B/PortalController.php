<?php

namespace VanguardLTE\Http\Controllers\Api\B2B;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use RuntimeException;
use VanguardLTE\B2B\Services\B2BOperatorPortalQuery;
use VanguardLTE\B2B\Services\B2BOperatorSupportCaseService;
use VanguardLTE\B2B\Services\B2BOperatorSupportTicketService;
use VanguardLTE\B2B\Support\B2BApiResponse;

class PortalController extends Controller
{
    private $sections = [
        'credentials' => 'Credentials',
        'games' => 'Game Assignments',
        'sessions' => 'Sessions',
        'transactions' => 'Transactions',
        'settlements' => 'Settlements',
        'cases' => 'Cases',
        'callbacks' => 'Callbacks',
        'reports' => 'Reports',
        'support' => 'Support',
        'docs' => 'Docs',
    ];

    public function overview(Request $request, B2BOperatorPortalQuery $portal)
    {
        $validationResponse = $this->validatePortalQuery($request);
        if ($validationResponse) {
            return $validationResponse;
        }

        $payload = $portal->overview($request);
        if (!$payload) {
            return B2BApiResponse::error($request, 'OPERATOR_CONTEXT_MISSING', null, 500);
        }

        return B2BApiResponse::success($request, $payload);
    }

    public function page(Request $request, B2BOperatorPortalQuery $portal)
    {
        $validationResponse = $this->validatePortalQuery($request);
        if ($validationResponse) {
            return $validationResponse;
        }

        $payload = $portal->overview($request);
        if (!$payload) {
            abort(500, 'B2B operator context is missing.');
        }

        return response()
            ->view('b2b.operator-portal.overview', $payload)
            ->header('Cache-Control', 'no-store, private');
    }

    public function section(Request $request, B2BOperatorPortalQuery $portal, $section)
    {
        $section = strtolower((string) $section);
        if (!isset($this->sections[$section])) {
            abort(404);
        }

        $validationResponse = $this->validatePortalQuery($request);
        if ($validationResponse) {
            return $validationResponse;
        }

        $payload = $portal->overview($request);
        if (!$payload) {
            abort(500, 'B2B operator context is missing.');
        }

        $payload['portal_section'] = [
            'key' => $section,
            'title' => $this->sections[$section],
        ];
        $payload['portal_sections'] = $this->sections;

        return response()
            ->view('b2b.operator-portal.section', $payload)
            ->header('Cache-Control', 'no-store, private');
    }

    public function commentCase(Request $request, B2BOperatorSupportCaseService $supportCases, $transactionUid)
    {
        $operator = $request->attributes->get('b2b_operator');
        if (!$operator || !isset($operator->id)) {
            return B2BApiResponse::error($request, 'OPERATOR_CONTEXT_MISSING', null, 500);
        }

        $validator = Validator::make(array_merge($request->all(), [
            'transaction_uid' => $transactionUid,
        ]), [
            'transaction_uid' => 'required|string|min:1|max:191',
            'message' => 'required|string|max:1000',
            'external_reference' => 'nullable|string|max:120',
        ]);

        if ($validator->fails()) {
            return B2BApiResponse::error($request, 'VALIDATION_FAILED', null, 422, $validator->errors());
        }

        try {
            $payload = $supportCases->comment($operator, $transactionUid, $request->input('message'), [
                'external_reference' => $request->input('external_reference'),
                'request_id' => $request->attributes->get('request_id') ?: $request->header('X-Request-Id'),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
            ]);
        } catch (InvalidArgumentException $e) {
            return B2BApiResponse::error($request, 'CASE_NOT_FOUND', $e->getMessage(), 404);
        } catch (RuntimeException $e) {
            return B2BApiResponse::error($request, 'SERVICE_NOT_READY', $e->getMessage(), 503);
        }

        return B2BApiResponse::success($request, $payload, 201);
    }

    public function createSupportTicket(Request $request, B2BOperatorSupportTicketService $tickets)
    {
        $operator = $request->attributes->get('b2b_operator');
        if (!$operator || !isset($operator->id)) {
            return B2BApiResponse::error($request, 'OPERATOR_CONTEXT_MISSING', null, 500);
        }

        $validator = Validator::make($request->all(), [
            'subject' => 'required|string|max:160',
            'message' => 'required|string|max:2000',
            'priority' => 'nullable|string|in:low,normal,high,urgent',
            'category' => 'nullable|string|max:80',
            'external_reference' => 'nullable|string|max:120',
        ]);

        if ($validator->fails()) {
            return B2BApiResponse::error($request, 'VALIDATION_FAILED', null, 422, $validator->errors());
        }

        try {
            $payload = $tickets->create($operator, $request->only([
                'subject',
                'message',
                'priority',
                'category',
                'external_reference',
            ]), $this->supportTicketContext($request));
        } catch (InvalidArgumentException $e) {
            return B2BApiResponse::error($request, 'VALIDATION_FAILED', $e->getMessage(), 422);
        } catch (RuntimeException $e) {
            return B2BApiResponse::error($request, 'SERVICE_NOT_READY', $e->getMessage(), 503);
        }

        return B2BApiResponse::success($request, $payload, 201);
    }

    public function commentSupportTicket(Request $request, B2BOperatorSupportTicketService $tickets, $ticketUid)
    {
        $operator = $request->attributes->get('b2b_operator');
        if (!$operator || !isset($operator->id)) {
            return B2BApiResponse::error($request, 'OPERATOR_CONTEXT_MISSING', null, 500);
        }

        $validator = Validator::make(array_merge($request->all(), [
            'ticket_uid' => $ticketUid,
        ]), [
            'ticket_uid' => 'required|string|min:1|max:80',
            'message' => 'required|string|max:2000',
            'external_reference' => 'nullable|string|max:120',
        ]);

        if ($validator->fails()) {
            return B2BApiResponse::error($request, 'VALIDATION_FAILED', null, 422, $validator->errors());
        }

        try {
            $payload = $tickets->comment($operator, $ticketUid, $request->input('message'), $this->supportTicketContext($request));
        } catch (InvalidArgumentException $e) {
            return B2BApiResponse::error($request, 'SUPPORT_TICKET_NOT_FOUND', $e->getMessage(), 404);
        } catch (RuntimeException $e) {
            return B2BApiResponse::error($request, 'SERVICE_NOT_READY', $e->getMessage(), 503);
        }

        return B2BApiResponse::success($request, $payload, 201);
    }

    public function closeSupportTicket(Request $request, B2BOperatorSupportTicketService $tickets, $ticketUid)
    {
        $operator = $request->attributes->get('b2b_operator');
        if (!$operator || !isset($operator->id)) {
            return B2BApiResponse::error($request, 'OPERATOR_CONTEXT_MISSING', null, 500);
        }

        $validator = Validator::make(array_merge($request->all(), [
            'ticket_uid' => $ticketUid,
        ]), [
            'ticket_uid' => 'required|string|min:1|max:80',
            'reason' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return B2BApiResponse::error($request, 'VALIDATION_FAILED', null, 422, $validator->errors());
        }

        try {
            $payload = $tickets->close($operator, $ticketUid, $request->input('reason'), $this->supportTicketContext($request));
        } catch (InvalidArgumentException $e) {
            return B2BApiResponse::error($request, 'SUPPORT_TICKET_NOT_FOUND', $e->getMessage(), 404);
        } catch (RuntimeException $e) {
            return B2BApiResponse::error($request, 'SERVICE_NOT_READY', $e->getMessage(), 503);
        }

        return B2BApiResponse::success($request, $payload);
    }

    private function supportTicketContext(Request $request)
    {
        return [
            'external_reference' => $request->input('external_reference'),
            'request_id' => $request->attributes->get('request_id') ?: $request->header('X-Request-Id'),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ];
    }

    private function validatePortalQuery(Request $request)
    {
        $validator = Validator::make($request->query(), [
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        if ($validator->fails()) {
            return B2BApiResponse::error($request, 'VALIDATION_FAILED', null, 422, $validator->errors());
        }

        if ($request->query('from') && $request->query('to')) {
            $from = Carbon::parse($request->query('from'))->startOfDay();
            $to = Carbon::parse($request->query('to'))->endOfDay();

            if ($from->gt($to)) {
                return B2BApiResponse::error($request, 'VALIDATION_FAILED', null, 422, [
                    'period' => ['Portal period start must be before or equal to period end.'],
                ]);
            }
        }

        return null;
    }
}
