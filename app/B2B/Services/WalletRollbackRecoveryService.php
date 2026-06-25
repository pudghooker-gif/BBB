<?php

namespace VanguardLTE\B2B\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WalletRollbackRecoveryService
{
    protected $client;
    protected $stateMachine;
    protected $redactor;

    public function __construct(
        OperatorWalletClient $client,
        WalletTransactionStateMachine $stateMachine,
        B2BPayloadRedactor $redactor
    )
    {
        $this->client = $client;
        $this->stateMachine = $stateMachine;
        $this->redactor = $redactor;
    }

    public function recover($limit = 50)
    {
        if (!Schema::hasTable('b2b_wallet_transactions')) {
            return ['processed' => 0, 'reversed' => 0, 'failed' => 0, 'manual_review' => 0, 'skipped' => 0, 'message' => 'b2b_wallet_transactions table missing'];
        }

        $rows = DB::table('b2b_wallet_transactions')
            ->where('status', WalletTransactionStateMachine::STATUS_ROLLBACK_REQUIRED)
            ->orderBy('id')
            ->limit($this->safeLimit($limit))
            ->get();

        $result = [
            'processed' => 0,
            'reversed' => 0,
            'failed' => 0,
            'manual_review' => 0,
            'skipped' => 0,
        ];

        foreach ($rows as $row) {
            $result['processed']++;
            $maxAttempts = $this->maxRollbackAttempts();
            $attemptsBefore = $this->rollbackAttemptCount($row);

            if ($attemptsBefore >= $maxAttempts) {
                $this->moveToManualReview($row, [
                    'ok' => false,
                    'code' => 'ROLLBACK_RECOVERY_BUDGET_EXHAUSTED',
                    'message' => 'Rollback recovery budget exhausted before another callback.',
                    'http_status' => null,
                    'duration_ms' => null,
                    'body' => null,
                ], $attemptsBefore, $maxAttempts);
                $result['manual_review']++;
                continue;
            }

            $operator = $this->operatorFor($row);
            if (!$operator) {
                $this->openItem($row, 'rollback_required', [
                    'rollback_recovery' => [
                        'ok' => false,
                        'code' => 'OPERATOR_NOT_FOUND',
                        'message' => 'Operator was not resolved for rollback recovery.',
                        'attempts' => $attemptsBefore,
                        'max_attempts' => $maxAttempts,
                    ],
                ]);
                $result['skipped']++;
                continue;
            }

            $payload = $this->rollbackPayload($row, $attemptsBefore + 1);
            $callbackResult = $this->client->call($operator, 'rollback', $payload, $row);

            if (!empty($callbackResult['ok'])) {
                $this->markReversed($row, $callbackResult);
                $result['reversed']++;
                continue;
            }

            $attemptsAfter = $this->rollbackAttemptCount($row);
            if ($attemptsAfter >= $maxAttempts) {
                $this->moveToManualReview($row, $callbackResult, $attemptsAfter, $maxAttempts);
                $result['manual_review']++;
                continue;
            }

            $this->recordFailedAttempt($row, $callbackResult, $attemptsAfter, $maxAttempts);
            $result['failed']++;
        }

        return $result;
    }

    private function markReversed($row, array $callbackResult)
    {
        $this->stateMachine->transition(
            $row,
            WalletTransactionStateMachine::STATUS_REVERSED,
            'wallet_rollback_recovery_result',
            [
                'processed_at' => Carbon::now(),
                'last_error' => null,
                'operator_response_code' => isset($callbackResult['http_status']) ? $callbackResult['http_status'] : null,
                'operator_response_body' => $this->redactor->json(isset($callbackResult['body']) ? $callbackResult['body'] : null),
                'raw_response' => $this->redactor->json(['rollback_recovery' => $callbackResult]),
            ],
            $this->recoveryContext($callbackResult, $this->rollbackAttemptCount($row), $this->maxRollbackAttempts())
        );

        $this->resolveOpenItems($row, WalletTransactionStateMachine::STATUS_REVERSED, $callbackResult);
    }

