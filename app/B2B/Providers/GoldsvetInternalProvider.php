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
    protected $walletActionContracts = [
        'balance' => [
            'request_fields' => ['player_id', 'currency'],
            'response_fields' => ['status', 'balance', 'currency'],
            'terminal_statuses' => ['success', 'failed'],
        ],
        'bet' => [
            'request_fields' => ['player_id', 'game_id', 'session_id', 'round_id', 'transaction_id', 'amount', 'currency'],
            'response_fields' => ['status', 'balance', 'currency'],
            'terminal_statuses' => ['success', 'failed', 'rollback_required'],
        ],
        'win' => [
            'request_fields' => ['player_id', 'game_id', 'session_id', 'round_id', 'transaction_id', 'amount', 'currency'],
            'response_fields' => ['status', 'balance', 'currency'],
            'terminal_statuses' => ['success', 'failed'],
        ],
        'refund' => [
            'request_fields' => ['player_id', 'game_id', 'session_id', 'round_id', 'transaction_id', 'amount', 'currency'],
            'response_fields' => ['status', 'balance', 'currency'],
            'terminal_statuses' => ['success', 'failed', 'reversed'],
        ],
        'rollback' => [
            'request_fields' => ['transaction_id', 'original_transaction_id', 'original_transaction_uid', 'round_id', 'session_id', 'game_id', 'amount', 'currency', 'recovery_reason', 'recovery_attempt'],
            'response_fields' => ['status'],
            'terminal_statuses' => ['accepted', 'success', 'ok', 'failed'],
            'idempotency_key' => 'transaction_id',
            'resolved_wallet_status' => 'reversed',
        ],
        'transaction_status' => [
            'request_fields' => ['transaction_uid', 'transaction_id', 'idempotency_key', 'round_id', 'session_id', 'game_uid', 'type', 'amount', 'currency', 'current_status'],
            'response_fields' => ['transaction_status', 'status', 'state'],
            'final_statuses' => ['success', 'failed', 'rollback_required', 'reversed'],
            'ambiguous_statuses' => ['pending', 'processing', 'unknown', 'not_found'],
        ],
    ];

    public function __construct(ShadowUserManager $shadowUsers)
    {
        $this->shadowUsers = $shadowUsers;
    }

    public function providerCode()
    {
        return 'goldsvet_internal';
    }

    public function health()
    {
        return [
            'ok' => true,
            'provider' => $this->providerCode(),
            'games_table_available' => Schema::hasTable('games'),
            'checked_at' => now()->toIso8601String(),
        ];
    }

    public function supportsWalletAction($action)
    {
        return array_key_exists((string) $action, $this->walletActionContracts);
    }

    public function walletActionContracts()
    {
        return $this->walletActionContracts;
    }

    public function walletActionContract($action)
    {
        $action = (string) $action;

        return isset($this->walletActionContracts[$action]) ? $this->walletActionContracts[$action] : null;
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

    public function refreshSession(B2BGameSession $session)
    {
        if ($session->status !== B2BGameSession::STATUS_ACTIVE) {
            return $this->fail('SESSION_NOT_ACTIVE', 'B2B session is not active.');
        }

        $session->forceFill(['last_seen_at' => now()])->save();

        return [
            'ok' => true,
            'session_uid' => $session->session_uid,
            'status' => $session->status,
            'last_seen_at' => $session->last_seen_at ? $session->last_seen_at->toIso8601String() : null,
        ];
    }

    public function closeSession(B2BGameSession $session, $reason = null)
    {
        if ($session->status === B2BGameSession::STATUS_CLOSED) {
            return [
                'ok' => true,
                'session_uid' => $session->session_uid,
                'status' => $session->status,
                'closed_at' => $session->closed_at ? $session->closed_at->toIso8601String() : null,
            ];
        }

        $updates = [
            'status' => B2BGameSession::STATUS_CLOSED,
            'closed_at' => now(),
            'failure_message' => $reason,
        ];

        if (Schema::hasColumn('b2b_game_sessions', 'close_reason')) {
            $updates['close_reason'] = $reason;
        }

        $session->forceFill($updates)->save();

        return [
            'ok' => true,
            'session_uid' => $session->session_uid,
            'status' => $session->status,
            'closed_at' => $session->closed_at ? $session->closed_at->toIso8601String() : null,
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
