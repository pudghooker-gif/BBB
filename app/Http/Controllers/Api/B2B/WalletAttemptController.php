<?php

namespace VanguardLTE\Http\Controllers\Api\B2B;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use VanguardLTE\Http\Controllers\Controller;
use VanguardLTE\B2B\Services\B2BContext;

class WalletAttemptController extends Controller
{
    public function index(Request $request, $transaction_uid)
    {
        if (!Schema::hasTable('b2b_wallet_transaction_attempts')) {
            return response()->json([
                'status' => 'error',
                'code' => 'ATTEMPTS_TABLE_MISSING',
            ], 500);
        }

        $operator = B2BContext::operator($request);
        $query = DB::table('b2b_wallet_transaction_attempts')
            ->where('transaction_uid', $transaction_uid)
            ->orderBy('id', 'desc');

        if ($operator && isset($operator->id)) {
            $query->where('operator_id', $operator->id);
        }

        return response()->json([
            'status' => 'success',
            'transaction_uid' => $transaction_uid,
            'data' => $query->limit(100)->get(),
        ]);
    }
}
