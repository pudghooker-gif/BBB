<?php

namespace VanguardLTE\Http\Controllers\Api\B2B;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use VanguardLTE\B2B\Models\B2BSettlement;
use VanguardLTE\B2B\Models\B2BWalletTransaction;
use VanguardLTE\B2B\Services\B2BReconciliationReportQuery;
use VanguardLTE\B2B\Services\B2BReportQuery;
use VanguardLTE\B2B\Services\B2BSettlementWorkflowService;
use VanguardLTE\B2B\Services\WalletTransactionLookupService;
use VanguardLTE\B2B\Support\B2BApiResponse;

class ReportsController extends Controller
{
    private const DEFAULT_LIMIT = 100;
    private const MAX_LIMIT = 1000;
    private const DEFAULT_TRANSACTION_SORT = '-created_at';
    private const TRANSACTION_SORT_OPTIONS = [
        'created_at',
        '-created_at',
        'amount',
        '-amount',
        'type',
        '-type',
        'status',
        '-status',
        'currency',
        '-currency',
        'game_id',
        '-game_id',
        'transaction_uid',
        '-transaction_uid',
    ];
    private const TRANSACTION_TYPE_OPTIONS = [
        B2BWalletTransaction::TYPE_BALANCE,
        B2BWalletTransaction::TYPE_BET,
        B2BWalletTransaction::TYPE_WIN,
        B2BWalletTransaction::TYPE_REFUND,
        B2BWalletTransaction::TYPE_ROLLBACK,
    ];
    private const TRANSACTION_STATUS_OPTIONS = [
        B2BWalletTransaction::STATUS_PENDING,
        B2BWalletTransaction::STATUS_ACCEPTED,
        B2BWalletTransaction::STATUS_REJECTED,
        B2BWalletTransaction::STATUS_SUCCESS,
        B2BWalletTransaction::STATUS_FAILED,
        B2BWalletTransaction::STATUS_TIMEOUT,
        B2BWalletTransaction::STATUS_UNKNOWN,
        B2BWalletTransaction::STATUS_ROLLBACK_REQUIRED,
        B2BWalletTransaction::STATUS_REVERSED,
        B2BWalletTransaction::STATUS_MANUAL_REVIEW,
        B2BWalletTransaction::STATUS_DEAD_LETTER,
    ];
    private const DEFAULT_SETTLEMENT_SORT = '-created_at';
    private const SETTLEMENT_SORT_OPTIONS = [
        'created_at',
        '-created_at',
        'period_start',
        '-period_start',
        'period_end',
        '-period_end',
        'status',
        '-status',
        'currency',
        '-currency',
        'net_amount',
        '-net_amount',
        'settlement_uid',
        '-settlement_uid',
    ];
    private const SETTLEMENT_STATUS_OPTIONS = [
        B2BSettlement::STATUS_DRAFT,
        B2BSettlement::STATUS_EXPORTED,
        B2BSettlement::STATUS_SUBMITTED,
        B2BSettlement::STATUS_APPROVED,
        B2BSettlement::STATUS_REJECTED,
    ];

    protected $reports;
    protected $reconciliationReports;
    protected $settlementWorkflow;
    protected $walletLookup;

    public function __construct(
        B2BReportQuery $reports,
        B2BReconciliationReportQuery $reconciliationReports,
        B2BSettlementWorkflowService $settlementWorkflow,
        WalletTransactionLookupService $walletLookup
    )
    {
        $this->reports = $reports;
        $this->reconciliationReports = $reconciliationReports;
        $this->settlementWorkflow = $settlementWorkflow;
        $this->walletLookup = $walletLookup;
    }

