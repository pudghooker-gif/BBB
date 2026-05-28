<?php

namespace VanguardLTE\Http\Controllers\Api\B2B;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use VanguardLTE\B2B\Models\B2BGameSession;
use VanguardLTE\B2B\Models\B2BOperatorPlayer;
use VanguardLTE\B2B\Services\B2BResilienceGuard;

class GameLaunchController extends Controller
{
    public function store(Request $request, B2BResilienceGuard $guard)
    {
        $operator = $request->attributes->get('b2b_operator');

        $availability = $guard->checkOperatorAvailable($operator);
        if (!$availability['ok']) {
            return $this->guardError($availability);
        }

        $rate = $guard->checkRateLimit($operator, 'launch');
        if (!$rate['ok']) {
            return $this->guardError($rate);
        }

        $validator = Validator::make($request->all(), [
            'player_id' => 'required|string|max:191',
            'game_id' => 'required|string|max:191',
            'currency' => 'required|string|size:3',
            'language' => 'nullable|string|max:8',
            'country' => 'nullable|string|size:2',
            'mode' => 'nullable|in:real,demo',
            'return_url' => 'nullable|url|max:2048',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_FAILED',
                    'details' => $validator->errors(),
                ],
            ], 422);
        }

        $player = B2BOperatorPlayer::firstOrCreate(
            [
                'operator_id' => $operator->id,
                'external_player_id' => $request->input('player_id'),
            ],
            [
                'currency' => strtoupper($request->input('currency')),
                'country' => strtoupper((string) $request->input('country')),
                'language' => $request->input('language', 'en'),
                'status' => B2BOperatorPlayer::STATUS_ACTIVE,
                'metadata' => $request->input('metadata', []),
            ]
        );

        if ($player->status !== B2BOperatorPlayer::STATUS_ACTIVE) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PLAYER_BLOCKED',
                    'message' => 'Player is not active',
                ],
            ], 403);
        }

        $token = Str::random(64);
        $sessionUid = 'sess_' . Str::uuid()->toString();
        $gameId = $request->input('game_id');
        $launchUrl = url('/launcher/' . $gameId . '/' . $token);

        $session = B2BGameSession::create([
            'operator_id' => $operator->id,
            'operator_player_id' => $player->id,
            'session_uid' => $sessionUid,
            'token_hash' => hash('sha256', $token),
            'game_uid' => $gameId,
            'provider' => 'goldsvet_internal',
            'mode' => $request->input('mode', 'real'),
            'currency' => strtoupper($request->input('currency')),
            'language' => $request->input('language', 'en'),
            'country' => strtoupper((string) $request->input('country')),
            'return_url' => $request->input('return_url'),
            'launch_url' => $launchUrl,
            'status' => B2BGameSession::STATUS_ACTIVE,
            'expires_at' => now()->addMinutes(30),
            'last_seen_at' => now(),
            'heartbeat_timeout_seconds' => 120,
            'metadata' => $request->input('metadata', []),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'session_id' => $session->session_uid,
                'game_id' => $session->game_uid,
                'provider' => $session->provider,
                'launch_url' => $session->launch_url,
                'expires_at' => $session->expires_at ? $session->expires_at->toIso8601String() : null,
            ],
        ], 201);
    }

    private function guardError(array $result)
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $result['code'],
                'message' => $result['message'],
            ],
            'retry_after' => isset($result['retry_after']) ? $result['retry_after'] : null,
        ], isset($result['http_status']) ? $result['http_status'] : 503);
    }
}
