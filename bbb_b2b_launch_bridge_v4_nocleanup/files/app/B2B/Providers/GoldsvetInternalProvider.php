<?php

namespace VanguardLTE\B2B\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use VanguardLTE\B2B\Contracts\GameProviderInterface;
use VanguardLTE\B2B\Models\B2BGameSession;
use VanguardLTE\B2B\Services\ShadowUserManager;

class GoldsvetInternalProvider implements GameProviderInterface
{
    protected $shadowUsers;

    public function __construct(ShadowUserManager $shadowUsers)
    {
        $this->shadowUsers = $shadowUsers;
    }

    public function prepareLaunch(B2BGameSession $session)
    {
        $operator = $session->operator;
        $player = $session->player;

        if (!$operator || !$player) {
            return $this->fail('SESSION_RELATION_MISSING', 'Operator or player relation is missing for this session.');
        }

        if ($session->status !== B2BGameSession::STATUS_ACTIVE) {
            return $this->fail('SESSION_NOT_ACTIVE', 'B2B session is not active.');
        }

        if ($session->expires_at && $session->expires_at->isPast()) {
            $session->update([
                'status' => B2BGameSession::STATUS_EXPIRED,
                'failure_code' => 'SESSION_EXPIRED',
                'failure_message' => 'Session expired before launch.',
            ]);
            return $this->fail('SESSION_EXPIRED', 'B2B session expired.');
        }

        if (!$this->gameLooksAvailable($session->game_uid, $operator->shop_id)) {
            return $this->fail('GAME_NOT_AVAILABLE', 'Game was not found for this shop or is not enabled.');
        }

        try {
            $user = $this->shadowUsers->ensureShadowUser($operator, $player);
            $legacyToken = $this->shadowUsers->refreshApiToken($user);
        } catch (\Exception $e) {
            $session->update([
                'status' => B2BGameSession::STATUS_FAILED,
                'failure_code' => 'SHADOW_USER_FAILED',
                'failure_message' => $e->getMessage(),
            ]);
            return $this->fail('SHADOW_USER_FAILED', $e->getMessage());
        }

        $legacyUrl = url('/launcher/' . $session->game_uid . '/' . $legacyToken);

        $session->update([
            'shadow_user_id' => $user->id,
            'legacy_launch_token' => $legacyToken,
            'legacy_launch_url' => $legacyUrl,
            'launched_at' => now(),
            'last_seen_at' => now(),
            'launch_attempts' => DB::raw('COALESCE(launch_attempts, 0) + 1'),
        ]);

        return [
            'ok' => true,
            'redirect_url' => $legacyUrl,
            'error_code' => null,
            'error_message' => null,
        ];
    }

    private function gameLooksAvailable($gameUid, $shopId)
    {
        if (!Schema::hasTable('games')) {
            return true;
        }

        $query = DB::table('games');
        $columns = Schema::getColumnListing('games');

        if (in_array('name', $columns, true)) {
            $query->where('name', $gameUid);
        } elseif (in_array('title', $columns, true)) {
            $query->where('title', $gameUid);
        } elseif (in_array('game_uid', $columns, true)) {
            $query->where('game_uid', $gameUid);
        } else {
            return true;
        }

        if ($shopId && in_array('shop_id', $columns, true)) {
            $query->where(function ($q) use ($shopId) {
                $q->where('shop_id', $shopId)->orWhereNull('shop_id');
            });
        }

        if (in_array('view', $columns, true)) {
            $query->where('view', 1);
        }

        return $query->exists();
    }

    private function fail($code, $message)
    {
        return [
            'ok' => false,
            'redirect_url' => null,
            'error_code' => $code,
            'error_message' => $message,
        ];
    }
}
