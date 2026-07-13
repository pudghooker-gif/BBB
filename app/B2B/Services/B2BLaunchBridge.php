<?php

namespace VanguardLTE\B2B\Services;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use VanguardLTE\B2B\Models\B2BGameSession;
use VanguardLTE\B2B\Models\B2BProviderRequest;
use VanguardLTE\B2B\Providers\GoldsvetInternalProvider;
use Throwable;

class B2BLaunchBridge
{
    protected $goldsvet;
    protected $redactor;

    public function __construct(GoldsvetInternalProvider $goldsvet, B2BPayloadRedactor $redactor)
    {
        $this->goldsvet = $goldsvet;
        $this->redactor = $redactor;
    }

    public function publicLaunchUrl($gameUid, $token)
    {
        return url('/b2b/launcher/' . $gameUid . '/' . $token);
    }

    public function findSessionByPlainToken($gameUid, $plainToken)
    {
        return B2BGameSession::where('game_uid', $gameUid)
            ->where('token_hash', hash('sha256', $plainToken))
            ->first();
    }

    public function prepareProviderLaunch(B2BGameSession $session)
    {
        $started = microtime(true);
        try {
            if ($session->provider === 'goldsvet_internal' || !$session->provider) {
                $result = $this->goldsvet->prepareLaunch($session);
            } else {
                $result = [
                    'ok' => false,
                    'redirect_url' => null,
                    'error_code' => 'PROVIDER_NOT_IMPLEMENTED',
                    'error_message' => 'Provider adapter is not implemented yet: ' . $session->provider,
                ];
            }

            $this->recordProviderRequest($session, 'launch', $this->sessionRequestPayload($session), $result, $started);

            return $result;
        } catch (Throwable $e) {
            $this->recordProviderRequest($session, 'launch', $this->sessionRequestPayload($session), [
                'ok' => false,
                'error_code' => 'PROVIDER_EXCEPTION',
                'error_message' => $e->getMessage(),
            ], $started);

            throw $e;
        }
    }

    public function closeProviderSession(B2BGameSession $session, $reason = null)
    {
        $started = microtime(true);
        $requestPayload = array_merge($this->sessionRequestPayload($session), [
            'reason' => $reason,
        ]);

        try {
            if ($session->provider === 'goldsvet_internal' || !$session->provider) {
                $result = $this->goldsvet->closeSession($session, $reason);
            } else {
                $result = [
                    'ok' => false,
                    'error_code' => 'PROVIDER_NOT_IMPLEMENTED',
                    'error_message' => 'Provider adapter is not implemented yet: ' . $session->provider,
                ];
            }

            $this->recordProviderRequest($session, 'close_session', $requestPayload, $result, $started);

            return $result;
        } catch (Throwable $e) {
            $this->recordProviderRequest($session, 'close_session', $requestPayload, [
                'ok' => false,
                'error_code' => 'PROVIDER_EXCEPTION',
                'error_message' => $e->getMessage(),
            ], $started);

            throw $e;
        }
    }

    private function sessionRequestPayload(B2BGameSession $session)
    {
        return [
            'session_uid' => $session->session_uid,
            'game_uid' => $session->game_uid,
            'provider' => $session->provider ?: 'goldsvet_internal',
            'mode' => $session->mode,
            'currency' => $session->currency,
            'country' => $session->country,
        ];
    }

    private function recordProviderRequest(B2BGameSession $session, $action, array $requestPayload, array $result, $started)
    {
        if (!Schema::hasTable('b2b_provider_requests')) {
            return;
        }

        try {
            B2BProviderRequest::create([
                'operator_id' => $session->operator_id,
                'provider' => $session->provider ?: 'goldsvet_internal',
                'game_uid' => $session->game_uid,
                'session_id' => $session->session_uid ?: (string) $session->id,
                'request_uid' => 'pr_' . Str::uuid()->toString(),
                'action' => $action,
                'status' => isset($result['ok']) && $result['ok'] ? 'success' : 'failed',
                'request_payload' => $this->redactor->redact($requestPayload),
                'response_payload' => $this->providerResponsePayload($result),
                'error_message' => isset($result['error_message']) ? $this->redactor->storageValue($result['error_message']) : null,
                'duration_ms' => (int) max(0, round((microtime(true) - $started) * 1000)),
            ]);
        } catch (Throwable $e) {
            // Diagnostics must not block player launch or operator session close.
        }
    }

    private function providerResponsePayload(array $result)
    {
        $payload = [
            'ok' => isset($result['ok']) ? (bool) $result['ok'] : false,
            'redirect_prepared' => !empty($result['redirect_url']),
            'error_code' => isset($result['error_code']) ? $result['error_code'] : null,
            'error_message' => isset($result['error_message']) ? $result['error_message'] : null,
        ];

        return $this->redactor->redact($payload);
    }
}
