<?php

namespace VanguardLTE\Http\Controllers\Api\B2B;

use Illuminate\Http\Request;
use VanguardLTE\Http\Controllers\Controller;
use VanguardLTE\B2B\Services\B2BContext;
use VanguardLTE\B2B\Services\OperatorCircuitBreaker;
use VanguardLTE\B2B\Support\B2BApiResponse;

class WalletHealthController extends Controller
{
    public function show(Request $request, OperatorCircuitBreaker $breaker)
    {
        $operator = B2BContext::operator($request);
        if (!$operator) {
            return B2BApiResponse::error($request, 'OPERATOR_NOT_FOUND');
        }

        return B2BApiResponse::success($request, [
            'operator_id' => isset($operator->id) ? $operator->id : null,
            'wallet_callback_configured' => !empty($operator->wallet_callback_url) || !empty($operator->callback_url),
            'wallet_timeout_ms' => isset($operator->wallet_timeout_ms) ? (int) $operator->wallet_timeout_ms : 5000,
            'failure_count' => isset($operator->failure_count) ? (int) $operator->failure_count : 0,
            'circuit_open' => $breaker->isOpen($operator),
            'circuit_open_until' => isset($operator->circuit_open_until) ? $operator->circuit_open_until : null,
        ]);
    }
}
