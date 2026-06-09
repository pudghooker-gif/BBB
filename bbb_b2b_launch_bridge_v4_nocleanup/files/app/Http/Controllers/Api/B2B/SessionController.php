<?php

namespace VanguardLTE\Http\Controllers\Api\B2B;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use VanguardLTE\B2B\Models\B2BGameSession;

class SessionController extends Controller
{
    public function show($sessionUid, Request $request)
    {
        $operator = $request->attributes->get('b2b_operator');

        $session = B2BGameSession::where('operator_id', $operator->id)
            ->where('session_uid', $sessionUid)
            ->first();

        if (!$session) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SESSION_NOT_FOUND',
                    'message' => 'Session not found',
                ],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'session_id' => $session->session_uid,
                'player_id' => $session->operator_player_id,
                'game_id' => $session->game_uid,
                'provider' => $session->provider,
                'status' => $session->status,
                'currency' => $session->currency,
                'launch_url' => $session->launch_url,
                'legacy_launch_url' => $session->legacy_launch_url,
                'expires_at' => $session->expires_at ? $session->expires_at->toIso8601String() : null,
                'launched_at' => $session->launched_at ? $session->launched_at->toIso8601String() : null,
                'last_seen_at' => $session->last_seen_at ? $session->last_seen_at->toIso8601String() : null,
                'failure_code' => $session->failure_code,
                'failure_message' => $session->failure_message,
            ],
        ]);
    }
}
