<?php

namespace VanguardLTE\Http\Controllers\Api\B2B;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use VanguardLTE\B2B\Models\B2BGameSession;
use VanguardLTE\B2B\Services\B2BLaunchBridge;

class B2BLauncherController extends Controller
{
    public function launch($game, $token, Request $request, B2BLaunchBridge $bridge)
    {
        $session = $bridge->findSessionByPlainToken($game, $token);

        if (!$session) {
            return $this->launchError('SESSION_NOT_FOUND', 'B2B game session was not found.', 404);
        }

        if ($session->status !== B2BGameSession::STATUS_ACTIVE) {
            return $this->launchError('SESSION_NOT_ACTIVE', 'B2B game session is not active.', 403);
        }

        if ($session->expires_at && $session->expires_at->isPast()) {
            $session->update([
                'status' => B2BGameSession::STATUS_EXPIRED,
                'failure_code' => 'SESSION_EXPIRED',
                'failure_message' => 'Player opened launch URL after expiry.',
            ]);

            return $this->launchError('SESSION_EXPIRED', 'B2B game session expired.', 410);
        }

        $session->update(['last_seen_at' => now()]);

        $prepared = $bridge->prepareProviderLaunch($session);
        if (!$prepared['ok']) {
            return $this->launchError($prepared['error_code'], $prepared['error_message'], 502);
        }

        return redirect()->to($prepared['redirect_url']);
    }

    private function launchError($code, $message, $status)
    {
        return response()->view('errors.b2b-launch', [
            'code' => $code,
            'message' => $message,
        ], $status);
    }
}
