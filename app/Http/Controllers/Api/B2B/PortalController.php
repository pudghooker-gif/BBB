<?php

namespace VanguardLTE\Http\Controllers\Api\B2B;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use VanguardLTE\B2B\Services\B2BOperatorPortalQuery;
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
}
