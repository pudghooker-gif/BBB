<?php

namespace VanguardLTE\Http\Controllers\Api\B2B;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use VanguardLTE\B2B\Services\B2BReportQuery;

class ReportsController extends Controller
{
    protected $reports;

    public function __construct(B2BReportQuery $reports)
    {
        $this->reports = $reports;
    }

    public function summary(Request $request)
    {
        $base = $this->reports->transactionBaseQuery($request);

        $rows = $base
            ->select('type', 'status', DB::raw('COUNT(*) as count'), DB::raw('COALESCE(SUM(amount), 0) as amount'))
            ->groupBy('type', 'status')
            ->orderBy('type')
            ->orderBy('status')
            ->get();

        $totals = [
            'bets' => 0.0,
            'wins' => 0.0,
            'refunds' => 0.0,
            'rollbacks' => 0.0,
            'ggr' => 0.0,
            'transactions' => 0,
        ];

        foreach ($rows as $row) {
            if ($row->status === 'success') {
                if ($row->type === 'bet') {
                    $totals['bets'] += (float) $row->amount;
                } elseif ($row->type === 'win') {
                    $totals['wins'] += (float) $row->amount;
                } elseif ($row->type === 'refund') {
                    $totals['refunds'] += (float) $row->amount;
                } elseif ($row->type === 'rollback') {
                    $totals['rollbacks'] += (float) $row->amount;
                }
            }
            $totals['transactions'] += (int) $row->count;
        }

        $totals['ggr'] = round($totals['bets'] - $totals['wins'] - $totals['refunds'], 8);

        return response()->json([
            'status' => 'ok',
            'data' => [
                'totals' => $totals,
                'breakdown' => $rows,
            ],
        ]);
    }

    public function transactions(Request $request)
    {
        $limit = $this->reports->safeLimit($request);

        $rows = $this->reports->transactionBaseQuery($request)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'status' => 'ok',
            'data' => $rows,
            'limit' => $limit,
        ]);
    }

    public function transaction(Request $request, $transactionUid)
    {
        $operatorId = $this->reports->operatorId($request);

        $query = DB::table('b2b_wallet_transactions')
            ->where(function ($q) use ($transactionUid) {
                $q->where('transaction_uid', $transactionUid)
                  ->orWhere('transaction_id', $transactionUid)
                  ->orWhere('id', $transactionUid);
            });

        if ($operatorId > 0) {
            $query->where('operator_id', $operatorId);
        }

        $transaction = $query->first();

        if (!$transaction) {
            return response()->json([
                'status' => 'error',
                'code' => 'TRANSACTION_NOT_FOUND',
            ], 404);
        }

        $logs = DB::table('b2b_wallet_callback_logs')
            ->where('wallet_transaction_id', $transaction->id)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return response()->json([
            'status' => 'ok',
            'data' => [
                'transaction' => $transaction,
                'callback_logs' => $logs,
            ],
        ]);
    }

    public function ggr(Request $request)
    {
        $base = $this->reports->transactionBaseQuery($request)->where('status', 'success');

        $bets = (clone $base)->where('type', 'bet')->sum('amount');
        $wins = (clone $base)->where('type', 'win')->sum('amount');
        $refunds = (clone $base)->where('type', 'refund')->sum('amount');
        $rollbacks = (clone $base)->where('type', 'rollback')->sum('amount');

        return response()->json([
            'status' => 'ok',
            'data' => [
                'bets' => round((float) $bets, 8),
                'wins' => round((float) $wins, 8),
                'refunds' => round((float) $refunds, 8),
                'rollbacks' => round((float) $rollbacks, 8),
                'ggr' => round((float) $bets - (float) $wins - (float) $refunds, 8),
            ],
        ]);
    }

    public function settlements(Request $request)
    {
        list($fromDate, $toDate) = $this->reports->dateRange($request);
        $operatorId = $this->reports->operatorId($request);

        $query = DB::table('b2b_settlements')
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->orderBy('created_at', 'desc')
            ->limit($this->reports->safeLimit($request));

        if ($operatorId > 0) {
            $query->where('operator_id', $operatorId);
        }

        return response()->json([
            'status' => 'ok',
            'data' => $query->get(),
        ]);
    }
}
