<?php

namespace VanguardLTE\B2B\Services;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class B2BReconciliationReportQuery
{
    protected $reports;

    public function __construct(B2BReportQuery $reports)
    {
        $this->reports = $reports;
    }

    public function build(Request $request)
    {
        $operatorId = $this->reports->operatorId($request);
        list($fromDate, $toDate) = $this->reports->dateRange($request);

        if ($operatorId <= 0) {
            return null;
        }

        if (!Schema::hasTable('b2b_wallet_reconciliation_items')) {
            return [
                'period' => $this->period($fromDate, $toDate),
                'totals' => $this->emptyTotals(),
                'by_state' => [],
                'by_reason' => [],
                'by_priority' => [],
                'open_exposure' => [],
                'aging_buckets' => $this->emptyAgingBuckets(),
                'oldest_open_items' => [],
                'message' => 'b2b_wallet_reconciliation_items table missing',
            ];
        }

        $base = $this->baseQuery($operatorId, $fromDate, $toDate, $request);
        $byState = $this->groupCounts(clone $base, 'state');
        $byReason = $this->groupCounts(clone $base, 'reason');
        $byPriority = $this->groupCounts(clone $base, 'priority');
        $openItems = $this->openItems($operatorId, $fromDate, $toDate, $request);
        $openExposure = $this->openExposure($operatorId, $fromDate, $toDate, $request);

        return [
            'period' => $this->period($fromDate, $toDate),
            'totals' => $this->totals($byState, $openItems, $openExposure),
            'by_state' => $byState,
            'by_reason' => $byReason,
            'by_priority' => $byPriority,
            'open_exposure' => $openExposure,
            'aging_buckets' => $this->agingBuckets($openItems),
            'oldest_open_items' => $this->oldestOpenItems($operatorId, $fromDate, $toDate, $request),
        ];
    }

    private function baseQuery($operatorId, Carbon $fromDate, Carbon $toDate, Request $request)
    {
        $query = DB::table('b2b_wallet_reconciliation_items')
            ->where('operator_id', $operatorId)
            ->whereBetween('detected_at', [$fromDate, $toDate]);

        foreach (['state', 'reason', 'priority'] as $filter) {
            $value = $request->query($filter);
            if ($value !== null && $value !== '') {
                $query->where($filter, $value);
            }
        }

        $this->applyTransactionSubqueryFilters($query, $operatorId, $request);

        return $query;
    }

    private function groupCounts($query, $column)
    {
        $rows = $query
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

    private function openItems($operatorId, Carbon $fromDate, Carbon $toDate, Request $request)
    {
        return $this->baseQuery($operatorId, $fromDate, $toDate, $request)
            ->whereIn('state', ['open', 'in_progress'])
            ->orderBy('detected_at')
            ->get();
    }

    private function openExposure($operatorId, Carbon $fromDate, Carbon $toDate, Request $request)
    {
        if (!Schema::hasTable('b2b_wallet_transactions')) {
            return [];
        }

        $query = DB::table('b2b_wallet_reconciliation_items as ri')
            ->join('b2b_wallet_transactions as wt', 'wt.id', '=', 'ri.wallet_transaction_id')
            ->where('ri.operator_id', $operatorId)
            ->whereIn('ri.state', ['open', 'in_progress'])
            ->whereBetween('ri.detected_at', [$fromDate, $toDate]);

        foreach (['state', 'reason', 'priority'] as $filter) {
            $value = $request->query($filter);
            if ($value !== null && $value !== '') {
                $query->where('ri.'.$filter, $value);
            }
        }

        $this->applyJoinedTransactionFilters($query, $request);

        $rows = $query
            ->select('wt.id', 'wt.currency', 'wt.amount')
            ->groupBy('wt.id', 'wt.currency', 'wt.amount')
            ->orderBy('wt.currency')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $currency = isset($row->currency) && $row->currency ? strtoupper((string) $row->currency) : 'UNK';
            if (!isset($result[$currency])) {
                $result[$currency] = [
                    'amount' => '0.00000000',
                    'transactions' => 0,
                ];
            }

            $result[$currency]['amount'] = $this->decimalAdd($result[$currency]['amount'], $row->amount);
            $result[$currency]['transactions']++;
        }

        ksort($result);

        return $result;
    }

