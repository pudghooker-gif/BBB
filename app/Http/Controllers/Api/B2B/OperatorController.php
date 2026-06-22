<?php

namespace VanguardLTE\Http\Controllers\Api\B2B;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use VanguardLTE\B2B\Models\B2BGameSession;
use VanguardLTE\B2B\Models\B2BOperatorPlayer;
use VanguardLTE\B2B\Models\B2BWalletTransaction;
use VanguardLTE\B2B\Support\B2BApiResponse;

class OperatorController extends Controller
{
    public function me(Request $request)
    {
        $operator = $request->attributes->get('b2b_operator');
        $apiKey = $request->attributes->get('b2b_api_key');

        if (!$operator) {
            return B2BApiResponse::error($request, 'OPERATOR_CONTEXT_MISSING', null, 500);
        }

        return B2BApiResponse::success($request, [
            'operator' => [
                'id' => $operator->operator_uid,
                'name' => $operator->name,
                'shop_id' => $operator->shop_id,
                'status' => $operator->status,
                'default_currency' => $operator->default_currency,
                'wallet_callback_url' => $operator->wallet_callback_url,
                'max_rps' => isset($operator->max_rps) ? (int) $operator->max_rps : null,
                'wallet_timeout_ms' => isset($operator->wallet_timeout_ms) ? (int) $operator->wallet_timeout_ms : null,
                'connect_timeout_ms' => isset($operator->connect_timeout_ms) ? (int) $operator->connect_timeout_ms : null,
                'failure_count' => isset($operator->failure_count) ? (int) $operator->failure_count : null,
                'circuit_open_until' => $operator->circuit_open_until ? $operator->circuit_open_until->toIso8601String() : null,
            ],
            'api_key' => [
                'key_id' => $apiKey ? $apiKey->key_id : null,
                'last_used_at' => ($apiKey && $apiKey->last_used_at) ? $apiKey->last_used_at->toIso8601String() : null,
            ],
            'counters' => [
                'players' => B2BOperatorPlayer::where('operator_id', $operator->id)->count(),
                'active_sessions' => B2BGameSession::where('operator_id', $operator->id)->where('status', B2BGameSession::STATUS_ACTIVE)->count(),
                'wallet_transactions' => B2BWalletTransaction::where('operator_id', $operator->id)->count(),
            ],
        ]);
    }
}