    public function summary(Request $request)
    {
        if ($this->reports->operatorId($request) <= 0) {
            return $this->operatorContextMissing($request);
        }

        $base = $this->reports->transactionBaseQuery($request);

        $rows = $base
            ->select('type', 'status', DB::raw('COUNT(*) as count'), DB::raw('COALESCE(SUM(amount), 0) as amount'))
            ->groupBy('type', 'status')
            ->orderBy('type')
            ->orderBy('status')
            ->get();

        $totals = [
            'bets' => '0.00000000',
            'wins' => '0.00000000',
            'refunds' => '0.00000000',
            'rollbacks' => '0.00000000',
            'ggr' => '0.00000000',
            'transactions' => 0,
        ];

        foreach ($rows as $row) {
            if ($row->status === 'success') {
                if ($row->type === 'bet') {
                    $totals['bets'] = $this->decimalAdd($totals['bets'], $row->amount);
                } elseif ($row->type === 'win') {
                    $totals['wins'] = $this->decimalAdd($totals['wins'], $row->amount);
                } elseif ($row->type === 'refund') {
                    $totals['refunds'] = $this->decimalAdd($totals['refunds'], $row->amount);
                } elseif ($row->type === 'rollback') {
                    $totals['rollbacks'] = $this->decimalAdd($totals['rollbacks'], $row->amount);
                }
            }
            $totals['transactions'] += (int) $row->count;
        }

        $totals['ggr'] = $this->decimalSub($this->decimalSub($totals['bets'], $totals['wins']), $totals['refunds']);

        return B2BApiResponse::success($request, [
            'totals' => $totals,
            'breakdown' => $rows,
        ]);
    }

    public function transactions(Request $request)
    {
        if ($this->reports->operatorId($request) <= 0) {
            return $this->operatorContextMissing($request);
        }

        $filters = $this->validatedTransactionListFilters($request);
        if (isset($filters['response'])) {
            return $filters['response'];
        }

        list($fromDate, $toDate) = $this->reports->dateRange($request);
        if ($fromDate->gt($toDate)) {
            return B2BApiResponse::error($request, 'VALIDATION_FAILED', null, 422, [
                'period' => ['Report period start must be before or equal to period end.'],
            ]);
        }

        $query = $this->reports->transactionBaseQuery($request);
        $matchedCount = (clone $query)->count();
        $this->applyTransactionSort($query, $filters['sort']);
        $rows = $query->limit($filters['limit'])->get();

        return B2BApiResponse::success($request, $rows, 200, [
            'limit' => $filters['limit'],
            'count' => $rows->count(),
            'matched_count' => $matchedCount,
            'sort' => $filters['sort'],
            'filters' => $this->transactionResponseFilters($filters),
            'period' => [
                'from' => $fromDate->toIso8601String(),
                'to' => $toDate->toIso8601String(),
            ],
        ]);
    }

    public function transaction(Request $request, $transactionUid)
    {
        $operatorId = $this->reports->operatorId($request);
        if ($operatorId <= 0) {
            return $this->operatorContextMissing($request);
        }

        $query = DB::table('b2b_wallet_transactions')
            ->where(function ($q) use ($transactionUid) {
                $q->where('transaction_uid', $transactionUid)
                  ->orWhere('transaction_id', $transactionUid)
                  ->orWhere('id', $transactionUid);
            });

        $query->where('operator_id', $operatorId);

        $transaction = $query->first();

        if (!$transaction) {
            return B2BApiResponse::error($request, 'TRANSACTION_NOT_FOUND');
        }

        $payload = $this->walletLookup->statusPayload($transaction);
        $payload['callback_logs'] = $this->walletLookup->callbackLogs($transaction);

        return B2BApiResponse::success($request, $payload);
    }

    public function ggr(Request $request)
    {
        if ($this->reports->operatorId($request) <= 0) {
            return $this->operatorContextMissing($request);
        }

        $base = $this->reports->transactionBaseQuery($request)->where('status', 'success');

        $bets = (clone $base)->where('type', 'bet')->sum('amount');
        $wins = (clone $base)->where('type', 'win')->sum('amount');
        $refunds = (clone $base)->where('type', 'refund')->sum('amount');
        $rollbacks = (clone $base)->where('type', 'rollback')->sum('amount');

        return B2BApiResponse::success($request, [
            'bets' => $this->decimalNormalize($bets),
            'wins' => $this->decimalNormalize($wins),
            'refunds' => $this->decimalNormalize($refunds),
            'rollbacks' => $this->decimalNormalize($rollbacks),
            'ggr' => $this->decimalSub($this->decimalSub($bets, $wins), $refunds),
        ]);
    }