    private function oldestOpenItems($operatorId, Carbon $fromDate, Carbon $toDate, Request $request)
    {
        if (!Schema::hasTable('b2b_wallet_transactions')) {
            return [];
        }

        $limit = $this->reports->safeLimit($request, 20, 100);
        $query = DB::table('b2b_wallet_reconciliation_items as ri')
            ->leftJoin('b2b_wallet_transactions as wt', 'wt.id', '=', 'ri.wallet_transaction_id')
            ->where('ri.operator_id', $operatorId)
            ->whereIn('ri.state', ['open', 'in_progress'])
            ->whereBetween('ri.detected_at', [$fromDate, $toDate]);

        foreach (['state', 'reason', 'priority'] as $filter) {
            $value = $request->query($filter);
            if ($value !== null && $value !== '') {
                $query->where('ri.'.$filter, $value);
            }
        }

        $this->applyJoinedTransactionFilters($query, $request);

        $rows = $query
            ->orderBy('ri.detected_at')
            ->orderBy('ri.id')
            ->limit($limit)
            ->get([
                'ri.wallet_transaction_id',
                'ri.transaction_uid',
                'ri.status',
                'ri.reason',
                'ri.priority',
                'ri.state',
                'ri.detected_at',
                'wt.type',
                'wt.amount',
                'wt.currency',
                'wt.round_id',
                'wt.session_id',
            ]);

        return $rows->map(function ($row) {
            $detectedAt = isset($row->detected_at) && $row->detected_at
                ? Carbon::parse($row->detected_at)
                : null;

            return [
                'wallet_transaction_id' => isset($row->wallet_transaction_id) ? (int) $row->wallet_transaction_id : null,
                'transaction_uid' => isset($row->transaction_uid) ? $row->transaction_uid : null,
                'status' => isset($row->status) ? $row->status : null,
                'reason' => isset($row->reason) ? $row->reason : null,
                'priority' => isset($row->priority) ? $row->priority : null,
                'state' => isset($row->state) ? $row->state : null,
                'type' => isset($row->type) ? $row->type : null,
                'amount' => isset($row->amount) ? $this->decimalNormalize($row->amount) : null,
                'currency' => isset($row->currency) ? $row->currency : null,
                'round_id' => isset($row->round_id) ? $row->round_id : null,
                'session_id' => isset($row->session_id) ? $row->session_id : null,
                'detected_at' => $detectedAt ? $detectedAt->toIso8601String() : null,
                'age_minutes' => $detectedAt ? max(0, $detectedAt->diffInMinutes(Carbon::now())) : null,
            ];
        })->values();
    }

    private function agingBuckets($openItems)
    {
        $buckets = $this->emptyAgingBuckets();
        $now = Carbon::now();

        foreach ($openItems as $item) {
            $detectedAt = isset($item->detected_at) && $item->detected_at
                ? Carbon::parse($item->detected_at)
                : null;
            if (!$detectedAt) {
                $buckets['unknown']['count']++;
                continue;
            }

            $ageMinutes = $detectedAt->diffInMinutes($now);
            if ($ageMinutes < 60) {
                $buckets['lt_1h']['count']++;
            } elseif ($ageMinutes < 1440) {
                $buckets['1h_24h']['count']++;
            } elseif ($ageMinutes < 4320) {
                $buckets['1d_3d']['count']++;
            } else {
                $buckets['gt_3d']['count']++;
            }
        }

        return $buckets;
    }

    private function totals(array $byState, $openItems, array $openExposure)
    {
        $totalItems = 0;
        foreach ($byState as $state) {
            $totalItems += isset($state['count']) ? (int) $state['count'] : 0;
        }

        $unresolvedTransactions = 0;
        foreach ($openExposure as $currency) {
            $unresolvedTransactions += isset($currency['transactions']) ? (int) $currency['transactions'] : 0;
        }

        $highPriorityOpen = 0;
        foreach ($openItems as $item) {
            if (isset($item->priority) && $item->priority === 'high') {
                $highPriorityOpen++;
            }
        }

        return [
            'items' => $totalItems,
            'open' => isset($byState['open']['count']) ? (int) $byState['open']['count'] : 0,
            'in_progress' => isset($byState['in_progress']['count']) ? (int) $byState['in_progress']['count'] : 0,
            'resolved' => isset($byState['resolved']['count']) ? (int) $byState['resolved']['count'] : 0,
            'high_priority_open' => $highPriorityOpen,
            'unresolved_items' => $openItems->count(),
            'unresolved_transactions' => $unresolvedTransactions,
        ];
    }

    private function emptyTotals()
    {
        return [
            'items' => 0,
            'open' => 0,
            'in_progress' => 0,
            'resolved' => 0,
            'high_priority_open' => 0,
            'unresolved_items' => 0,
            'unresolved_transactions' => 0,
        ];
    }

    private function emptyAgingBuckets()
    {
        return [
            'lt_1h' => ['count' => 0],
            '1h_24h' => ['count' => 0],
            '1d_3d' => ['count' => 0],
            'gt_3d' => ['count' => 0],
            'unknown' => ['count' => 0],
        ];
    }

    private function period(Carbon $fromDate, Carbon $toDate)
    {
        return [
            'from' => $fromDate->toIso8601String(),
            'to' => $toDate->toIso8601String(),
        ];
    }

    private function applyTransactionSubqueryFilters($query, $operatorId, Request $request)
    {
        if (!Schema::hasTable('b2b_wallet_transactions')) {
            return;
        }

        $hasFilter = $request->query('currency') || $request->query('game_id') || $request->query('round_id');
        if (!$hasFilter) {
            return;
        }

        $query->whereIn('wallet_transaction_id', function ($subquery) use ($operatorId, $request) {
            $subquery->select('id')
                ->from('b2b_wallet_transactions')
                ->where('operator_id', $operatorId);

            if ($request->query('currency')) {
                $subquery->where('currency', strtoupper((string) $request->query('currency')));
            }

            if ($request->query('game_id')) {
                $subquery->where($this->walletGameColumn(), $request->query('game_id'));
            }

            if ($request->query('round_id')) {
                $subquery->where('round_id', $request->query('round_id'));
            }
        });
    }

