<?php

namespace VanguardLTE\Http\Controllers\Api\B2B;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class SessionController extends Controller
{
    protected function operatorId(Request $request)
    {
        $operator = $request->attributes->get('b2b_operator');
        if ($operator && isset($operator->id)) {
            return (int) $operator->id;
        }
        return (int) $request->query('operator_id', 0);
    }

    public function index(Request $request)
    {
        $operatorId = $this->operatorId($request);
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
            $query->where('external_player_id', $request->query('player_id'));
        }
        if ($request->query('game_id')) {
            $query->where('game_id', $request->query('game_id'));
        }

        return response()->json([
            'status' => 'ok',
            'data' => $query->get(),
            'limit' => $limit,
        ]);
    }

    public function show(Request $request, $sessionUid)
    {
        $operatorId = $this->operatorId($request);

        $query = DB::table('b2b_game_sessions')
            ->where(function ($q) use ($sessionUid) {
                $q->where('session_uid', $sessionUid)
                  ->orWhere('id', $sessionUid);
            });

        if ($operatorId > 0) {
            $query->where('operator_id', $operatorId);
        }

        $session = $query->first();
        if (!$session) {
            return response()->json([
                'status' => 'error',
                'code' => 'SESSION_NOT_FOUND',
            ], 404);
        }

        $transactions = DB::table('b2b_wallet_transactions')
            ->where('session_id', $session->id)
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        return response()->json([
            'status' => 'ok',
            'data' => [
                'session' => $session,
                'transactions' => $transactions,
            ],
        ]);
    }

    public function close(Request $request, $sessionUid)
    {
        $operatorId = $this->operatorId($request);

        $query = DB::table('b2b_game_sessions')
            ->where(function ($q) use ($sessionUid) {
                $q->where('session_uid', $sessionUid)
                  ->orWhere('id', $sessionUid);
            });

        if ($operatorId > 0) {
            $query->where('operator_id', $operatorId);
        }

        $session = $query->first();
        if (!$session) {
            return response()->json([
                'status' => 'error',
                'code' => 'SESSION_NOT_FOUND',
            ], 404);
        }

        DB::table('b2b_game_sessions')
            ->where('id', $session->id)
            ->update([
                'status' => 'closed',
                'closed_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

        return response()->json([
            'status' => 'ok',
            'message' => 'Session closed',
            'session_uid' => isset($session->session_uid) ? $session->session_uid : $session->id,
        ]);
    }
}
