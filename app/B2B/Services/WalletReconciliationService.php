<?php

namespace VanguardLTE\B2B\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WalletReconciliationService
{
    protected $stateMachine;

    public function __construct(WalletTransactionStateMachine $stateMachine)
    {
        $this->stateMachine = $stateMachine;
    }

    public function scan($limit = 100, $pendingMinutes = null)
    {
        if (!Schema::hasTable('b2b_wallet_transactions')) {
            return ['processed' => 0, 'opened' => 0, 'updated' => 0, 'transitioned_unknown' => 0, 'message' => 'b2b_wallet_transactions table missing'];
        }

        if (!Schema::hasTable('b2b_wallet_reconciliation_items')) {
            return ['processed' => 0, 'opened' => 0, 'updated' => 0, 'transitioned_unknown' => 0, 'message' => 'b2b_wallet_reconciliation_items table missing'];
        }

        $limit = $this->safeLimit($limit);
        $pendingMinutes = $this->safePendingMinutes($pendingMinutes);
        $cutoff = Carbon::now()->subMinutes($pendingMinutes);
        $maxAttempts = $this->maxRetryAttempts();

        $rows = $this->candidateQuery($cutoff, $maxAttempts)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $result = [
            'processed' => 0,
            'opened' => 0,
            'updated' => 0,
            'transitioned_unknown' => 0,
        ];

        foreach ($rows as $row) {
            $result['processed']++;
            $reason = $this->reasonFor($row, $cutoff, $maxAttempts);

            if ($reason === 'stale_pending') {
                $this->stateMachine->transition($row, WalletTransactionStateMachine::STATUS_UNKNOWN, 'wallet_reconciliation_stale_pending', [
                    'processed_at' => Carbon::now(),
                    'last_error' => 'Pending wallet transaction exceeded reconciliation pending window.',
                ], [
                    'pending_minutes' => $pendingMinutes,
                ]);

                $row = DB::table('b2b_wallet_transactions')->where('id', $row->id)->first();
                $reason = 'unknown_result';
                $result['transitioned_unknown']++;
            }

            $opened = $this->openItem($row, $reason, $maxAttempts, $pendingMinutes);
            if ($opened) {
                $result['opened']++;
            } else {
                $result['updated']++;
            }
        }

        return $result;
    }

    private function candidateQuery(Carbon $cutoff, $maxAttempts)
    {
        return DB::table('b2b_wallet_transactions')
            ->where(function ($query) use ($cutoff, $maxAttempts) {
                $query->whereIn('status', [
                    WalletTransactionStateMachine::STATUS_UNKNOWN,
                    WalletTransactionStateMachine::STATUS_ROLLBACK_REQUIRED,
                    WalletTransactionStateMachine::STATUS_MANUAL_REVIEW,
                    WalletTransactionStateMachine::STATUS_DEAD_LETTER,
                ]);

                $query->orWhere(function ($stale) use ($cutoff) {
                    $stale->where('status', WalletTransactionStateMachine::STATUS_PENDING)
                        ->where('created_at', '<', $cutoff);
                });

                if (Schema::hasColumn('b2b_wallet_transactions', 'attempts')) {
                    $query->orWhere(function ($exhausted) use ($maxAttempts) {
                        $exhausted->whereIn('status', [
                                WalletTransactionStateMachine::STATUS_FAILED,
                                WalletTransactionStateMachine::STATUS_TIMEOUT,
                            ])
                            ->where('attempts', '>=', $maxAttempts);
                    });
                }
            });
    }

    private function openItem($row, $reason, $maxAttempts, $pendingMinutes)
    {
        $now = Carbon::now();
        $context = [
            'attempts' => isset($row->attempts) ? (int) $row->attempts : null,
            'max_attempts' => $maxAttempts,
            'pending_minutes' => $pendingMinutes,
            'created_at' => isset($row->created_at) ? $row->created_at : null,
            'updated_at' => isset($row->updated_at) ? $row->updated_at : null,
            'processed_at' => isset($row->processed_at) ? $row->processed_at : null,
            'last_error' => isset($row->last_error) ? $row->last_error : null,
        ];

        $data = [
            'wallet_transaction_id' => isset($row->id) ? $row->id : null,
            'operator_id' => isset($row->operator_id) ? $row->operator_id : null,
            'transaction_uid' => isset($row->transaction_uid) ? $row->transaction_uid : null,
            'status' => isset($row->status) ? $row->status : 'unknown',
            'reason' => $reason,
            'priority' => $this->priorityFor(isset($row->status) ? $row->status : null, $reason),
            'context' => json_encode($context),
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

            return false;
        }

        $data['state'] = 'open';
        $data['created_at'] = $now;

        DB::table('b2b_wallet_reconciliation_items')
            ->insert($this->filterColumns('b2b_wallet_reconciliation_items', $data));

        return true;
    }

    private function reasonFor($row, Carbon $cutoff, $maxAttempts)
    {
        $status = isset($row->status) ? $row->status : null;

        if ($status === WalletTransactionStateMachine::STATUS_PENDING
            && isset($row->created_at)
            && Carbon::parse($row->created_at)->lt($cutoff)) {
            return 'stale_pending';
        }

        if (in_array($status, [WalletTransactionStateMachine::STATUS_FAILED, WalletTransactionStateMachine::STATUS_TIMEOUT], true)
            && isset($row->attempts)
            && (int) $row->attempts >= $maxAttempts) {
            return 'retry_budget_exhausted';
        }

        if ($status === WalletTransactionStateMachine::STATUS_ROLLBACK_REQUIRED) {
            return 'rollback_required';
        }

        if ($status === WalletTransactionStateMachine::STATUS_MANUAL_REVIEW) {
            return 'manual_review';
        }

        if ($status === WalletTransactionStateMachine::STATUS_DEAD_LETTER) {
            return 'dead_letter';
        }

        return 'unknown_result';
    }

    private function priorityFor($status, $reason)
    {
        if (in_array($status, [
            WalletTransactionStateMachine::STATUS_DEAD_LETTER,
            WalletTransactionStateMachine::STATUS_ROLLBACK_REQUIRED,
        ], true)) {
            return 'high';
        }

        if (in_array($reason, ['unknown_result', 'manual_review', 'stale_pending'], true)) {
            return 'medium';
        }

        return 'normal';
    }

    private function safeLimit($limit)
    {
        $limit = (int) $limit;
        if ($limit < 1) {
            return 100;
        }

        return min($limit, 1000);
    }

    private function safePendingMinutes($minutes)
    {
        $minutes = $minutes === null
            ? (int) config('b2b.wallet_reconciliation_pending_minutes', 5)
            : (int) $minutes;

        return $minutes > 0 ? $minutes : 5;
    }

    private function maxRetryAttempts()
    {
        $attempts = (int) config('b2b.wallet_retry_max_attempts', 3);

        return $attempts > 0 ? $attempts : 3;
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
