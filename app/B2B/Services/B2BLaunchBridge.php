<?php

namespace VanguardLTE\B2B\Services;

use VanguardLTE\B2B\Models\B2BGameSession;
use VanguardLTE\B2B\Providers\GoldsvetInternalProvider;

class B2BLaunchBridge
{
    protected $goldsvet;

    public function __construct(GoldsvetInternalProvider $goldsvet)
    {
        $this->goldsvet = $goldsvet;
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
        if ($session->provider === 'goldsvet_internal' || !$session->provider) {
            return $this->goldsvet->prepareLaunch($session);
        }

        return [
            'ok' => false,
            'redirect_url' => null,
            'error_code' => 'PROVIDER_NOT_IMPLEMENTED',
            'error_message' => 'Provider adapter is not implemented yet: ' . $session->provider,
        ];
    }

    public function closeProviderSession(B2BGameSession $session, $reason = null)
    {
        if ($session->provider === 'goldsvet_internal' || !$session->provider) {
            return $this->goldsvet->closeSession($session, $reason);
        }

        return [
            'ok' => false,
            'error_code' => 'PROVIDER_NOT_IMPLEMENTED',
            'error_message' => 'Provider adapter is not implemented yet: ' . $session->provider,
        ];
    }
}
