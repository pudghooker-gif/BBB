<?php

namespace VanguardLTE\Http\Controllers\Api\B2B;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use VanguardLTE\Http\Controllers\Controller;
use VanguardLTE\B2B\Services\B2BContext;
use VanguardLTE\B2B\Support\B2BApiResponse;

class WalletAttemptController extends Controller
{
    public function index(Request $request, $transaction_uid)
    {
        if (!Schema::hasTable('b2b_wallet_transaction_attempts')) {
            return B2BApiResponse::error($request, 'ATTEMPTS_TABLE_MISSING');
        }

        $operator = B2BContext::operator($request);
        if (!$operator || !isset($operator->id)) {
            return B2BApiResponse::error($request, 'OPERATOR_CONTEXT_MISSING');
        }

        $query = DB::table('b2b_wallet_transaction_attempts')
            ->where('transaction_uid', $transaction_uid)
            ->orderBy('id', 'desc');

        $query->where('operator_id', $operator->id);

        return B2BApiResponse::success($request, $query->limit(100)->get(), 200, [
            'transaction_uid' => $transaction_uid,
            'limit' => 100,
        ]);
    }
}