    private function moveToManualReview($row, array $callbackResult, $attempts, $maxAttempts)
    {
        $this->stateMachine->transition(
            $row,
            WalletTransactionStateMachine::STATUS_MANUAL_REVIEW,
            'wallet_rollback_recovery_budget_exhausted',
            [
                'processed_at' => Carbon::now(),
                'last_error' => 'Rollback recovery budget exhausted after '.$maxAttempts.' attempts.',
                'operator_response_code' => isset($callbackResult['http_status']) ? $callbackResult['http_status'] : null,
                'operator_response_body' => $this->redactor->json(isset($callbackResult['body']) ? $callbackResult['body'] : null),
                'raw_response' => $this->redactor->json(['rollback_recovery' => $callbackResult]),
            ],
            $this->recoveryContext($callbackResult, $attempts, $maxAttempts)
        );

        $fresh = DB::table('b2b_wallet_transactions')->where('id', $row->id)->first();
        $this->resolveOpenItems($fresh ?: $row, WalletTransactionStateMachine::STATUS_MANUAL_REVIEW, $callbackResult);
        $this->openItem($fresh ?: $row, 'manual_review', [
            'rollback_recovery' => $this->recoveryContext($callbackResult, $attempts, $maxAttempts),
        ]);
    }

    private function recordFailedAttempt($row, array $callbackResult, $attempts, $maxAttempts)
    {
        DB::table('b2b_wallet_transactions')
            ->where('id', $row->id)
            ->update($this->filterColumns('b2b_wallet_transactions', [
                'processed_at' => Carbon::now(),
                'last_error' => isset($callbackResult['message']) ? $callbackResult['message'] : 'Rollback recovery callback failed.',
                'operator_response_code' => isset($callbackResult['http_status']) ? $callbackResult['http_status'] : null,
                'operator_response_body' => $this->redactor->json(isset($callbackResult['body']) ? $callbackResult['body'] : null),
                'raw_response' => $this->redactor->json(['rollback_recovery' => $callbackResult]),
                'updated_at' => Carbon::now(),
            ]));

        $this->openItem($row, 'rollback_required', [
            'rollback_recovery' => $this->recoveryContext($callbackResult, $attempts, $maxAttempts),
        ]);
    }

    private function rollbackPayload($row, $attemptNo)
    {
        $raw = $this->rawRequest($row);
        $originalTransactionId = isset($row->transaction_id) && $row->transaction_id
            ? $row->transaction_id
            : (isset($raw['transaction_id']) ? $raw['transaction_id'] : null);

        return $this->withoutNulls([
            'player_id' => isset($raw['player_id']) ? $raw['player_id'] : null,
            'game_id' => isset($raw['game_id']) ? $raw['game_id'] : (isset($row->game_uid) ? $row->game_uid : null),
            'session_id' => isset($row->session_id) ? $row->session_id : (isset($raw['session_id']) ? $raw['session_id'] : null),
            'round_id' => isset($row->round_id) ? $row->round_id : (isset($raw['round_id']) ? $raw['round_id'] : null),
            'transaction_id' => $this->rollbackTransactionId($row),
            'original_transaction_id' => $originalTransactionId,
            'original_transaction_uid' => isset($row->transaction_uid) ? $row->transaction_uid : null,
            'original_type' => isset($row->type) ? $row->type : null,
            'amount' => isset($row->amount) ? (string) $row->amount : (isset($raw['amount']) ? (string) $raw['amount'] : null),
            'currency' => isset($row->currency) ? $row->currency : (isset($raw['currency']) ? $raw['currency'] : null),
            'provider' => isset($row->provider) ? $row->provider : (isset($raw['provider']) ? $raw['provider'] : null),
            'recovery_reason' => 'rollback_required',
            'recovery_attempt' => (int) $attemptNo,
        ]);
    }

    private function rollbackTransactionId($row)
    {
        $base = isset($row->transaction_uid) && $row->transaction_uid
            ? $row->transaction_uid
            : (isset($row->transaction_id) && $row->transaction_id ? $row->transaction_id : $row->id);

        $id = 'rollback_'.$base;
        if (strlen($id) <= 191) {
            return $id;
        }

        return 'rollback_'.sha1($id);
    }

