<?php

namespace VanguardLTE\Http\Controllers\Api\B2B;

use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use VanguardLTE\B2B\Models\B2BOperatorPlayer;
use VanguardLTE\B2B\Models\B2BWalletTransaction;
use VanguardLTE\B2B\Services\B2BResilienceGuard;
use VanguardLTE\B2B\Services\OperatorWalletClient;

class WalletController extends Controller
{
    public function balance(Request $request, OperatorWalletClient $walletClient, B2BResilienceGuard $guard)
    {
        return $this->handle($request, $walletClient, $guard, B2BWalletTransaction::TYPE_BALANCE);
    }

    public function bet(Request $request, OperatorWalletClient $walletClient, B2BResilienceGuard $guard)
    {
        return $this->handle($request, $walletClient, $guard, B2BWalletTransaction::TYPE_BET);
    }

    public function win(Request $request, OperatorWalletClient $walletClient, B2BResilienceGuard $guard)
    {
        return $this->handle($request, $walletClient, $guard, B2BWalletTransaction::TYPE_WIN);
    }

    public function refund(Request $request, OperatorWalletClient $walletClient, B2BResilienceGuard $guard)
    {
        return $this->handle($request, $walletClient, $guard, B2BWalletTransaction::TYPE_REFUND);
    }

    public function rollback(Request $request, OperatorWalletClient $walletClient, B2BResilienceGuard $guard)
    {
        return $this->handle($request, $walletClient, $guard, B2BWalletTransaction::TYPE_ROLLBACK);
    }

