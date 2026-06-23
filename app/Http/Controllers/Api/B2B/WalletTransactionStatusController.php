<?php

namespace VanguardLTE\Http\Controllers\Api\B2B;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use VanguardLTE\B2B\Services\B2BContext;
use VanguardLTE\B2B\Services\WalletTransactionLookupService;
use VanguardLTE\B2B\Support\B2BApiResponse;
use VanguardLTE\Http\Controllers\Controller;

class WalletTransactionStatusController extends Controller
{
    protected $lookup;

    public function __construct(WalletTransactionLookupService $lookup)
    {
        $this->lookup = $lookup;
    }

    public function show(Request $request, $transaction_uid)
    {
        if (!Schema::hasTable('b2b_wallet_transactions')) {
            return B2BApiResponse::error($request, 'B2B_WALLET_TABLE_MISSING');
        }

        $operator = B2BContext::operator($request);
        if (!$operator || !isset($operator->id)) {
            return B2BApiResponse::error($request, 'OPERATOR_CONTEXT_MISSING');
        }

        $transaction = $this->lookup->findForOperator($operator->id, $transaction_uid);
        if (!$transaction) {
            return B2BApiResponse::error($request, 'TRANSACTION_NOT_FOUND');
        }

        return B2BApiResponse::success($request, $this->lookup->statusPayload($transaction));
    }
}
