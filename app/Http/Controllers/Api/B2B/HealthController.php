<?php

namespace VanguardLTE\Http\Controllers\Api\B2B;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use VanguardLTE\B2B\Services\B2BMetricsExporter;
use VanguardLTE\B2B\Services\B2BReadinessService;
use VanguardLTE\B2B\Support\B2BApiResponse;

class HealthController extends Controller
{
    public function health(Request $request)
    {
        return B2BApiResponse::success($request, [
            'service' => 'bbb-b2b',
            'version' => 'v6-reporting',
            'time' => now()->toIso8601String(),
        ]);
    }

    public function readiness(Request $request, B2BReadinessService $readiness)
    {
        $result = $readiness->check();

        if (!$readiness->isReady($result)) {
            return B2BApiResponse::error($request, 'SERVICE_NOT_READY', null, 503, $result);
        }

        return B2BApiResponse::success($request, $result);
    }

    public function metrics(B2BMetricsExporter $metrics)
    {
        return response($metrics->render(), 200)
            ->header('Content-Type', 'text/plain; version=0.0.4; charset=utf-8')
            ->header('Cache-Control', 'no-store');
    }
}
