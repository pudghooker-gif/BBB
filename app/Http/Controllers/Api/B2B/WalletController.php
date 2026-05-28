<?php

namespace VanguardLTE\Http\Controllers\Api\B2B;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use VanguardLTE\B2B\Models\B2BOperatorPlayer;
use VanguardLTE\B2B\Models\B2BWalletTransaction;
use VanguardLTE\B2B\Services\OperatorWalletClient;

class WalletController extends Controller
{
    public function balance(Request $request, OperatorWalletClient $walletClient)
    {
        return $this->handle($request, $walletClient, B2BWalletTransaction::TYPE_BALANCE);
    }

    public function bet(Request $request, OperatorWalletClient $walletClient)
    {
        return $this->handle($request, $walletClient, B2BWalletTransaction::TYPE_BET);
    }

    public function win(Request $request, OperatorWalletClient $walletClient)
    {
        return $this->handle($request, $walletClient, B2BWalletTransaction::TYPE_WIN);
    }

    public function refund(Request $request, OperatorWalletClient $walletClient)
    {
        return $this->handle($request, $walletClient, B2BWalletTransaction::TYPE_REFUND);
    }

    public function rollback(Request $request, OperatorWalletClient $walletClient)
    {
        return $this->handle($request, $walletClient, B2BWalletTransaction::TYPE_ROLLBACK);
    }

    private function handle(Request $request, OperatorWalletClient $walletClient, $type)
    {
        $operator = $request->attributes->get('b2b_operator');

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

        $idempotencyKey = $request->input('idempotency_key')
            ?: $operator->operator_uid . ':' . $type . ':' . ($request->input('transaction_id') ?: Str::uuid()->toString());

        $existing = B2BWalletTransaction::where('operator_id', $operator->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => true,
                'data' => [
                    'transaction_id' => $existing->transaction_uid,
                    'status' => $existing->status,
                    'idempotent' => true,
                    'raw_response' => $existing->raw_response,
                ],
            ]);
        }

        $result = DB::transaction(function () use ($operator, $player, $request, $type, $idempotencyKey, $walletClient) {
            $transaction = B2BWalletTransaction::create([
                'operator_id' => $operator->id,
                'operator_player_id' => $player->id,
                'session_id' => $request->input('session_id'),
                'game_uid' => $request->input('game_id'),
                'provider' => $request->input('provider', 'goldsvet_internal'),
                'round_id' => $request->input('round_id'),
                'transaction_uid' => $request->input('transaction_id') ?: 'tx_' . Str::uuid()->toString(),
                'idempotency_key' => $idempotencyKey,
                'type' => $type,
                'amount' => $request->input('amount', 0),
                'currency' => strtoupper($request->input('currency')),
                'status' => B2BWalletTransaction::STATUS_PENDING,
                'raw_request' => $request->all(),
            ]);

            $callbackResponse = $walletClient->forward($operator, $transaction, $request->all());
            $accepted = isset($callbackResponse['accepted']) ? (bool) $callbackResponse['accepted'] : !isset($callbackResponse['error']);

            $errorMessage = null;
            if (!$accepted) {
                $errorMessage = isset($callbackResponse['error'])
                    ? $callbackResponse['error']
                    : 'Operator wallet callback rejected the transaction';
            }

            $transaction->update([
                'status' => $accepted ? B2BWalletTransaction::STATUS_ACCEPTED : B2BWalletTransaction::STATUS_FAILED,
                'raw_response' => $callbackResponse,
                'error_message' => $errorMessage,
            ]);

            return [$transaction, $callbackResponse];
        });

        list($transaction, $callbackResponse) = $result;

        return response()->json([
            'success' => $transaction->status === B2BWalletTransaction::STATUS_ACCEPTED,
            'data' => [
                'transaction_id' => $transaction->transaction_uid,
                'status' => $transaction->status,
                'callback' => $callbackResponse,
            ],
        ], $transaction->status === B2BWalletTransaction::STATUS_ACCEPTED ? 200 : 502);
    }
}