    private function applyJoinedTransactionFilters($query, Request $request)
    {
        if ($request->query('currency')) {
            $query->where('wt.currency', strtoupper((string) $request->query('currency')));
        }

        if ($request->query('game_id')) {
            $query->where('wt.'.$this->walletGameColumn(), $request->query('game_id'));
        }

        if ($request->query('round_id')) {
            $query->where('wt.round_id', $request->query('round_id'));
        }
    }

    private function walletGameColumn()
    {
        return Schema::hasColumn('b2b_wallet_transactions', 'game_uid') ? 'game_uid' : 'game_id';
    }

    private function decimalAdd($left, $right, $scale = 8)
    {
        if (function_exists('bcadd')) {
            return bcadd((string) $left, (string) $right, $scale);
        }

        return $this->decimalFromMinorUnits(
            $this->signedIntegerAdd(
                $this->decimalToMinorUnits($left, $scale),
                $this->decimalToMinorUnits($right, $scale)
            ),
            $scale
        );
    }

    private function decimalNormalize($value, $scale = 8)
    {
        return $this->decimalAdd('0', $value, $scale);
    }

    private function decimalToMinorUnits($value, $scale)
    {
        $value = trim((string) $value);
        $negative = strpos($value, '-') === 0;
        $value = ltrim($value, '+-');
        $parts = explode('.', $value, 2);
        $major = preg_replace('/[^0-9]/', '', $parts[0]);
        $minor = isset($parts[1]) ? preg_replace('/[^0-9]/', '', $parts[1]) : '';
        $minor = substr(str_pad($minor, $scale, '0'), 0, $scale);
        $units = ltrim(($major === '' ? '0' : $major) . $minor, '0');
        $units = $units === '' ? '0' : $units;

        return $negative && $units !== '0' ? '-' . $units : $units;
    }

    private function decimalFromMinorUnits($units, $scale)
    {
        $units = (string) $units;
        $negative = strpos($units, '-') === 0;
        $units = ltrim($units, '+-');
        $units = ltrim($units, '0');
        $units = $units === '' ? '0' : $units;
        $units = str_pad($units, $scale + 1, '0', STR_PAD_LEFT);
        $major = substr($units, 0, -$scale);
        $minor = substr($units, -$scale);

        return ($negative ? '-' : '') . $major . '.' . $minor;
    }

    private function signedIntegerAdd($left, $right)
    {
        $leftNegative = strpos($left, '-') === 0;
        $rightNegative = strpos($right, '-') === 0;
        $leftAbs = ltrim($left, '+-');
        $rightAbs = ltrim($right, '+-');

        if ($leftNegative === $rightNegative) {
            $sum = $this->addAbs($leftAbs, $rightAbs);
            return $leftNegative && $sum !== '0' ? '-' . $sum : $sum;
        }

        $compare = $this->compareAbs($leftAbs, $rightAbs);
        if ($compare === 0) {
            return '0';
        }

        if ($compare > 0) {
            $diff = $this->subAbs($leftAbs, $rightAbs);
            return $leftNegative && $diff !== '0' ? '-' . $diff : $diff;
        }

        $diff = $this->subAbs($rightAbs, $leftAbs);
        return $rightNegative && $diff !== '0' ? '-' . $diff : $diff;
    }

    private function compareAbs($left, $right)
    {
        $left = ltrim($left, '0') ?: '0';
        $right = ltrim($right, '0') ?: '0';
        if (strlen($left) !== strlen($right)) {
            return strlen($left) > strlen($right) ? 1 : -1;
        }

        return strcmp($left, $right);
    }

    private function addAbs($left, $right)
    {
        $left = strrev(ltrim($left, '0') ?: '0');
        $right = strrev(ltrim($right, '0') ?: '0');
        $max = max(strlen($left), strlen($right));
        $carry = 0;
        $result = '';

        for ($i = 0; $i < $max; $i++) {
            $sum = (int) ($left[$i] ?? 0) + (int) ($right[$i] ?? 0) + $carry;
            $result .= (string) ($sum % 10);
            $carry = intdiv($sum, 10);
        }

        if ($carry > 0) {
            $result .= (string) $carry;
        }

        return ltrim(strrev($result), '0') ?: '0';
    }

    private function subAbs($left, $right)
    {
        $left = strrev(ltrim($left, '0') ?: '0');
        $right = strrev(ltrim($right, '0') ?: '0');
        $borrow = 0;
        $result = '';

        for ($i = 0; $i < strlen($left); $i++) {
            $digit = (int) $left[$i] - $borrow;
            $subtrahend = (int) ($right[$i] ?? 0);
            if ($digit < $subtrahend) {
                $digit += 10;
                $borrow = 1;
            } else {
                $borrow = 0;
            }

            $result .= (string) ($digit - $subtrahend);
        }

        return ltrim(strrev($result), '0') ?: '0';
    }
}
