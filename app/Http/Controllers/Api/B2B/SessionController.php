<?php

namespace VanguardLTE\Http\Controllers\Api\B2B;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use VanguardLTE\B2B\Models\B2BGameSession;
use VanguardLTE\B2B\Services\B2BLaunchBridge;
use VanguardLTE\B2B\Support\B2BApiResponse;

class SessionController extends Controller
{
    protected function operatorId(Request $request)
    {
        $operator = $request->attributes->get('b2b_operator');
        if ($operator && isset($operator->id)) {
            return (int) $operator->id;
        }
        return 0;
    }

    public function index(Request $request)
    {
        $operatorId = $this->operatorId($request);
        if ($operatorId <= 0) {
            return $this->operatorContextMissing($request);
        }

        $limit = (int) $request->query('limit', 100);
        if ($limit < 1) {
            $limit = 100;
        }
        if ($limit > 1000) {
            $limit = 1000;
        }

        $query = DB::table('b2b_game_sessions')->orderBy('created_at', 'desc')->limit($limit);

        if ($operatorId > 0) {
            $query->where('operator_id', $operatorId);
        }
        if ($request->query('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->query('player_id')) {
            $query->whereIn('operator_player_id', function ($subquery) use ($operatorId, $request) {
                $subquery->select('id')
                    ->from('b2b_operator_players')
                    ->where('operator_id', $operatorId)
                    ->where('external_player_id', $request->query('player_id'));
            });
        }
        if ($request->query('game_id')) {
            $query->where($this->sessionGameColumn(), $request->query('game_id'));
        }

        return B2BApiResponse::success($request, $query->get(), 200, ['limit' => $limit]);
    }

    public function show(Request $request, $sessionUid)
    {
        $operatorId = $this->operatorId($request);
        if ($operatorId <= 0) {
            return $this->operatorContextMissing($request);
        }

        $query = DB::table('b2b_game_sessions');
        $this->applySessionIdentifier($query, $sessionUid);

        if ($operatorId > 0) {
            $query->where('operator_id', $operatorId);
        }

        $session = $query->first();
        if (!$session) {
            return B2BApiResponse::error($request, 'SESSION_NOT_FOUND');
        }

        $transactions = DB::table('b2b_wallet_transactions')
            ->where(function ($q) use ($session) {
                $q->where('session_id', $session->id);
                if (isset($session->session_uid)) {
                    $q->orWhere('session_id', $session->session_uid);
                }
            })
            ->where('operator_id', $operatorId)
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        return B2BApiResponse::success($request, [
            'session' => $session,
            'transactions' => $transactions,
        ]);
    }

    public function close(Request $request, $sessionUid, B2BLaunchBridge $bridge)
    {
        $operatorId = $this->operatorId($request);
        if ($operatorId <= 0) {
            return $this->operatorContextMissing($request);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return B2BApiResponse::error($request, 'VALIDATION_FAILED', null, 422, $validator->errors());
        }

        $query = B2BGameSession::query();
        $this->applySessionIdentifier($query, $sessionUid);
        $query->where('operator_id', $operatorId);

        $session = $query->first();
        if (!$session) {
            return B2BApiResponse::error($request, 'SESSION_NOT_FOUND');
        }

        $reason = $request->input('reason', 'operator_close');

        if ($session->status !== B2BGameSession::STATUS_CLOSED) {
            $closed = $bridge->closeProviderSession($session, $reason);
            if (!isset($closed['ok']) || !$closed['ok']) {
                return B2BApiResponse::error(
                    $request,
                    isset($closed['error_code']) ? $closed['error_code'] : 'SESSION_CLOSE_FAILED',
                    isset($closed['error_message']) ? $closed['error_message'] : null
                );
            }

            $this->persistCloseReason($session->id, $reason);
            $session = $session->fresh();
        }

        return B2BApiResponse::success($request, [
            'message' => 'Session closed',
            'session_uid' => $session->session_uid ? $session->session_uid : $session->id,
            'status' => $session->status,
            'closed_at' => $session->closed_at ? Carbon::parse($session->closed_at)->toIso8601String() : null,
        ]);
    }

    private function sessionGameColumn()
    {
        return Schema::hasColumn('b2b_game_sessions', 'game_uid') ? 'game_uid' : 'game_id';
    }

    private function applySessionIdentifier($query, $sessionUid)
    {
        $query->where(function ($q) use ($sessionUid) {
            $q->where('session_uid', $sessionUid);

            if ($this->isUnsignedIntegerString($sessionUid)) {
                $q->orWhere('id', (int) $sessionUid);
            }
        });
    }

    private function isUnsignedIntegerString($value)
    {
        if (is_int($value)) {
            return $value >= 0;
        }

        return is_string($value) && $value !== '' && ctype_digit($value);
    }

    private function persistCloseReason($sessionId, $reason)
    {
        if (!Schema::hasColumn('b2b_game_sessions', 'close_reason')) {
            return;
        }

        DB::table('b2b_game_sessions')
            ->where('id', $sessionId)
            ->update([
                'close_reason' => $reason,
                'updated_at' => Carbon::now(),
            ]);
    }

    private function operatorContextMissing(Request $request)
    {
        return B2BApiResponse::error($request, 'OPERATOR_CONTEXT_MISSING');
    }
}
