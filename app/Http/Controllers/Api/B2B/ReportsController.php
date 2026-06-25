<?php

namespace VanguardLTE\Http\Controllers\Api\B2B;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use VanguardLTE\B2B\Services\B2BReconciliationReportQuery;
use VanguardLTE\B2B\Services\B2BReportQuery;
use VanguardLTE\B2B\Services\WalletTransactionLookupService;
use VanguardLTE\B2B\Support\B2BApiResponse;

class ReportsController extends Controller
{
    protected $reports;
    protected $reconciliationReports;
    protected $walletLookup;

    public function __construct(
        B2BReportQuery $reports,
        B2BReconciliationReportQuery $reconciliationReports,
        WalletTransactionLookupService $walletLookup
    )
    {
        $this->reports = $reports;
        $this->reconciliationReports = $reconciliationReports;
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

        $limit = $this->reports->safeLimit($request);

        $rows = $this->reports->transactionBaseQuery($request)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return B2BApiResponse::success($request, $rows, 200, ['limit' => $limit]);
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
        list($fromDate, $toDate) = $this->reports->dateRange($request);
        $operatorId = $this->reports->operatorId($request);
        if ($operatorId <= 0) {
            return $this->operatorContextMissing($request);
        }

        $query = DB::table('b2b_settlements')
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->orderBy('created_at', 'desc')
            ->limit($this->reports->safeLimit($request));

        $query->where('operator_id', $operatorId);

        return B2BApiResponse::success($request, $query->get());
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

    private function operatorContextMissing(Request $request)
    {
        return B2BApiResponse::error($request, 'OPERATOR_CONTEXT_MISSING');
    }
}
