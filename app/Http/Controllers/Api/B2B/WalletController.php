<?php

namespace VanguardLTE\Http\Controllers\Api\B2B;

use Illuminate\Http\Request;
use VanguardLTE\Http\Controllers\Controller;
use VanguardLTE\B2B\Services\B2BContext;
use VanguardLTE\B2B\Services\WalletTransactionService;

class WalletController extends Controller
{
    protected $transactions;

    public function __construct(WalletTransactionService $transactions)
    {
        $this->transactions = $transactions;
    }

    public function balance(Request $request)
    {
        return $this->process($request, 'balance');
    }

    public function bet(Request $request)
    {
        return $this->process($request, 'bet');
    }

    public function win(Request $request)
    {
        return $this->process($request, 'win');
    }

    public function refund(Request $request)
    {
        return $this->process($request, 'refund');
    }

    public function rollback(Request $request)
    {
        return $this->process($request, 'rollback');
    }

    protected function process(Request $request, $type)
    {
        $operator = B2BContext::operator($request);
        $payload = $request->all();
        $result = $this->transactions->process($operator, $type, $payload);

        return response()->json($result['body'], $result['http_status']);
    }
}
