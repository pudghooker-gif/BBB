<?php

namespace VanguardLTE\B2B\Services;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class B2BOperatorPortalQuery
{
    private $reports;

    public function __construct(B2BReportQuery $reports)
    {
        $this->reports = $reports;
    }

    public function overview(Request $request)
    {
        $operator = $request->attributes->get('b2b_operator');
        if (!$operator || !isset($operator->id)) {
            return null;
        }

        $operatorId = (int) $operator->id;
        list($fromDate, $toDate) = $this->reports->dateRange($request);
        $limit = $this->reports->safeLimit($request, 10, 50);

        return [
            'operator' => $this->operatorProfile($operator),
            'api_key' => $this->apiKeyProfile($request->attributes->get('b2b_api_key')),
            'period' => $this->period($fromDate, $toDate),
            'summary' => $this->summary($operatorId),
            'wallet' => $this->walletSummary($operatorId, $fromDate, $toDate),
            'sessions' => $this->sessionSummary($operatorId),
            'credentials' => $this->credentialSummary($operatorId, $limit),
            'game_assignments' => $this->gameAssignmentSummary($operatorId, $limit),
            'settlements' => $this->settlementSummary($operatorId, $limit),
            'reconciliation' => $this->reconciliationSummary($operatorId, $limit),
            'recent_sessions' => $this->recentSessions($operatorId, $limit),
            'recent_transactions' => $this->recentTransactions($operatorId, $fromDate, $toDate, $limit),
            'links' => [
                'operator_profile' => '/api/b2b/v1/operator/me',
                'games' => '/api/b2b/v1/games',
                'sessions' => '/api/b2b/v1/sessions',
                'transactions' => '/api/b2b/v1/reports/transactions',
                'settlements' => '/api/b2b/v1/reports/settlements',
                'reconciliation' => '/api/b2b/v1/reports/reconciliation',
            ],
        ];
    }

    private function operatorProfile($operator)
    {
        return [
            'id' => $operator->operator_uid,
            'name' => $operator->name,
            'status' => $operator->status,
            'default_currency' => $operator->default_currency,
            'allowed_currencies' => $this->arrayValue($operator->allowed_currencies),
            'allowed_countries' => $this->arrayValue($operator->allowed_countries),
            'base_url' => $operator->base_url,
            'wallet_callback_url' => $operator->wallet_callback_url,
            'wallet_callback_configured' => !empty($operator->wallet_callback_url),
            'max_rps' => isset($operator->max_rps) ? (int) $operator->max_rps : null,
            'wallet_timeout_ms' => isset($operator->wallet_timeout_ms) ? (int) $operator->wallet_timeout_ms : null,
            'connect_timeout_ms' => isset($operator->connect_timeout_ms) ? (int) $operator->connect_timeout_ms : null,
            'failure_count' => isset($operator->failure_count) ? (int) $operator->failure_count : null,
            'circuit_open_until' => $this->isoTime($operator->circuit_open_until),
        ];
    }

    private function apiKeyProfile($apiKey)
    {
        if (!$apiKey) {
            return null;
        }

        return [
            'key_id' => $apiKey->key_id,
            'status' => $apiKey->status,
            'max_rps' => isset($apiKey->max_rps) ? (int) $apiKey->max_rps : null,
            'last_used_at' => $this->isoTime($apiKey->last_used_at),
            'expires_at' => $this->isoTime($apiKey->expires_at),
        ];
    }

    private function summary($operatorId)
    {
        return [
            'players' => $this->countWhere('b2b_operator_players', $operatorId),
            'active_sessions' => $this->countWhere('b2b_game_sessions', $operatorId, function ($query) {
                $query->where('status', 'active');
            }),
            'wallet_transactions' => $this->countWhere('b2b_wallet_transactions', $operatorId),
            'open_reconciliation_items' => $this->countWhere('b2b_wallet_reconciliation_items', $operatorId, function ($query) {
                $query->whereIn('state', ['open', 'in_progress']);
            }),
            'pending_settlements' => $this->countWhere('b2b_settlements', $operatorId, function ($query) {
                $query->whereIn('status', ['draft', 'exported', 'submitted']);
            }),
        ];
    }

