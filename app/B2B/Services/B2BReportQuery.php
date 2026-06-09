<?php

namespace VanguardLTE\B2B\Services;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class B2BReportQuery
{
    public function operatorId(Request $request)
    {
        $operator = $request->attributes->get('b2b_operator');
        if ($operator && isset($operator->id)) {
            return (int) $operator->id;
        }

        return (int) $request->query('operator_id', 0);
    }

    public function dateRange(Request $request)
    {
        $from = $request->query('from');
        $to = $request->query('to');

        try {
            $fromDate = $from ? Carbon::parse($from)->startOfDay() : Carbon::now()->subDays(7)->startOfDay();
        } catch (\Exception $e) {
            $fromDate = Carbon::now()->subDays(7)->startOfDay();
        }

        try {
            $toDate = $to ? Carbon::parse($to)->endOfDay() : Carbon::now()->endOfDay();
        } catch (\Exception $e) {
            $toDate = Carbon::now()->endOfDay();
        }

        return [$fromDate, $toDate];
    }

    public function transactionBaseQuery(Request $request)
    {
        list($fromDate, $toDate) = $this->dateRange($request);
        $operatorId = $this->operatorId($request);

        $query = DB::table('b2b_wallet_transactions')
            ->whereBetween('created_at', [$fromDate, $toDate]);

        if ($operatorId > 0) {
            $query->where('operator_id', $operatorId);
        }

        if ($request->query('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->query('type')) {
            $query->where('type', $request->query('type'));
        }

        if ($request->query('player_id')) {
            $query->where('external_player_id', $request->query('player_id'));
        }

        if ($request->query('game_id')) {
            $query->where('game_id', $request->query('game_id'));
        }

        if ($request->query('round_id')) {
            $query->where('round_id', $request->query('round_id'));
        }

        return $query;
    }

    public function safeLimit(Request $request, $default = 100, $max = 1000)
    {
        $limit = (int) $request->query('limit', $default);
        if ($limit < 1) {
            $limit = $default;
        }
        if ($limit > $max) {
            $limit = $max;
        }
        return $limit;
    }
}
