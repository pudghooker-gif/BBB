<?php

namespace VanguardLTE\Http\Controllers\Api\B2B;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use VanguardLTE\B2B\Models\B2BGameSession;
use VanguardLTE\B2B\Models\B2BOperatorPlayer;
use VanguardLTE\B2B\Services\B2BGameAvailabilityService;
use VanguardLTE\B2B\Services\B2BLaunchBridge;
use VanguardLTE\B2B\Services\B2BResilienceGuard;
use VanguardLTE\B2B\Support\B2BApiResponse;

class GameLaunchController extends Controller
{
    public function store(Request $request, B2BResilienceGuard $guard, B2BLaunchBridge $bridge, B2BGameAvailabilityService $games)
    {
        $operator = $request->attributes->get('b2b_operator');

        $availability = $guard->checkOperatorAvailable($operator);
        if (!$availability['ok']) {
            return $this->guardError($request, $availability);
        }

        $rate = $guard->checkRateLimit($operator, 'launch');
        if (!$rate['ok']) {
            return $this->guardError($request, $rate);
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
            return B2BApiResponse::error($request, 'VALIDATION_FAILED', null, 422, $validator->errors());
        }

        $currency = strtoupper($request->input('currency'));
        if (!$this->isCurrencyAllowed($operator, $currency)) {
            return B2BApiResponse::error($request, 'CURRENCY_NOT_ALLOWED');
        }

        if (!$this->isReturnUrlAllowed($operator, $request->input('return_url'))) {
            return B2BApiResponse::error($request, 'RETURN_URL_NOT_ALLOWED');
        }

        $mode = $request->input('mode', 'real');
        $gameId = $request->input('game_id');
        $country = strtoupper((string) $request->input('country'));
        $gameAvailability = $games->availableForLaunch($operator, $gameId, $currency, $country, $mode);
        if (!$gameAvailability['ok']) {
            return B2BApiResponse::error(
                $request,
                $gameAvailability['code'],
                isset($gameAvailability['message']) ? $gameAvailability['message'] : null
            );
        }

        $player = B2BOperatorPlayer::firstOrCreate(
            [
                'operator_id' => $operator->id,
                'external_player_id' => $request->input('player_id'),
            ],
            [
                'currency' => $currency,
                'country' => strtoupper((string) $request->input('country')),
                'language' => $request->input('language', 'en'),
                'status' => B2BOperatorPlayer::STATUS_ACTIVE,
                'metadata' => $request->input('metadata', []),
            ]
        );

        if ($player->status !== B2BOperatorPlayer::STATUS_ACTIVE) {
            return B2BApiResponse::error($request, 'PLAYER_BLOCKED');
        }

        $token = Str::random(64);
        $sessionUid = 'sess_' . Str::uuid()->toString();
        $launchUrl = $bridge->publicLaunchUrl($gameId, $token);

        $session = B2BGameSession::create([
            'operator_id' => $operator->id,
            'operator_player_id' => $player->id,
            'session_uid' => $sessionUid,
            'token_hash' => hash('sha256', $token),
            'game_uid' => $gameId,
            'provider' => isset($gameAvailability['provider']) ? $gameAvailability['provider'] : 'goldsvet_internal',
            'mode' => $mode,
            'currency' => $currency,
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

        return B2BApiResponse::success($request, [
            'session_id' => $session->session_uid,
            'game_id' => $session->game_uid,
            'provider' => $session->provider,
            'launch_url' => $session->launch_url,
            'expires_at' => $session->expires_at ? $session->expires_at->toIso8601String() : null,
        ], 201);
    }

    private function guardError(Request $request, array $result)
    {
        $meta = [];
        if (isset($result['retry_after'])) {
            $meta['retry_after'] = $result['retry_after'];
        }

        return B2BApiResponse::error(
            $request,
            $result['code'],
            isset($result['message']) ? $result['message'] : null,
            isset($result['http_status']) ? $result['http_status'] : 503,
            null,
            $meta
        );
    }

    private function isCurrencyAllowed($operator, $currency)
    {
        $allowed = $operator && is_array($operator->allowed_currencies)
            ? $operator->allowed_currencies
            : [];

        if (count($allowed) === 0) {
            return true;
        }

        return in_array(strtoupper($currency), array_map('strtoupper', $allowed), true);
    }

    private function isReturnUrlAllowed($operator, $returnUrl)
    {
        if (!$returnUrl) {
            return true;
        }

        $host = parse_url($returnUrl, PHP_URL_HOST);
        if (!$host) {
            return false;
        }

        $allowedHosts = [];
        if ($operator && $operator->base_url) {
            $baseHost = parse_url($operator->base_url, PHP_URL_HOST);
            if ($baseHost) {
                $allowedHosts[] = strtolower($baseHost);
            }
        }

        $settings = $operator && is_array($operator->settings) ? $operator->settings : [];
        if (isset($settings['return_url_allowlist']) && is_array($settings['return_url_allowlist'])) {
            foreach ($settings['return_url_allowlist'] as $allowed) {
                $allowedHost = parse_url($allowed, PHP_URL_HOST) ?: $allowed;
                if ($allowedHost) {
                    $allowedHosts[] = strtolower($allowedHost);
                }
            }
        }

        $host = strtolower($host);
        foreach (array_unique($allowedHosts) as $allowedHost) {
            if ($host === $allowedHost) {
                return true;
            }

            if (strpos($allowedHost, '*.') === 0) {
                $suffix = substr($allowedHost, 1);
                if (substr($host, -strlen($suffix)) === $suffix) {
                    return true;
                }
            }
        }

        return false;
    }
}
