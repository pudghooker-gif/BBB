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
    private const DEFAULT_LIMIT = 100;
    private const MAX_LIMIT = 1000;
    private const DEFAULT_SORT = '-created_at';
    private const SORT_OPTIONS = [
        'created_at',
        '-created_at',
        'updated_at',
        '-updated_at',
        'expires_at',
        '-expires_at',
        'status',
        '-status',
        'game_id',
        '-game_id',
        'session_uid',
        '-session_uid',
    ];
    private const STATUS_OPTIONS = [
        B2BGameSession::STATUS_ACTIVE,
        B2BGameSession::STATUS_STALE,
        B2BGameSession::STATUS_EXPIRED,
        B2BGameSession::STATUS_CLOSED,
        B2BGameSession::STATUS_FAILED,
    ];

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

        $filters = $this->validatedIndexFilters($request);
        if (isset($filters['response'])) {
            return $filters['response'];
        }

        $query = DB::table('b2b_game_sessions')
            ->where('operator_id', $operatorId);

        $this->applySessionFilters($query, $operatorId, $filters);
        $matchedCount = (clone $query)->count();
        $this->applySessionSort($query, $filters['sort']);
        $rows = $query->limit($filters['limit'])->get();

        return B2BApiResponse::success($request, $rows, 200, [
            'limit' => $filters['limit'],
            'count' => $rows->count(),
            'matched_count' => $matchedCount,
            'sort' => $filters['sort'],
            'filters' => $this->responseFilters($filters),
        ]);
    }

    public function show(Request $request, $sessionUid)
    {
        $operatorId = $this->operatorId($request);
        if ($operatorId <= 0) {
            return $this->operatorContextMissing($request);
        }

        $validationResponse = $this->validateSessionUid($request, $sessionUid);
        if ($validationResponse) {
            return $validationResponse;
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

        $validator = Validator::make(array_merge($request->all(), [
            'session_uid' => $sessionUid,
        ]), [
            'session_uid' => 'required|string|min:1|max:191',
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

    private function validatedIndexFilters(Request $request)
    {
        $validator = Validator::make($request->query(), [
            'limit' => 'nullable|integer|min:1|max:' . self::MAX_LIMIT,
            'status' => 'nullable|in:' . implode(',', self::STATUS_OPTIONS),
            'player_id' => 'nullable|string|max:191',
            'game_id' => 'nullable|string|max:191',
            'sort' => 'nullable|in:' . implode(',', self::SORT_OPTIONS),
        ]);

        if ($validator->fails()) {
            return [
                'response' => B2BApiResponse::error(
                    $request,
                    'VALIDATION_FAILED',
                    null,
                    422,
                    $validator->errors()
                ),
            ];
        }

        $limit = $request->query('limit');

        return [
            'limit' => $limit === null || $limit === '' ? self::DEFAULT_LIMIT : (int) $limit,
            'status' => $this->normalizedTextFilter($request, 'status'),
            'player_id' => $this->normalizedTextFilter($request, 'player_id'),
            'game_id' => $this->normalizedTextFilter($request, 'game_id'),
            'sort' => $this->normalizedTextFilter($request, 'sort') ?: self::DEFAULT_SORT,
        ];
    }

    private function applySessionFilters($query, $operatorId, array $filters)
    {
        if ($filters['status']) {
            $query->where('status', $filters['status']);
        }

        if ($filters['player_id']) {
            $playerId = $filters['player_id'];
            $query->whereIn('operator_player_id', function ($subquery) use ($operatorId, $playerId) {
                $subquery->select('id')
                    ->from('b2b_operator_players')
                    ->where('operator_id', $operatorId)
                    ->where('external_player_id', $playerId);
            });
        }

        if ($filters['game_id']) {
            $query->where($this->sessionGameColumn(), $filters['game_id']);
        }
    }

    private function applySessionSort($query, $sort)
    {
        list($column, $direction) = $this->sortParts($sort);

        if ($column === 'game_id') {
            $column = $this->sessionGameColumn();
        }

        $query->orderBy($column, $direction);

        if ($column !== 'session_uid') {
            $query->orderBy('session_uid');
        }
    }

    private function sortParts($sort)
    {
        $direction = strpos($sort, '-') === 0 ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        return [$column, $direction];
    }

    private function normalizedTextFilter(Request $request, $key)
    {
        $value = $request->query($key);
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function responseFilters(array $filters)
    {
        return array_filter([
            'status' => $filters['status'],
            'player_id' => $filters['player_id'],
            'game_id' => $filters['game_id'],
        ], function ($value) {
            return $value !== null && $value !== '';
        });
    }

    private function validateSessionUid(Request $request, $sessionUid)
    {
        $validator = Validator::make(['session_uid' => $sessionUid], [
            'session_uid' => 'required|string|min:1|max:191',
        ]);

        if ($validator->fails()) {
            return B2BApiResponse::error($request, 'VALIDATION_FAILED', null, 422, $validator->errors());
        }

        return null;
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
