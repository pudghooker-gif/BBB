<?php

namespace VanguardLTE\Http\Controllers\Api\B2B;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use VanguardLTE\B2B\Services\B2BOperatorPortalQuery;
use VanguardLTE\B2B\Support\B2BApiResponse;

class PortalController extends Controller
{
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
}
