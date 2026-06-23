<?php

namespace VanguardLTE\B2B\Services;

use Carbon\Carbon;
use InvalidArgumentException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WalletTransactionStateMachine
{
    const STATUS_PENDING = 'pending';
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED = 'failed';
    const STATUS_TIMEOUT = 'timeout';
    const STATUS_UNKNOWN = 'unknown';
    const STATUS_ROLLBACK_REQUIRED = 'rollback_required';
    const STATUS_REVERSED = 'reversed';
    const STATUS_MANUAL_REVIEW = 'manual_review';
    const STATUS_DEAD_LETTER = 'dead_letter';

    public function transition($transaction, $toStatus, $reason, array $updates = [], array $context = [], $actor = 'system')
    {
        if (!$transaction || !isset($transaction->id)) {
            throw new InvalidArgumentException('Wallet transaction is required for a state transition.');
        }

        $fromStatus = isset($transaction->status) ? $transaction->status : null;
        if (!$this->canTransition($fromStatus, $toStatus)) {
            throw new InvalidArgumentException('Illegal wallet transaction transition from '.$fromStatus.' to '.$toStatus.'.');
        }

        $updates['status'] = $toStatus;
        $updates['updated_at'] = Carbon::now();

        DB::transaction(function () use ($transaction, $fromStatus, $toStatus, $reason, $updates, $context, $actor) {
            if (Schema::hasTable('b2b_wallet_transactions')) {
                DB::table('b2b_wallet_transactions')
                    ->where('id', $transaction->id)
                    ->update($this->filterColumns('b2b_wallet_transactions', $updates));
            }

            $this->record($transaction, $fromStatus, $toStatus, $reason, $context, $actor);
        });
    }

    public function record($transaction, $fromStatus, $toStatus, $reason, array $context = [], $actor = 'system')
    {
        if (!Schema::hasTable('b2b_wallet_transaction_transitions')
            || !Schema::hasColumn('b2b_wallet_transaction_transitions', 'to_status')) {
            return;
        }

        $row = [
            'wallet_transaction_id' => $transaction && isset($transaction->id) ? $transaction->id : null,
            'operator_id' => $transaction && isset($transaction->operator_id) ? $transaction->operator_id : null,
            'transaction_uid' => $transaction && isset($transaction->transaction_uid) ? $transaction->transaction_uid : null,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'reason' => $reason,
            'actor' => $actor,
            'context' => $this->json($context),
            'created_at' => Carbon::now(),
        ];

        DB::table('b2b_wallet_transaction_transitions')
            ->insert($this->filterColumns('b2b_wallet_transaction_transitions', $row));
    }

    public function canTransition($fromStatus, $toStatus)
    {
        $fromStatus = $fromStatus ?: '__new__';

        $allowed = [
            '__new__' => [self::STATUS_PENDING],
            self::STATUS_PENDING => [
                self::STATUS_SUCCESS,
                self::STATUS_FAILED,
                self::STATUS_TIMEOUT,
                self::STATUS_UNKNOWN,
                self::STATUS_ROLLBACK_REQUIRED,
                self::STATUS_MANUAL_REVIEW,
                self::STATUS_DEAD_LETTER,
            ],
            self::STATUS_FAILED => [
                self::STATUS_FAILED,
                self::STATUS_TIMEOUT,
                self::STATUS_SUCCESS,
                self::STATUS_UNKNOWN,
                self::STATUS_ROLLBACK_REQUIRED,
                self::STATUS_MANUAL_REVIEW,
                self::STATUS_DEAD_LETTER,
            ],
            self::STATUS_TIMEOUT => [
                self::STATUS_TIMEOUT,
                self::STATUS_FAILED,
                self::STATUS_SUCCESS,
                self::STATUS_UNKNOWN,
                self::STATUS_ROLLBACK_REQUIRED,
                self::STATUS_MANUAL_REVIEW,
                self::STATUS_DEAD_LETTER,
            ],
            self::STATUS_UNKNOWN => [
                self::STATUS_UNKNOWN,
                self::STATUS_FAILED,
                self::STATUS_TIMEOUT,
                self::STATUS_SUCCESS,
                self::STATUS_ROLLBACK_REQUIRED,
                self::STATUS_REVERSED,
                self::STATUS_MANUAL_REVIEW,
                self::STATUS_DEAD_LETTER,
            ],
            self::STATUS_ROLLBACK_REQUIRED => [
                self::STATUS_REVERSED,
                self::STATUS_MANUAL_REVIEW,
                self::STATUS_DEAD_LETTER,
                self::STATUS_FAILED,
                self::STATUS_TIMEOUT,
            ],
            self::STATUS_SUCCESS => [
                self::STATUS_SUCCESS,
                self::STATUS_REVERSED,
                self::STATUS_ROLLBACK_REQUIRED,
                self::STATUS_MANUAL_REVIEW,
            ],
            self::STATUS_REVERSED => [
                self::STATUS_REVERSED,
                self::STATUS_MANUAL_REVIEW,
            ],
            self::STATUS_MANUAL_REVIEW => [
                self::STATUS_MANUAL_REVIEW,
                self::STATUS_SUCCESS,
                self::STATUS_FAILED,
                self::STATUS_TIMEOUT,
                self::STATUS_UNKNOWN,
                self::STATUS_ROLLBACK_REQUIRED,
                self::STATUS_REVERSED,
                self::STATUS_DEAD_LETTER,
            ],
            self::STATUS_DEAD_LETTER => [
                self::STATUS_MANUAL_REVIEW,
            ],
            'accepted' => [
                self::STATUS_REVERSED,
                self::STATUS_ROLLBACK_REQUIRED,
                self::STATUS_MANUAL_REVIEW,
            ],
            'rejected' => [
                self::STATUS_MANUAL_REVIEW,
                self::STATUS_DEAD_LETTER,
            ],
        ];

        return isset($allowed[$fromStatus]) && in_array($toStatus, $allowed[$fromStatus], true);
    }

    private function json(array $value)
    {
        $json = json_encode($value);

        return $json === false ? null : $json;
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