    private function handle(Request $request, OperatorWalletClient $walletClient, B2BResilienceGuard $guard, $type)
    {
        $operator = $request->attributes->get('b2b_operator');

        $availability = $guard->checkOperatorAvailable($operator);
        if (!$availability['ok']) {
            return $this->guardError($availability);
        }

        $rate = $guard->checkRateLimit($operator, 'wallet');
        if (!$rate['ok']) {
            return $this->guardError($rate);
        }

        $rules = [
            'player_id' => 'required|string|max:191',
            'currency' => 'required|string|size:3',
            'game_id' => 'nullable|string|max:191',
            'provider' => 'nullable|string|max:191',
            'session_id' => 'nullable|string|max:191',
            'round_id' => 'nullable|string|max:191',
            'transaction_id' => 'nullable|string|max:191',
            'idempotency_key' => 'nullable|string|max:191',
            'amount' => 'nullable|numeric|min:0',
        ];

        if ($type !== B2BWalletTransaction::TYPE_BALANCE) {
            $rules['amount'] = 'required|numeric|min:0';
            $rules['transaction_id'] = 'required|string|max:191';
            $rules['round_id'] = 'required|string|max:191';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_FAILED',
                    'details' => $validator->errors(),
                ],
            ], 422);
        }

        $player = B2BOperatorPlayer::firstOrCreate(
            [
                'operator_id' => $operator->id,
                'external_player_id' => $request->input('player_id'),
            ],
            [
                'currency' => strtoupper($request->input('currency')),
                'status' => B2BOperatorPlayer::STATUS_ACTIVE,
            ]
        );

        if ($player->status !== B2BOperatorPlayer::STATUS_ACTIVE) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PLAYER_BLOCKED',
                    'message' => 'Player is not active',
                ],
            ], 403);
        }

        $idempotencyKey = $this->makeIdempotencyKey($operator, $request, $type);
        $existing = B2BWalletTransaction::where('operator_id', $operator->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            return $this->transactionResponse($existing, true);
        }

        try {
            $transaction = DB::transaction(function () use ($operator, $player, $request, $type, $idempotencyKey) {
                return B2BWalletTransaction::create([
                    'operator_id' => $operator->id,
                    'operator_player_id' => $player->id,
                    'session_id' => $request->input('session_id'),
                    'game_uid' => $request->input('game_id'),
                    'provider' => $request->input('provider', 'goldsvet_internal'),
                    'round_id' => $request->input('round_id'),
                    'transaction_uid' => $request->input('transaction_id', 'bal_' . Str::uuid()->toString()),
                    'idempotency_key' => $idempotencyKey,
                    'type' => $type,
                    'amount' => (float) $request->input('amount', 0),
                    'currency' => strtoupper($request->input('currency')),
                    'status' => B2BWalletTransaction::STATUS_PENDING,
                    'attempts' => 0,
                    'raw_request' => $request->all(),
                ]);
            });
        } catch (QueryException $e) {
            $existing = B2BWalletTransaction::where('operator_id', $operator->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return $this->transactionResponse($existing, true);
            }

            throw $e;
        }

        $transaction->forceFill([
            'attempts' => ((int) $transaction->attempts) + 1,
            'last_attempt_at' => now(),
            'locked_until' => now()->addSeconds($guard->walletTimeoutSeconds($operator) + 2),
        ])->save();

        $forwardResult = $walletClient->forward($operator, $transaction, $request->all());
        $accepted = (bool) (isset($forwardResult['accepted']) ? $forwardResult['accepted'] : false);
        $errorCode = isset($forwardResult['error_code']) ? $forwardResult['error_code'] : null;

        if ($accepted) {
            $status = B2BWalletTransaction::STATUS_ACCEPTED;
            $guard->recordSuccess($operator, 'wallet_callback_success', [
                'transaction_id' => $transaction->id,
                'type' => $type,
            ]);
        } else {
            $status = $errorCode === 'CALLBACK_TIMEOUT'
                ? B2BWalletTransaction::STATUS_TIMEOUT
                : B2BWalletTransaction::STATUS_REJECTED;

            $guard->recordFailure($operator, 'wallet_callback_failure', isset($forwardResult['error']) ? $forwardResult['error'] : 'Operator wallet callback rejected or failed.', [
                'transaction_id' => $transaction->id,
                'type' => $type,
                'error_code' => $errorCode,
            ]);
        }

        $transaction->forceFill([
            'status' => $status,
            'raw_response' => $forwardResult,
            'error_code' => $accepted ? null : ($errorCode ?: 'OPERATOR_REJECTED'),
            'error_message' => $accepted ? null : (isset($forwardResult['error']) ? $forwardResult['error'] : (isset($forwardResult['message']) ? $forwardResult['message'] : 'Operator wallet callback failed.')),
            'locked_until' => null,
            'processed_at' => now(),
        ])->save();

        return $this->transactionResponse($transaction, false);
    }

    private function makeIdempotencyKey($operator, Request $request, $type)
    {
        if ($request->input('idempotency_key')) {
            return $request->input('idempotency_key');
        }

        if ($request->input('transaction_id')) {
            return $operator->operator_uid . ':' . $type . ':' . $request->input('transaction_id');
        }

        return $operator->operator_uid . ':' . $type . ':' . $request->input('player_id') . ':' . sha1($request->getContent() . microtime(true));
    }

    private function transactionResponse(B2BWalletTransaction $transaction, $idempotentReplay)
    {
        $success = $transaction->status === B2BWalletTransaction::STATUS_ACCEPTED;

        $payload = [
            'success' => $success,
            'data' => [
                'transaction_id' => $transaction->transaction_uid,
                'aggregator_transaction_id' => $transaction->id,
                'idempotency_key' => $transaction->idempotency_key,
                'idempotent_replay' => $idempotentReplay,
                'type' => $transaction->type,
                'status' => $transaction->status,
                'amount' => $transaction->amount,
                'currency' => $transaction->currency,
            ],
        ];

        if (!$success) {
            $payload['error'] = [
                'code' => $transaction->error_code ?: 'WALLET_TRANSACTION_NOT_ACCEPTED',
                'message' => $transaction->error_message ?: 'Wallet transaction was not accepted.',
            ];
        }

        $httpStatus = $success ? 200 : ($transaction->status === B2BWalletTransaction::STATUS_TIMEOUT ? 504 : 409);
        return response()->json($payload, $httpStatus);
    }

    private function guardError(array $result)
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $result['code'],
                'message' => $result['message'],
            ],
            'retry_after' => isset($result['retry_after']) ? $result['retry_after'] : null,
        ], isset($result['http_status']) ? $result['http_status'] : 503);
    }
}
