<?php

namespace VanguardLTE\B2B\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class B2BBackofficeDashboardQuery
{
    public function snapshot()
    {
        return [
            'summary' => [
                'operators_total' => $this->count('b2b_operators'),
                'operators_active' => $this->countWhere('b2b_operators', 'status', 'active'),
                'operators_degraded' => $this->countWhere('b2b_operators', 'status', 'degraded'),
                'operator_circuits_open' => $this->countOpenCircuits(),
                'api_keys_active' => $this->countWhere('b2b_operator_api_keys', 'status', 'active'),
                'sessions_active' => $this->countWhere('b2b_game_sessions', 'status', 'active'),
                'wallet_pending' => $this->countWhere('b2b_wallet_transactions', 'status', 'pending'),
                'wallet_unknown' => $this->countWhere('b2b_wallet_transactions', 'status', 'unknown'),
                'wallet_manual_review' => $this->countWhere('b2b_wallet_transactions', 'status', 'manual_review'),
                'reconciliation_open' => $this->countWhere('b2b_wallet_reconciliation_items', 'state', 'open'),
                'settlements_submitted' => $this->countWhere('b2b_settlements', 'status', 'submitted'),
            ],
            'operator_statuses' => $this->groupCounts('b2b_operators', 'status'),
            'wallet_statuses' => $this->groupCounts('b2b_wallet_transactions', 'status'),
            'session_statuses' => $this->groupCounts('b2b_game_sessions', 'status'),
            'settlement_statuses' => $this->groupCounts('b2b_settlements', 'status'),
            'recent_wallet_transactions' => $this->recentWalletTransactions(),
            'recent_reconciliation_items' => $this->recentReconciliationItems(),
        ];
    }

    private function count($table)
    {
        if (!Schema::hasTable($table)) {
            return null;
        }

        return DB::table($table)->count();
    }

    private function countWhere($table, $column, $value)
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return null;
        }

        return DB::table($table)->where($column, $value)->count();
    }

    private function countOpenCircuits()
    {
        if (!Schema::hasTable('b2b_operators') || !Schema::hasColumn('b2b_operators', 'circuit_open_until')) {
            return null;
        }

        return DB::table('b2b_operators')
            ->whereNotNull('circuit_open_until')
            ->where('circuit_open_until', '>', now())
            ->count();
    }

    private function groupCounts($table, $column)
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return [];
        }

        return DB::table($table)
            ->select($column, DB::raw('COUNT(*) as aggregate'))
            ->groupBy($column)
            ->orderBy($column)
            ->pluck('aggregate', $column)
            ->map(function ($value) {
                return (int) $value;
            })
            ->toArray();
    }

    private function recentWalletTransactions()
    {
        if (!Schema::hasTable('b2b_wallet_transactions')) {
            return collect();
        }

        return DB::table('b2b_wallet_transactions')
            ->select('transaction_uid', 'type', 'status', 'currency', 'attempts', 'created_at')
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get();
    }

    private function recentReconciliationItems()
    {
        if (!Schema::hasTable('b2b_wallet_reconciliation_items')) {
            return collect();
        }

        return DB::table('b2b_wallet_reconciliation_items')
            ->select('transaction_uid', 'state', 'status', 'priority', 'reason', 'detected_at')
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get();
    }
}
