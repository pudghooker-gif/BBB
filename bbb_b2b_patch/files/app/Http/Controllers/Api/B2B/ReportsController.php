<?php

namespace VanguardLTE\Http\Controllers\Api\B2B;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use VanguardLTE\B2B\Models\B2BWalletTransaction;

class ReportsController extends Controller
{
    public function transactions(Request $request)
    {
        $operator = $request->attributes->get('b2b_operator');

        $query = B2BWalletTransaction::where('operator_id', $operator->id)
            ->orderBy('id', 'desc');

        if ($request->query('from')) {
            $query->where('created_at', '>=', $request->query('from'));
        }

        if ($request->query('to')) {
            $query->where('created_at', '<=', $request->query('to'));
        }

        if ($request->query('type')) {
            $query->where('type', $request->query('type'));
        }

        if ($request->query('status')) {
            $query->where('status', $request->query('status'));
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate((int) $request->query('per_page', 50)),
        ]);
    }

    public function ggr(Request $request)
    {
        $operator = $request->attributes->get('b2b_operator');

        $query = B2BWalletTransaction::where('operator_id', $operator->id)
            ->where('status', B2BWalletTransaction::STATUS_ACCEPTED);

        if ($request->query('from')) {
            $query->where('created_at', '>=', $request->query('from'));
        }

        if ($request->query('to')) {
            $query->where('created_at', '<=', $request->query('to'));
        }

        $bets = (clone $query)->where('type', B2BWalletTransaction::TYPE_BET)->sum('amount');
        $wins = (clone $query)->where('type', B2BWalletTransaction::TYPE_WIN)->sum('amount');
        $refunds = (clone $query)->where('type', B2BWalletTransaction::TYPE_REFUND)->sum('amount');
        $rollbacks = (clone $query)->where('type', B2BWalletTransaction::TYPE_ROLLBACK)->sum('amount');

        return response()->json([
            'success' => true,
            'data' => [
                'currency' => $request->query('currency'),
                'bets' => (float) $bets,
                'wins' => (float) $wins,
                'refunds' => (float) $refunds,
                'rollbacks' => (float) $rollbacks,
                'ggr' => (float) ($bets - $wins - $refunds),
            ],
        ]);
    }
}
