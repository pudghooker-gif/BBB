<?php

namespace VanguardLTE\Http\Controllers\Api\B2B;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use RuntimeException;
use VanguardLTE\B2B\Services\B2BOperatorPortalQuery;
use VanguardLTE\B2B\Services\B2BOperatorSupportCaseService;
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
        $payload = $portal->overview($request);
        if (!$payload) {
            return B2BApiResponse::error($request, 'OPERATOR_CONTEXT_MISSING', null, 500);
        }

        return B2BApiResponse::success($request, $payload);
    }

    public function page(Request $request, B2BOperatorPortalQuery $portal)
    {
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

        $validator = Validator::make($request->all(), [
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
}