    private function walletSummary($operatorId, Carbon $fromDate, Carbon $toDate)
    {
        if (!Schema::hasTable('b2b_wallet_transactions')) {
            return [
                'by_status' => [],
                'by_type' => [],
                'success_amounts' => [],
            ];
        }

        $amountRows = DB::table('b2b_wallet_transactions')
            ->where('operator_id', $operatorId)
            ->where('status', 'success')
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->select('type', 'currency', DB::raw('COALESCE(SUM(amount), 0) as amount'), DB::raw('COUNT(*) as count'))
            ->groupBy('type', 'currency')
            ->orderBy('type')
            ->orderBy('currency')
            ->get();

        $amounts = [];
        foreach ($amountRows as $row) {
            $type = $row->type ?: 'unknown';
            $currency = $row->currency ?: 'UNK';
            if (!isset($amounts[$type])) {
                $amounts[$type] = [];
            }

            $amounts[$type][$currency] = [
                'amount' => $this->decimalNormalize($row->amount),
                'count' => (int) $row->count,
            ];
        }

        return [
            'by_status' => $this->groupCounts('b2b_wallet_transactions', $operatorId, 'status'),
            'by_type' => $this->groupCounts('b2b_wallet_transactions', $operatorId, 'type'),
            'success_amounts' => $amounts,
        ];
    }

    private function sessionSummary($operatorId)
    {
        return [
            'by_status' => $this->groupCounts('b2b_game_sessions', $operatorId, 'status'),
            'by_provider' => $this->groupCounts('b2b_game_sessions', $operatorId, 'provider'),
        ];
    }