    private function rawRequest($row)
    {
        if (!isset($row->raw_request) || $row->raw_request === null) {
            return [];
        }

        if (is_array($row->raw_request)) {
            return $row->raw_request;
        }

        $decoded = json_decode($row->raw_request, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function operatorFor($row)
    {
        if (!Schema::hasTable('b2b_operators') || !isset($row->operator_id)) {
            return null;
        }

        return DB::table('b2b_operators')->where('id', $row->operator_id)->first();
    }

    private function rollbackAttemptCount($row)
    {
        if (!Schema::hasTable('b2b_wallet_transaction_attempts') || !isset($row->id)) {
            return 0;
        }

        return (int) DB::table('b2b_wallet_transaction_attempts')
            ->where('wallet_transaction_id', $row->id)
            ->where('type', 'rollback')
            ->count();
    }

    private function resolveOpenItems($row, $resolvedStatus, array $callbackResult)
    {
        if (!Schema::hasTable('b2b_wallet_reconciliation_items') || !$row || !isset($row->id)) {
            return;
        }

        DB::table('b2b_wallet_reconciliation_items')
            ->where('wallet_transaction_id', $row->id)
            ->whereIn('state', ['open', 'in_progress'])
            ->update($this->filterColumns('b2b_wallet_reconciliation_items', [
                'state' => 'resolved',
                'resolved_at' => Carbon::now(),
                'context' => $this->redactor->json([
                    'resolved_by' => 'wallet_rollback_recovery',
                    'resolved_status' => $resolvedStatus,
                    'rollback_recovery' => $this->recoveryContext($callbackResult, $this->rollbackAttemptCount($row), $this->maxRollbackAttempts()),
                ]),
                'updated_at' => Carbon::now(),
            ]));
    }

    private function openItem($row, $reason, array $context)
    {
        if (!Schema::hasTable('b2b_wallet_reconciliation_items') || !$row || !isset($row->id)) {
            return;
        }

        $now = Carbon::now();
        $status = $reason === 'manual_review'
            ? WalletTransactionStateMachine::STATUS_MANUAL_REVIEW
            : WalletTransactionStateMachine::STATUS_ROLLBACK_REQUIRED;

        $data = [
            'wallet_transaction_id' => $row->id,
            'operator_id' => isset($row->operator_id) ? $row->operator_id : null,
            'transaction_uid' => isset($row->transaction_uid) ? $row->transaction_uid : null,
            'status' => $status,
            'reason' => $reason,
            'priority' => $reason === 'rollback_required' ? 'high' : 'medium',
            'context' => $this->redactor->json($context),
            'detected_at' => $now,
            'updated_at' => $now,
        ];

        $existing = DB::table('b2b_wallet_reconciliation_items')
            ->where('wallet_transaction_id', $row->id)
            ->where('reason', $reason)
            ->whereIn('state', ['open', 'in_progress'])
            ->first();

        if ($existing) {
            DB::table('b2b_wallet_reconciliation_items')
                ->where('id', $existing->id)
                ->update($this->filterColumns('b2b_wallet_reconciliation_items', $data));
            return;
        }

        $data['state'] = 'open';
        $data['created_at'] = $now;

        DB::table('b2b_wallet_reconciliation_items')
            ->insert($this->filterColumns('b2b_wallet_reconciliation_items', $data));
    }

    private function recoveryContext(array $callbackResult, $attempts, $maxAttempts)
    {
        return [
            'ok' => !empty($callbackResult['ok']),
            'code' => isset($callbackResult['code']) ? $callbackResult['code'] : null,
            'message' => isset($callbackResult['message']) ? $callbackResult['message'] : null,
            'http_status' => isset($callbackResult['http_status']) ? $callbackResult['http_status'] : null,
            'duration_ms' => isset($callbackResult['duration_ms']) ? $callbackResult['duration_ms'] : null,
            'attempts' => (int) $attempts,
            'max_attempts' => (int) $maxAttempts,
        ];
    }

    private function maxRollbackAttempts()
    {
        $attempts = (int) config('b2b.wallet_rollback_max_attempts', 3);

        return $attempts > 0 ? $attempts : 3;
    }

    private function safeLimit($limit)
    {
        $limit = (int) $limit;
        if ($limit < 1) {
            return 50;
        }

        return min($limit, 500);
    }

    private function withoutNulls(array $payload)
    {
        $filtered = [];
        foreach ($payload as $key => $value) {
            if ($value !== null && $value !== '') {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
    }

    private function filterColumns($table, array $data)
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