    public function settlements(Request $request)
    {
        $operatorId = $this->reports->operatorId($request);
        if ($operatorId <= 0) {
            return $this->operatorContextMissing($request);
        }

        $filters = $this->validatedSettlementListFilters($request);
        if (isset($filters['response'])) {
            return $filters['response'];
        }

        list($fromDate, $toDate) = $this->reports->dateRange($request);
        if ($fromDate->gt($toDate)) {
            return B2BApiResponse::error($request, 'VALIDATION_FAILED', null, 422, [
                'period' => ['Settlement report period start must be before or equal to period end.'],
            ]);
        }

        $query = DB::table('b2b_settlements')
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->where('operator_id', $operatorId);

        $this->applySettlementFilters($query, $filters);
        $matchedCount = (clone $query)->count();
        $this->applySettlementSort($query, $filters['sort']);
        $rows = $query->limit($filters['limit'])->get();

        return B2BApiResponse::success($request, $rows, 200, [
            'limit' => $filters['limit'],
            'count' => $rows->count(),
            'matched_count' => $matchedCount,
            'sort' => $filters['sort'],
            'filters' => $this->settlementResponseFilters($filters),
            'period' => [
                'from' => $fromDate->toIso8601String(),
                'to' => $toDate->toIso8601String(),
            ],
        ]);
    }

    public function settlement(Request $request, $settlementUid)
    {
        $operatorId = $this->reports->operatorId($request);
        if ($operatorId <= 0) {
            return $this->operatorContextMissing($request);
        }

        try {
            $settlement = $this->settlementWorkflow->settlementForOperator($settlementUid, $operatorId);
        } catch (\InvalidArgumentException $e) {
            return B2BApiResponse::error($request, 'SETTLEMENT_NOT_FOUND');
        }

        return B2BApiResponse::success($request, $this->settlementWorkflow->payload($settlement));
    }

