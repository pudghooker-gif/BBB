<?php

namespace VanguardLTE\Http\Controllers\Api\B2B;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
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
        if (!$operator || !isset($operator->id)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'OPERATOR_CONTEXT_MISSING',
                    'message' => 'B2B operator context is missing.',
                ],
            ], 401);
        }

        $validator = Validator::make($request->all(), $this->rules($type));
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_FAILED',
                    'details' => $validator->errors(),
                ],
            ], 422);
        }

        $payload = $request->all();
        $payload['currency'] = strtoupper((string) $payload['currency']);

        if (!$this->isCurrencyAllowed($operator, $payload['currency'])) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CURRENCY_NOT_ALLOWED',
                    'message' => 'Currency is not allowed for this operator.',
                ],
            ], 422);
        }

        $result = $this->transactions->process($operator, $type, $payload);

        return response()->json($result['body'], $result['http_status']);
    }

    private function rules($type)
    {
        $base = [
            'player_id' => 'required|string|max:191',
            'currency' => 'required|string|size:3',
            'provider' => 'nullable|string|max:191',
            'metadata' => 'nullable|array',
        ];

        if ($type === 'balance') {
            return array_merge($base, [
                'game_id' => 'nullable|string|max:191',
                'session_id' => 'nullable|string|max:191',
            ]);
        }

        return array_merge($base, [
            'game_id' => 'required|string|max:191',
            'session_id' => 'required|string|max:191',
            'round_id' => 'required|string|max:191',
            'transaction_id' => 'required|string|max:191',
            'original_transaction_id' => 'nullable|string|max:191',
            'amount' => [
                'required',
                'string',
                'regex:/^\d{1,12}(\.\d{1,8})?$/',
                'not_regex:/^0+(\.0{1,8})?$/',
            ],
        ]);
    }

    private function isCurrencyAllowed($operator, $currency)
    {
        $allowed = $operator && is_array($operator->allowed_currencies)
            ? $operator->allowed_currencies
            : [];

        if (count($allowed) === 0) {
            return true;
        }

        return in_array(strtoupper($currency), array_map('strtoupper', $allowed), true);
    }
}
