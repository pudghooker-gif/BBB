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
        'diagnostics' => 'Launch Diagnostics',
        'reports' => 'Reports',
        'support' => 'Support',
        'logs' => 'API Logs',
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

    public function downloadOpenApi(Request $request)
    {
        return $this->downloadDocumentationArtifact($request, base_path('docs/b2b/openapi.json'), 'bbb-b2b-openapi.json');
    }

    public function downloadPostman(Request $request)
    {
        return $this->downloadDocumentationArtifact($request, base_path('docs/b2b/postman_collection.json'), 'bbb-b2b-postman_collection.json');
    }

    public function showGame(Request $request, B2BOperatorPortalQuery $portal, $gameUid)
    {
        $operator = $request->attributes->get('b2b_operator');
        if (!$operator || !isset($operator->id)) {
            return B2BApiResponse::error($request, 'OPERATOR_CONTEXT_MISSING', null, 500);
        }

        $validator = Validator::make(array_merge($request->query(), [
            'game_uid' => $gameUid,
        ]), [
            'game_uid' => 'required|string|min:1|max:191',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        if ($validator->fails()) {
            return B2BApiResponse::error($request, 'VALIDATION_FAILED', null, 422, $validator->errors());
        }

        $payload = $portal->gameDetail($request, $gameUid, (int) $request->query('limit', 20));
        if (!$payload) {
            return B2BApiResponse::error($request, 'GAME_NOT_AVAILABLE');
        }

        return response()
            ->view('b2b.operator-portal.game', [
                'operator' => $this->operatorViewProfile($operator),
                'portal_section' => [
                    'key' => 'games',
                    'title' => 'Game Detail',
                ],
                'portal_sections' => $this->sections,
                'links' => $this->portalLinks(),
                'detail' => $payload,
            ])
            ->header('Cache-Control', 'no-store, private');
    }

    public function showDiagnostic(Request $request, B2BOperatorPortalQuery $portal, $requestUid)
    {
        $operator = $request->attributes->get('b2b_operator');
        if (!$operator || !isset($operator->id)) {
            return B2BApiResponse::error($request, 'OPERATOR_CONTEXT_MISSING', null, 500);
        }

        $validator = Validator::make([
            'request_uid' => $requestUid,
        ], [
            'request_uid' => 'required|string|min:1|max:191',
        ]);

        if ($validator->fails()) {
            return B2BApiResponse::error($request, 'VALIDATION_FAILED', null, 422, $validator->errors());
        }

        $payload = $portal->providerRequestDetail($request, $requestUid);
        if (!$payload) {
            return B2BApiResponse::error($request, 'PROVIDER_REQUEST_NOT_FOUND');
        }

        return response()
            ->view('b2b.operator-portal.diagnostic', [
                'operator' => $this->operatorViewProfile($operator),
                'portal_section' => [
                    'key' => 'diagnostics',
                    'title' => 'Launch Diagnostic Detail',
                ],
                'portal_sections' => $this->sections,
                'links' => $this->portalLinks(),
                'detail' => $payload,
            ])
            ->header('Cache-Control', 'no-store, private');
    }

    public function showSession(Request $request, B2BOperatorPortalQuery $portal, $sessionUid)
    {
        $operator = $request->attributes->get('b2b_operator');
        if (!$operator || !isset($operator->id)) {
            return B2BApiResponse::error($request, 'OPERATOR_CONTEXT_MISSING', null, 500);
        }

        $validator = Validator::make(array_merge($request->query(), [
            'session_uid' => $sessionUid,
        ]), [
            'session_uid' => 'required|string|min:1|max:191',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        if ($validator->fails()) {
            return B2BApiResponse::error($request, 'VALIDATION_FAILED', null, 422, $validator->errors());
        }

        $payload = $portal->sessionDetail($request, $sessionUid, (int) $request->query('limit', 20));
        if (!$payload) {
            return B2BApiResponse::error($request, 'SESSION_NOT_FOUND');
        }

        return response()
            ->view('b2b.operator-portal.session', [
                'operator' => $this->operatorViewProfile($operator),
                'portal_section' => [
                    'key' => 'sessions',
                    'title' => 'Session Detail',
                ],
                'portal_sections' => $this->sections,
                'links' => $this->portalLinks(),
                'detail' => $payload,
            ])
            ->header('Cache-Control', 'no-store, private');
    }

    public function showTransaction(Request $request, B2BOperatorPortalQuery $portal, $transactionUid)
    {
        $operator = $request->attributes->get('b2b_operator');
        if (!$operator || !isset($operator->id)) {
            return B2BApiResponse::error($request, 'OPERATOR_CONTEXT_MISSING', null, 500);
        }

        $validator = Validator::make(array_merge($request->query(), [
            'transaction_uid' => $transactionUid,
        ]), [
            'transaction_uid' => 'required|string|min:1|max:191',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        if ($validator->fails()) {
            return B2BApiResponse::error($request, 'VALIDATION_FAILED', null, 422, $validator->errors());
        }

        $payload = $portal->transactionDetail($request, $transactionUid, (int) $request->query('limit', 20));
        if (!$payload) {
            return B2BApiResponse::error($request, 'TRANSACTION_NOT_FOUND');
        }

        return response()
            ->view('b2b.operator-portal.transaction', [
                'operator' => $this->operatorViewProfile($operator),
                'portal_section' => [
                    'key' => 'transactions',
                    'title' => 'Transaction Detail',
                ],
                'portal_sections' => $this->sections,
                'links' => $this->portalLinks(),
                'detail' => $payload,
            ])
            ->header('Cache-Control', 'no-store, private');
    }

    public function showSettlement(Request $request, B2BOperatorPortalQuery $portal, $settlementUid)
    {
        $operator = $request->attributes->get('b2b_operator');
        if (!$operator || !isset($operator->id)) {
            return B2BApiResponse::error($request, 'OPERATOR_CONTEXT_MISSING', null, 500);
        }

        $validator = Validator::make([
            'settlement_uid' => $settlementUid,
        ], [
            'settlement_uid' => 'required|string|min:1|max:80',
        ]);

        if ($validator->fails()) {
            return B2BApiResponse::error($request, 'VALIDATION_FAILED', null, 422, $validator->errors());
        }

        $payload = $portal->settlementDetail($request, $settlementUid);
        if (!$payload) {
            return B2BApiResponse::error($request, 'SETTLEMENT_NOT_FOUND');
        }

        return response()
            ->view('b2b.operator-portal.settlement', [
                'operator' => $this->operatorViewProfile($operator),
                'portal_section' => [
                    'key' => 'settlements',
                    'title' => 'Settlement Detail',
                ],
                'portal_sections' => $this->sections,
                'links' => $this->portalLinks(),
                'detail' => $payload,
            ])
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

    public function showCase(Request $request, B2BOperatorSupportCaseService $supportCases, $transactionUid)
    {
        $operator = $request->attributes->get('b2b_operator');
        if (!$operator || !isset($operator->id)) {
            return B2BApiResponse::error($request, 'OPERATOR_CONTEXT_MISSING', null, 500);
        }

        $validator = Validator::make(array_merge($request->query(), [
            'transaction_uid' => $transactionUid,
        ]), [
            'transaction_uid' => 'required|string|min:1|max:191',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return B2BApiResponse::error($request, 'VALIDATION_FAILED', null, 422, $validator->errors());
        }

        try {
            $payload = $supportCases->show($operator, $transactionUid, (int) $request->query('limit', 50));
        } catch (InvalidArgumentException $e) {
            return B2BApiResponse::error($request, 'CASE_NOT_FOUND', $e->getMessage(), 404);
        } catch (RuntimeException $e) {
            return B2BApiResponse::error($request, 'SERVICE_NOT_READY', $e->getMessage(), 503);
        }

        return B2BApiResponse::success($request, $payload);
    }

    public function showCaseThread(Request $request, B2BOperatorSupportCaseService $supportCases, $transactionUid)
    {
        $operator = $request->attributes->get('b2b_operator');
        if (!$operator || !isset($operator->id)) {
            return B2BApiResponse::error($request, 'OPERATOR_CONTEXT_MISSING', null, 500);
        }

        $validator = Validator::make(array_merge($request->query(), [
            'transaction_uid' => $transactionUid,
        ]), [
            'transaction_uid' => 'required|string|min:1|max:191',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return B2BApiResponse::error($request, 'VALIDATION_FAILED', null, 422, $validator->errors());
        }

        try {
            $payload = $supportCases->show($operator, $transactionUid, (int) $request->query('limit', 50));
        } catch (InvalidArgumentException $e) {
            return B2BApiResponse::error($request, 'CASE_NOT_FOUND', $e->getMessage(), 404);
        } catch (RuntimeException $e) {
            return B2BApiResponse::error($request, 'SERVICE_NOT_READY', $e->getMessage(), 503);
        }

        return response()
            ->view('b2b.operator-portal.thread', [
                'operator' => $this->operatorViewProfile($operator),
                'portal_section' => [
                    'key' => 'support',
                    'title' => 'Support Case Thread',
                ],
                'portal_sections' => $this->sections,
                'links' => $this->portalLinks(),
                'thread_type' => 'case',
                'thread' => $payload,
                'detail_endpoint' => '/api/b2b/v1/portal/support/cases/' . rawurlencode($payload['transaction_uid']),
            ])
            ->header('Cache-Control', 'no-store, private');
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

    public function showSupportTicket(Request $request, B2BOperatorSupportTicketService $tickets, $ticketUid)
    {
        $operator = $request->attributes->get('b2b_operator');
        if (!$operator || !isset($operator->id)) {
            return B2BApiResponse::error($request, 'OPERATOR_CONTEXT_MISSING', null, 500);
        }

        $validator = Validator::make(array_merge($request->query(), [
            'ticket_uid' => $ticketUid,
        ]), [
            'ticket_uid' => 'required|string|min:1|max:80',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return B2BApiResponse::error($request, 'VALIDATION_FAILED', null, 422, $validator->errors());
        }

        try {
            $payload = $tickets->show($operator, $ticketUid, (int) $request->query('limit', 50));
        } catch (InvalidArgumentException $e) {
            return B2BApiResponse::error($request, 'SUPPORT_TICKET_NOT_FOUND', $e->getMessage(), 404);
        } catch (RuntimeException $e) {
            return B2BApiResponse::error($request, 'SERVICE_NOT_READY', $e->getMessage(), 503);
        }

        return B2BApiResponse::success($request, $payload);
    }

    public function showSupportTicketThread(Request $request, B2BOperatorSupportTicketService $tickets, $ticketUid)
    {
        $operator = $request->attributes->get('b2b_operator');
        if (!$operator || !isset($operator->id)) {
            return B2BApiResponse::error($request, 'OPERATOR_CONTEXT_MISSING', null, 500);
        }

        $validator = Validator::make(array_merge($request->query(), [
            'ticket_uid' => $ticketUid,
        ]), [
            'ticket_uid' => 'required|string|min:1|max:80',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return B2BApiResponse::error($request, 'VALIDATION_FAILED', null, 422, $validator->errors());
        }

        try {
            $payload = $tickets->show($operator, $ticketUid, (int) $request->query('limit', 50));
        } catch (InvalidArgumentException $e) {
            return B2BApiResponse::error($request, 'SUPPORT_TICKET_NOT_FOUND', $e->getMessage(), 404);
        } catch (RuntimeException $e) {
            return B2BApiResponse::error($request, 'SERVICE_NOT_READY', $e->getMessage(), 503);
        }

        return response()
            ->view('b2b.operator-portal.thread', [
                'operator' => $this->operatorViewProfile($operator),
                'portal_section' => [
                    'key' => 'support',
                    'title' => 'Support Ticket Thread',
                ],
                'portal_sections' => $this->sections,
                'links' => $this->portalLinks(),
                'thread_type' => 'ticket',
                'thread' => $payload,
                'detail_endpoint' => '/api/b2b/v1/portal/support/tickets/' . rawurlencode($payload['ticket_uid']),
            ])
            ->header('Cache-Control', 'no-store, private');
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

    public function reopenSupportTicket(Request $request, B2BOperatorSupportTicketService $tickets, $ticketUid)
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
            $payload = $tickets->reopen($operator, $ticketUid, $request->input('reason'), $this->supportTicketContext($request));
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

    private function operatorViewProfile($operator)
    {
        return [
            'id' => $operator->operator_uid,
            'name' => $operator->name,
            'status' => $operator->status,
            'default_currency' => $operator->default_currency,
        ];
    }

    private function portalLinks()
    {
        return [
            'portal_overview' => '/api/b2b/v1/portal',
            'portal_credentials' => '/api/b2b/v1/portal/credentials',
            'portal_games' => '/api/b2b/v1/portal/games',
            'portal_sessions' => '/api/b2b/v1/portal/sessions',
            'portal_transactions' => '/api/b2b/v1/portal/transactions',
            'portal_settlements' => '/api/b2b/v1/portal/settlements',
            'portal_cases' => '/api/b2b/v1/portal/cases',
            'portal_callbacks' => '/api/b2b/v1/portal/callbacks',
            'portal_diagnostics' => '/api/b2b/v1/portal/diagnostics',
            'portal_reports' => '/api/b2b/v1/portal/reports',
            'portal_support' => '/api/b2b/v1/portal/support',
            'portal_logs' => '/api/b2b/v1/portal/logs',
            'portal_docs' => '/api/b2b/v1/portal/docs',
            'portal_openapi_download' => '/api/b2b/v1/portal/docs/openapi.json',
            'portal_postman_download' => '/api/b2b/v1/portal/docs/postman_collection.json',
            'portal_game_detail_template' => '/api/b2b/v1/portal/games/{game_uid}',
            'portal_diagnostic_detail_template' => '/api/b2b/v1/portal/diagnostics/{request_uid}',
            'portal_session_detail_template' => '/api/b2b/v1/portal/sessions/{session_uid}',
            'portal_transaction_detail_template' => '/api/b2b/v1/portal/transactions/{transaction_uid}',
            'portal_settlement_detail_template' => '/api/b2b/v1/portal/settlements/{settlement_uid}',
        ];
    }

    private function downloadDocumentationArtifact(Request $request, $path, $downloadName)
    {
        if (!is_file($path) || !is_readable($path)) {
            return B2BApiResponse::error($request, 'SERVICE_NOT_READY', 'Documentation artifact is missing.', 503);
        }

        return response(file_get_contents($path), 200, [
            'Cache-Control' => 'no-store, private',
            'Content-Disposition' => 'attachment; filename="' . $downloadName . '"',
            'Content-Type' => 'application/json; charset=utf-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