    public function exportSettlement(Request $request)
    {
        $operator = $request->attributes->get('b2b_operator');
        if (!$operator || !isset($operator->id)) {
            return $this->operatorContextMissing($request);
        }

        $validator = Validator::make($request->all(), [
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'currency' => 'nullable|string|size:3',
            'format' => 'nullable|in:csv,json',
        ]);

        if ($validator->fails()) {
            return B2BApiResponse::error($request, 'VALIDATION_FAILED', null, 422, $validator->errors());
        }

        try {
            $from = $request->input('from') ? Carbon::parse($request->input('from'))->startOfDay() : Carbon::now()->subDays(7)->startOfDay();
            $to = $request->input('to') ? Carbon::parse($request->input('to'))->endOfDay() : Carbon::now()->endOfDay();
        } catch (\Exception $e) {
            return B2BApiResponse::error($request, 'VALIDATION_FAILED', null, 422, [
                'period' => ['Invalid settlement period.'],
            ]);
        }

        if ($from->gt($to)) {
            return B2BApiResponse::error($request, 'VALIDATION_FAILED', null, 422, [
                'period' => ['Settlement period start must be before period end.'],
            ]);
        }

        try {
            $payload = $this->settlementWorkflow->exportForOperator(
                $operator,
                $from,
                $to,
                $request->input('currency', $operator->default_currency),
                $request->input('format', 'csv'),
                'api:' . $operator->operator_uid,
                'Operator requested settlement export via API.'
            );
        } catch (\InvalidArgumentException $e) {
            return B2BApiResponse::error($request, 'VALIDATION_FAILED', $e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            return B2BApiResponse::error($request, 'SETTLEMENT_EXPORT_FAILED', $e->getMessage(), 500);
        }

        return B2BApiResponse::success($request, $payload);
    }

    public function reconciliation(Request $request)
    {
        if ($this->reports->operatorId($request) <= 0) {
            return $this->operatorContextMissing($request);
        }

        return B2BApiResponse::success($request, $this->reconciliationReports->build($request));
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

    private function decimalSub($left, $right, $scale = 8)
    {
        if (function_exists('bcsub')) {
            return bcsub((string) $left, (string) $right, $scale);
        }

        return $this->decimalFromMinorUnits(
            $this->signedIntegerAdd(
                $this->decimalToMinorUnits($left, $scale),
                $this->negateIntegerString($this->decimalToMinorUnits($right, $scale))
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

    private function negateIntegerString($value)
    {
        $value = (string) $value;
        if ($value === '0') {
            return '0';
        }

        return strpos($value, '-') === 0 ? substr($value, 1) : '-' . $value;
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

    private function validatedTransactionListFilters(Request $request)
    {
        $validator = Validator::make($request->query(), [
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'limit' => 'nullable|integer|min:1|max:' . self::MAX_LIMIT,
            'status' => 'nullable|in:' . implode(',', self::TRANSACTION_STATUS_OPTIONS),
            'type' => 'nullable|in:' . implode(',', self::TRANSACTION_TYPE_OPTIONS),
            'player_id' => 'nullable|string|max:191',
            'game_id' => 'nullable|string|max:191',
            'round_id' => 'nullable|string|max:191',
            'currency' => 'nullable|string|size:3',
            'sort' => 'nullable|in:' . implode(',', self::TRANSACTION_SORT_OPTIONS),
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
            'from' => $this->normalizedTextFilter($request, 'from'),
            'to' => $this->normalizedTextFilter($request, 'to'),
            'limit' => $limit === null || $limit === '' ? self::DEFAULT_LIMIT : (int) $limit,
            'status' => $this->normalizedTextFilter($request, 'status'),
            'type' => $this->normalizedTextFilter($request, 'type'),
            'player_id' => $this->normalizedTextFilter($request, 'player_id'),
            'game_id' => $this->normalizedTextFilter($request, 'game_id'),
            'round_id' => $this->normalizedTextFilter($request, 'round_id'),
            'currency' => $this->normalizedUpperFilter($request, 'currency'),
            'sort' => $this->normalizedTextFilter($request, 'sort') ?: self::DEFAULT_TRANSACTION_SORT,
        ];
    }

    private function applyTransactionSort($query, $sort)
    {
        list($column, $direction) = $this->sortParts($sort);

        if ($column === 'game_id') {
            $column = $this->reports->walletGameColumn();
        }

        $query->orderBy($column, $direction);

        if ($column !== 'transaction_uid') {
            $query->orderBy('transaction_uid');
        }
    }

    private function validatedSettlementListFilters(Request $request)
    {
        $validator = Validator::make($request->query(), [
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'limit' => 'nullable|integer|min:1|max:' . self::MAX_LIMIT,
            'status' => 'nullable|in:' . implode(',', self::SETTLEMENT_STATUS_OPTIONS),
            'currency' => 'nullable|string|size:3',
            'sort' => 'nullable|in:' . implode(',', self::SETTLEMENT_SORT_OPTIONS),
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
            'from' => $this->normalizedTextFilter($request, 'from'),
            'to' => $this->normalizedTextFilter($request, 'to'),
            'limit' => $limit === null || $limit === '' ? self::DEFAULT_LIMIT : (int) $limit,
            'status' => $this->normalizedTextFilter($request, 'status'),
            'currency' => $this->normalizedUpperFilter($request, 'currency'),
            'sort' => $this->normalizedTextFilter($request, 'sort') ?: self::DEFAULT_SETTLEMENT_SORT,
        ];
    }

    private function applySettlementFilters($query, array $filters)
    {
        if ($filters['status']) {
            $query->where('status', $filters['status']);
        }

        if ($filters['currency']) {
            $query->where('currency', $filters['currency']);
        }
    }

    private function applySettlementSort($query, $sort)
    {
        list($column, $direction) = $this->sortParts($sort);

        $query->orderBy($column, $direction);

        if ($column !== 'settlement_uid') {
            $query->orderBy('settlement_uid');
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

    private function normalizedUpperFilter(Request $request, $key)
    {
        $value = $this->normalizedTextFilter($request, $key);

        return $value === null ? null : strtoupper($value);
    }

    private function transactionResponseFilters(array $filters)
    {
        return array_filter([
            'from' => $filters['from'],
            'to' => $filters['to'],
            'status' => $filters['status'],
            'type' => $filters['type'],
            'player_id' => $filters['player_id'],
            'game_id' => $filters['game_id'],
            'round_id' => $filters['round_id'],
            'currency' => $filters['currency'],
        ], function ($value) {
            return $value !== null && $value !== '';
        });
    }

    private function settlementResponseFilters(array $filters)
    {
        return array_filter([
            'from' => $filters['from'],
            'to' => $filters['to'],
            'status' => $filters['status'],
            'currency' => $filters['currency'],
        ], function ($value) {
            return $value !== null && $value !== '';
        });
    }

    private function operatorContextMissing(Request $request)
    {
        return B2BApiResponse::error($request, 'OPERATOR_CONTEXT_MISSING');
    }
}
