<?php

namespace VanguardLTE\Http\Controllers\Api\B2B;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use VanguardLTE\Http\Controllers\Controller;
use VanguardLTE\B2B\Services\B2BContext;
use VanguardLTE\B2B\Services\B2BResilienceGuard;
use VanguardLTE\B2B\Services\WalletTransactionService;
use VanguardLTE\B2B\Support\B2BApiResponse;

class WalletController extends Controller
{
    protected $transactions;
    protected $guard;

    public function __construct(WalletTransactionService $transactions, B2BResilienceGuard $guard)
    {
        $this->transactions = $transactions;
        $this->guard = $guard;
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
            return B2BApiResponse::error($request, 'OPERATOR_CONTEXT_MISSING');
        }

        $availability = $this->guard->checkOperatorAvailable($operator);
        if (!$availability['ok']) {
            return $this->guardError($request, $availability);
        }

        $rate = $this->guard->checkRateLimit($operator, 'wallet_' . $type, $request->attributes->get('b2b_api_key'));
        if (!$rate['ok']) {
            return $this->guardError($request, $rate);
        }

        $validator = Validator::make($request->all(), $this->rules($type));
        if ($validator->fails()) {
            return B2BApiResponse::error($request, 'VALIDATION_FAILED', null, 422, $validator->errors());
        }

        $payload = $request->all();
        $payload['currency'] = strtoupper((string) $payload['currency']);

        if (!$this->isCurrencyAllowed($operator, $payload['currency'])) {
            return B2BApiResponse::error($request, 'CURRENCY_NOT_ALLOWED');
        }

        if (!$this->isSessionOwnedByOperator($operator, $payload)) {
            return B2BApiResponse::error($request, 'SESSION_NOT_FOUND');
        }

        $result = $this->transactions->process($operator, $type, $payload, [
            'request_id' => $request->attributes->get('request_id') ?: $request->header('X-Request-Id'),
        ]);

        if (!$result['ok']) {
            $body = isset($result['body']) && is_array($result['body']) ? $result['body'] : [];
            $code = isset($body['code']) ? $body['code'] : 'WALLET_OPERATION_FAILED';
            $message = isset($body['message']) ? $body['message'] : null;

            return B2BApiResponse::error(
                $request,
                $code,
                $message,
                $result['http_status'],
                isset($body['details']) ? $body['details'] : null,
                isset($body['transaction_uid']) ? ['transaction_uid' => $body['transaction_uid']] : []
            );
        }

        return B2BApiResponse::success($request, $result['body'], $result['http_status']);
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

    private function isSessionOwnedByOperator($operator, array $payload)
    {
        if (empty($payload['session_id']) || !Schema::hasTable('b2b_game_sessions')) {
            return true;
        }

        if (!$operator || !isset($operator->id)) {
            return false;
        }

        $query = DB::table('b2b_game_sessions')
            ->where('operator_id', $operator->id)
            ->where('session_uid', $payload['session_id']);

        if (!empty($payload['game_id']) && Schema::hasColumn('b2b_game_sessions', 'game_uid')) {
            $query->where('game_uid', $payload['game_id']);
        }

        if (!empty($payload['currency']) && Schema::hasColumn('b2b_game_sessions', 'currency')) {
            $query->where('currency', strtoupper((string) $payload['currency']));
        }

        if (Schema::hasColumn('b2b_game_sessions', 'status')) {
            $query->whereIn('status', ['active', 'launched']);
        }

        return $query->exists();
    }

    private function guardError(Request $request, array $result)
    {
        $meta = [];
        if (isset($result['retry_after'])) {
            $meta['retry_after'] = $result['retry_after'];
        }
        foreach (['rate_scope', 'limit', 'current'] as $key) {
            if (isset($result[$key])) {
                $meta[$key] = $result[$key];
            }
        }

        return B2BApiResponse::error(
            $request,
            $result['code'],
            isset($result['message']) ? $result['message'] : null,
            isset($result['http_status']) ? $result['http_status'] : 503,
            null,
            $meta
        );
    }
}