    private function credentialSummary($operatorId, $limit)
    {
        if (!Schema::hasTable('b2b_operator_api_keys')) {
            return [
                'by_status' => [],
                'recent_keys' => [],
            ];
        }

        $rows = DB::table('b2b_operator_api_keys')
            ->where('operator_id', $operatorId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get($this->selectExisting('b2b_operator_api_keys', [
                'key_id',
                'status',
                'max_rps',
                'last_used_at',
                'expires_at',
                'created_at',
            ]));

        return [
            'by_status' => $this->groupCounts('b2b_operator_api_keys', $operatorId, 'status'),
            'recent_keys' => $rows->map(function ($row) {
                return [
                    'key_id' => $row->key_id,
                    'status' => $row->status,
                    'max_rps' => isset($row->max_rps) ? (int) $row->max_rps : null,
                    'last_used_at' => $this->isoTime($row->last_used_at),
                    'expires_at' => $this->isoTime($row->expires_at),
                    'created_at' => $this->isoTime($row->created_at),
                ];
            })->values(),
        ];
    }

    private function gameAssignmentSummary($operatorId, $limit)
    {
        if (!Schema::hasTable('b2b_operator_game_assignments')) {
            return [
                'by_status' => [],
                'by_provider' => [],
                'recent_assignments' => [],
            ];
        }

        $rows = DB::table('b2b_operator_game_assignments')
            ->where('operator_id', $operatorId)
            ->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get(['game_uid', 'provider', 'status', 'demo_enabled', 'real_enabled', 'updated_at']);

        return [
            'by_status' => $this->groupCounts('b2b_operator_game_assignments', $operatorId, 'status'),
            'by_provider' => $this->groupCounts('b2b_operator_game_assignments', $operatorId, 'provider'),
            'recent_assignments' => $rows->map(function ($row) {
                return [
                    'game_uid' => $row->game_uid,
                    'provider' => $row->provider,
                    'status' => $row->status,
                    'demo_enabled' => (bool) $row->demo_enabled,
                    'real_enabled' => (bool) $row->real_enabled,
                    'updated_at' => $this->isoTime($row->updated_at),
                ];
            })->values(),
        ];
    }

    private function settlementSummary($operatorId, $limit)
    {
        if (!Schema::hasTable('b2b_settlements')) {
            return [
                'by_status' => [],
                'recent_settlements' => [],
            ];
        }

        $rows = DB::table('b2b_settlements')
            ->where('operator_id', $operatorId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get($this->selectExisting('b2b_settlements', [
                'settlement_uid',
                'currency',
                'status',
                'ggr_amount',
                'net_amount',
                'period_start',
                'period_end',
                'created_at',
            ]));

        return [
            'by_status' => $this->groupCounts('b2b_settlements', $operatorId, 'status'),
            'recent_settlements' => $rows->map(function ($row) {
                return [
                    'settlement_uid' => isset($row->settlement_uid) ? $row->settlement_uid : null,
                    'currency' => isset($row->currency) ? $row->currency : null,
                    'status' => isset($row->status) ? $row->status : null,
                    'ggr_amount' => isset($row->ggr_amount) ? $this->decimalNormalize($row->ggr_amount) : null,
                    'net_amount' => isset($row->net_amount) ? $this->decimalNormalize($row->net_amount) : null,
                    'period_start' => isset($row->period_start) ? $this->isoTime($row->period_start) : null,
                    'period_end' => isset($row->period_end) ? $this->isoTime($row->period_end) : null,
                    'created_at' => isset($row->created_at) ? $this->isoTime($row->created_at) : null,
                ];
            })->values(),
        ];
    }

    private function reconciliationSummary($operatorId, $limit)
    {
        if (!Schema::hasTable('b2b_wallet_reconciliation_items')) {
            return [
                'by_state' => [],
                'by_priority' => [],
                'open_items' => [],
            ];
        }

        $rows = DB::table('b2b_wallet_reconciliation_items')
            ->where('operator_id', $operatorId)
            ->whereIn('state', ['open', 'in_progress'])
            ->orderBy('detected_at')
            ->limit($limit)
            ->get(['transaction_uid', 'status', 'reason', 'priority', 'state', 'detected_at']);

        return [
            'by_state' => $this->groupCounts('b2b_wallet_reconciliation_items', $operatorId, 'state'),
            'by_priority' => $this->groupCounts('b2b_wallet_reconciliation_items', $operatorId, 'priority'),
            'open_items' => $rows->map(function ($row) {
                return [
                    'transaction_uid' => $row->transaction_uid,
                    'status' => $row->status,
                    'reason' => $row->reason,
                    'priority' => $row->priority,
                    'state' => $row->state,
                    'detected_at' => $this->isoTime($row->detected_at),
                ];
            })->values(),
        ];
    }

    private function recentSessions($operatorId, $limit)
    {
        if (!Schema::hasTable('b2b_game_sessions')) {
            return [];
        }

        $query = DB::table('b2b_game_sessions as s')
            ->where('s.operator_id', $operatorId)
            ->orderBy('s.created_at', 'desc')
            ->limit($limit);

        if (Schema::hasTable('b2b_operator_players')) {
            $query->leftJoin('b2b_operator_players as p', 'p.id', '=', 's.operator_player_id');
            $select = [
                's.session_uid',
                's.game_uid',
                's.provider',
                's.mode',
                's.status',
                's.currency',
                's.created_at',
                's.expires_at',
                's.closed_at',
                'p.external_player_id',
            ];
        } else {
            $select = [
                's.session_uid',
                's.game_uid',
                's.provider',
                's.mode',
                's.status',
                's.currency',
                's.created_at',
                's.expires_at',
                's.closed_at',
                DB::raw('NULL as external_player_id'),
            ];
        }

        return $query->get($select)->map(function ($row) {
            return [
                'session_uid' => $row->session_uid,
                'external_player_id' => $row->external_player_id,
                'game_uid' => $row->game_uid,
                'provider' => $row->provider,
                'mode' => $row->mode,
                'status' => $row->status,
                'currency' => $row->currency,
                'created_at' => $this->isoTime($row->created_at),
                'expires_at' => $this->isoTime($row->expires_at),
                'closed_at' => $this->isoTime($row->closed_at),
            ];
        })->values();
    }

    private function recentTransactions($operatorId, Carbon $fromDate, Carbon $toDate, $limit)
    {
        if (!Schema::hasTable('b2b_wallet_transactions')) {
            return [];
        }

        $rows = DB::table('b2b_wallet_transactions')
            ->where('operator_id', $operatorId)
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get($this->selectExisting('b2b_wallet_transactions', [
                'transaction_uid',
                'transaction_id',
                'type',
                'status',
                'amount',
                'currency',
                'session_id',
                'game_uid',
                'round_id',
                'attempts',
                'created_at',
            ]));

        return $rows->map(function ($row) {
            return [
                'transaction_uid' => isset($row->transaction_uid) ? $row->transaction_uid : null,
                'transaction_id' => isset($row->transaction_id) ? $row->transaction_id : null,
                'type' => isset($row->type) ? $row->type : null,
                'status' => isset($row->status) ? $row->status : null,
                'amount' => isset($row->amount) ? $this->decimalNormalize($row->amount) : null,
                'currency' => isset($row->currency) ? $row->currency : null,
                'session_id' => isset($row->session_id) ? $row->session_id : null,
                'game_uid' => isset($row->game_uid) ? $row->game_uid : null,
                'round_id' => isset($row->round_id) ? $row->round_id : null,
                'attempts' => isset($row->attempts) ? (int) $row->attempts : null,
                'created_at' => isset($row->created_at) ? $this->isoTime($row->created_at) : null,
            ];
        })->values();
    }

    private function countWhere($table, $operatorId, callable $callback = null)
    {
        if (!Schema::hasTable($table)) {
            return 0;
        }

        $query = DB::table($table)->where('operator_id', $operatorId);
        if ($callback) {
            $callback($query);
        }

        return (int) $query->count();
    }

    private function groupCounts($table, $operatorId, $column)
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return [];
        }

        $rows = DB::table($table)
            ->where('operator_id', $operatorId)
            ->select($column, DB::raw('COUNT(*) as count'))
            ->groupBy($column)
            ->orderBy($column)
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $key = isset($row->{$column}) && $row->{$column} !== null ? (string) $row->{$column} : 'unknown';
            $result[$key] = ['count' => (int) $row->count];
        }

        return $result;
    }

    private function selectExisting($table, array $columns)
    {
        $select = [];
        foreach ($columns as $column) {
            if (Schema::hasColumn($table, $column)) {
                $select[] = $column;
            } else {
                $select[] = DB::raw('NULL as ' . $column);
            }
        }

        return $select;
    }

    private function period(Carbon $fromDate, Carbon $toDate)
    {
        return [
            'from' => $fromDate->toIso8601String(),
            'to' => $toDate->toIso8601String(),
        ];
    }

    private function arrayValue($value)
    {
        if (is_array($value)) {
            return array_values($value);
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? array_values($decoded) : [];
        }

        return [];
    }

    private function isoTime($value)
    {
        if (!$value) {
            return null;
        }

        try {
            return $value instanceof Carbon
                ? $value->toIso8601String()
                : Carbon::parse($value)->toIso8601String();
        } catch (\Exception $e) {
            return null;
        }
    }

    private function decimalNormalize($value, $scale = 8)
    {
        if ($value === null) {
            return null;
        }

        if (function_exists('bcadd')) {
            return bcadd('0', (string) $value, $scale);
        }

        return number_format((float) $value, $scale, '.', '');
    }
}
