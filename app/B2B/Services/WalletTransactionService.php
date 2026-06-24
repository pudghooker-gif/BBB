<?php

namespace VanguardLTE\B2B\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class WalletTransactionService
{
    protected $client;
    protected $idempotency;
    protected $stateMachine;
    protected $redactor;

    public function __construct(
        OperatorWalletClient $client,
        WalletIdempotencyService $idempotency,
        WalletTransactionStateMachine $stateMachine,
        B2BPayloadRedactor $redactor
    )
    {
        $this->client = $client;
        $this->idempotency = $idempotency;
        $this->stateMachine = $stateMachine;
        $this->redactor = $redactor;
    }

    public function process($operator, $type, array $payload)
    {
        $operatorId = B2BContext::operatorId($operator);
        if (!$operatorId) {
            return [
                'ok' => false,
                'http_status' => 401,
                'body' => ['status' => 'error', 'code' => 'OPERATOR_NOT_FOUND'],
            ];
        }

        if (!Schema::hasTable('b2b_wallet_transactions')) {
            return [
                'ok' => false,
                'http_status' => 500,
                'body' => ['status' => 'error', 'code' => 'B2B_WALLET_TABLE_MISSING'],
            ];
        }

        $transactionId = isset($payload['transaction_id']) ? $payload['transaction_id'] : (isset($payload['tx_id']) ? $payload['tx_id'] : null);
        $roundId = isset($payload['round_id']) ? $payload['round_id'] : null;
        $idempotencyKey = $this->idempotency->key($operatorId, $type, $transactionId ?: Str::uuid()->toString(), $roundId);
        $requestHash = $this->idempotency->requestHash($payload);

        $existing = $this->idempotency->findExisting($operatorId, $idempotencyKey);
        if ($existing) {
            if (isset($existing->request_hash) && $existing->request_hash && !hash_equals($existing->request_hash, $requestHash)) {
                return [
                    'ok' => false,
                    'http_status' => 409,
                    'body' => [
                        'status' => 'error',
                        'code' => 'IDEMPOTENCY_CONFLICT',
                        'message' => 'Transaction idempotency key was already used with a different payload.',
                        'transaction_uid' => isset($existing->transaction_uid) ? $existing->transaction_uid : null,
                    ],
                ];
            }

            return [
                'ok' => true,
                'http_status' => 200,
                'body' => [
                    'status' => $existing->status,
                    'duplicate' => true,
                    'transaction_uid' => isset($existing->transaction_uid) ? $existing->transaction_uid : null,
                    'operator_response' => isset($existing->operator_response_body) ? json_decode($existing->operator_response_body, true) : null,
                ],
            ];
        }

        $transactionUid = (string) Str::uuid();
        $row = [
            'transaction_uid' => $transactionUid,
            'operator_id' => $operatorId,
            'player_id' => isset($payload['player_id']) ? $payload['player_id'] : null,
            'session_id' => isset($payload['session_id']) ? $payload['session_id'] : null,
            'game_id' => isset($payload['game_id']) ? $payload['game_id'] : null,
            'round_id' => $roundId,
            'transaction_id' => $transactionId,
            'type' => $type,
            'amount' => isset($payload['amount']) ? $payload['amount'] : 0,
            'currency' => isset($payload['currency']) ? $payload['currency'] : (isset($operator->currency) ? $operator->currency : null),
            'status' => 'pending',
            'idempotency_key' => $idempotencyKey,
            'request_hash' => $requestHash,
            'attempts' => 0,
            'raw_request' => $this->redactor->json($payload),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];

        $transactionIdDb = DB::table('b2b_wallet_transactions')->insertGetId($this->filterColumns('b2b_wallet_transactions', $row));
        $transaction = DB::table('b2b_wallet_transactions')->where('id', $transactionIdDb)->first();
        $this->stateMachine->record($transaction, null, WalletTransactionStateMachine::STATUS_PENDING, 'wallet_transaction_created', [
            'type' => $type,
            'idempotency_key' => $idempotencyKey,
        ]);

        $callbackResult = $this->client->call($operator, $type, $payload, $transaction);
        $status = $this->statusFromWalletResult($callbackResult);

        $updates = [
            'attempts' => isset($transaction->attempts) ? ((int) $transaction->attempts + 1) : 1,
            'processed_at' => Carbon::now(),
            'last_error' => $callbackResult['ok'] ? null : (isset($callbackResult['message']) ? $callbackResult['message'] : 'Wallet callback failed'),
            'operator_response_code' => isset($callbackResult['http_status']) ? $callbackResult['http_status'] : null,
            'operator_response_body' => $this->redactor->json(isset($callbackResult['body']) ? $callbackResult['body'] : null),
            'raw_response' => $this->redactor->json($callbackResult),
        ];

        $this->stateMachine->transition($transaction, $status, 'wallet_callback_result', $updates, [
            'wallet_code' => isset($callbackResult['code']) ? $callbackResult['code'] : null,
            'http_status' => isset($callbackResult['http_status']) ? $callbackResult['http_status'] : null,
            'duration_ms' => isset($callbackResult['duration_ms']) ? $callbackResult['duration_ms'] : null,
        ]);

        return [
            'ok' => $callbackResult['ok'],
            'http_status' => $callbackResult['ok'] ? 200 : 502,
            'body' => [
                'status' => $status,
                'transaction_uid' => $transactionUid,
                'wallet' => $callbackResult,
            ],
        ];
    }

    public function retryPending($limit = 50)
    {
        if (!Schema::hasTable('b2b_wallet_transactions')) {
            return ['processed' => 0, 'message' => 'b2b_wallet_transactions table missing'];
        }

        $rows = DB::table('b2b_wallet_transactions')
            ->whereIn('status', ['timeout', 'failed', 'unknown'])
            ->orderBy('id')
            ->limit((int) $limit)
            ->get();

        $processed = 0;
        $deadLettered = 0;
        $maxAttempts = $this->maxRetryAttempts();

        foreach ($rows as $row) {
            if ((int) $row->attempts >= $maxAttempts) {
                $this->stateMachine->transition($row, WalletTransactionStateMachine::STATUS_DEAD_LETTER, 'wallet_retry_budget_exhausted', [
                    'processed_at' => Carbon::now(),
                    'last_error' => 'Wallet retry budget exhausted after '.$maxAttempts.' attempts.',
                ], [
                    'attempts' => (int) $row->attempts,
                    'max_attempts' => $maxAttempts,
                ]);

                $processed++;
                $deadLettered++;
                continue;
            }

            $operator = DB::table('b2b_operators')->where('id', $row->operator_id)->first();
            if (!$operator) {
                continue;
            }

            $payload = isset($row->raw_request) ? json_decode($row->raw_request, true) : [];
            if (!is_array($payload)) {
                $payload = [];
            }

            $result = $this->client->call($operator, $row->type, $payload, $row);
            $status = $this->statusFromWalletResult($result);

            $updates = [
                'attempts' => isset($row->attempts) ? ((int) $row->attempts + 1) : 1,
                'processed_at' => Carbon::now(),
                'last_error' => $result['ok'] ? null : (isset($result['message']) ? $result['message'] : 'Wallet retry failed'),
                'operator_response_code' => isset($result['http_status']) ? $result['http_status'] : null,
                'operator_response_body' => $this->redactor->json(isset($result['body']) ? $result['body'] : null),
                'raw_response' => $this->redactor->json($result),
            ];

            $this->stateMachine->transition($row, $status, 'wallet_retry_result', $updates, [
                'wallet_code' => isset($result['code']) ? $result['code'] : null,
                'http_status' => isset($result['http_status']) ? $result['http_status'] : null,
                'duration_ms' => isset($result['duration_ms']) ? $result['duration_ms'] : null,
            ]);
            $processed++;
        }

        return ['processed' => $processed, 'dead_lettered' => $deadLettered];
    }

    protected function statusFromWalletResult(array $result)
    {
        if (!empty($result['ok'])) {
            return WalletTransactionStateMachine::STATUS_SUCCESS;
        }

        $code = isset($result['code']) ? $result['code'] : null;
        if ($code === 'WALLET_TIMEOUT_OR_EXCEPTION' || $code === 'CIRCUIT_OPEN') {
            return WalletTransactionStateMachine::STATUS_TIMEOUT;
        }

        if ($code === 'WALLET_UNKNOWN_RESULT') {
            return WalletTransactionStateMachine::STATUS_UNKNOWN;
        }

        return WalletTransactionStateMachine::STATUS_FAILED;
    }

    protected function maxRetryAttempts()
    {
        $attempts = (int) config('b2b.wallet_retry_max_attempts', 3);

        return $attempts > 0 ? $attempts : 3;
    }

    protected function filterColumns($table, array $data)
    {
        $filtered = [];
        foreach ($data as $key => $value) {
            if (Schema::hasColumn($table, $key)) {
                $filtered[$key] = $value;
            }
        }
        return $filtered;
    }
}
