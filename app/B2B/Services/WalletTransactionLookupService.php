<?php

namespace VanguardLTE\B2B\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WalletTransactionLookupService
{
    protected $redactor;

    public function __construct(B2BPayloadRedactor $redactor)
    {
        $this->redactor = $redactor;
    }

    public function findForOperator($operatorId, $transactionUid)
    {
        if (!Schema::hasTable('b2b_wallet_transactions')) {
            return null;
        }

        return DB::table('b2b_wallet_transactions')
            ->where('operator_id', $operatorId)
            ->where(function ($query) use ($transactionUid) {
                $query->where('transaction_uid', $transactionUid)
                    ->orWhere('transaction_id', $transactionUid);

                if (ctype_digit((string) $transactionUid)) {
                    $query->orWhere('id', (int) $transactionUid);
                }
            })
            ->first();
    }

    public function statusPayload($transaction)
    {
        return [
            'transaction' => $this->transactionSummary($transaction),
            'transitions' => $this->transitions($transaction),
            'attempts' => $this->attempts($transaction),
            'reconciliation_items' => $this->reconciliationItems($transaction),
            'manual_actions' => $this->manualActions($transaction),
            'next_actions' => $this->nextActions($transaction),
        ];
    }

    public function transitions($transaction, $limit = 100)
    {
        if (!$transaction || !isset($transaction->id) || !Schema::hasTable('b2b_wallet_transaction_transitions')) {
            return collect();
        }

        return DB::table('b2b_wallet_transaction_transitions')
            ->where('wallet_transaction_id', $transaction->id)
            ->where('operator_id', $transaction->operator_id)
            ->orderBy('id')
            ->limit((int) $limit)
            ->get()
            ->map(function ($row) {
                if (isset($row->context)) {
                    $decoded = json_decode($row->context, true);
                    $row->context = is_array($decoded) ? $this->redactor->redact($decoded) : null;
                }

                return $row;
            });
    }

    public function attempts($transaction, $limit = 20)
    {
        if (!$transaction || !isset($transaction->id) || !Schema::hasTable('b2b_wallet_transaction_attempts')) {
            return collect();
        }

        return DB::table('b2b_wallet_transaction_attempts')
            ->where('wallet_transaction_id', $transaction->id)
            ->where('operator_id', $transaction->operator_id)
            ->orderBy('id', 'desc')
            ->limit((int) $limit)
            ->get()
            ->map(function ($row) {
                if (isset($row->request_body)) {
                    $row->request_body = $this->decodeAndRedact($row->request_body);
                }
                if (isset($row->response_body)) {
                    $row->response_body = $this->decodeAndRedact($row->response_body);
                }

                return $row;
            });
    }

    public function callbackLogs($transaction, $limit = 20)
    {
        if (!$transaction || !isset($transaction->id) || !Schema::hasTable('b2b_wallet_callback_logs')) {
            return collect();
        }

        return DB::table('b2b_wallet_callback_logs')
            ->where('wallet_transaction_id', $transaction->id)
            ->where('operator_id', $transaction->operator_id)
            ->orderBy('created_at', 'desc')
            ->limit((int) $limit)
            ->get()
            ->map(function ($row) {
                if (isset($row->request_body)) {
                    $row->request_body = $this->decodeAndRedact($row->request_body);
                }
                if (isset($row->response_body)) {
                    $row->response_body = $this->decodeAndRedact($row->response_body);
                }

                return $row;
            });
    }

    public function reconciliationItems($transaction, $limit = 20)
    {
        if (!$transaction || !isset($transaction->id) || !Schema::hasTable('b2b_wallet_reconciliation_items')) {
            return collect();
        }

        return DB::table('b2b_wallet_reconciliation_items')
            ->where('wallet_transaction_id', $transaction->id)
            ->where('operator_id', $transaction->operator_id)
            ->orderBy('detected_at', 'desc')
            ->orderBy('id', 'desc')
            ->limit((int) $limit)
            ->get()
            ->map(function ($row) {
                if (isset($row->context)) {
                    $decoded = json_decode($row->context, true);
                    $row->context = is_array($decoded) ? $this->redactor->redact($decoded) : null;
                }

                return $row;
            });
    }

    public function manualActions($transaction, $limit = 20)
    {
        if (!$transaction || !isset($transaction->id) || !Schema::hasTable('b2b_wallet_manual_actions')) {
            return collect();
        }

        return DB::table('b2b_wallet_manual_actions')
            ->where('wallet_transaction_id', $transaction->id)
            ->where('operator_id', $transaction->operator_id)
            ->orderBy('id', 'desc')
            ->limit((int) $limit)
            ->get()
            ->map(function ($row) {
                if (isset($row->context)) {
                    $decoded = json_decode($row->context, true);
                    $row->context = is_array($decoded) ? $this->redactor->redact($decoded) : null;
                }

                return $row;
            });
    }

    private function transactionSummary($transaction)
    {
        return [
            'id' => isset($transaction->id) ? $transaction->id : null,
            'transaction_uid' => isset($transaction->transaction_uid) ? $transaction->transaction_uid : null,
            'transaction_id' => isset($transaction->transaction_id) ? $transaction->transaction_id : null,
            'operator_id' => isset($transaction->operator_id) ? $transaction->operator_id : null,
            'player_id' => isset($transaction->player_id) ? $transaction->player_id : null,
            'operator_player_id' => isset($transaction->operator_player_id) ? $transaction->operator_player_id : null,
            'session_id' => isset($transaction->session_id) ? $transaction->session_id : null,
            'game_uid' => isset($transaction->game_uid) ? $transaction->game_uid : (isset($transaction->game_id) ? $transaction->game_id : null),
            'round_id' => isset($transaction->round_id) ? $transaction->round_id : null,
            'type' => isset($transaction->type) ? $transaction->type : null,
            'amount' => isset($transaction->amount) ? (string) $transaction->amount : null,
            'currency' => isset($transaction->currency) ? $transaction->currency : null,
            'status' => isset($transaction->status) ? $transaction->status : null,
            'attempts' => isset($transaction->attempts) ? (int) $transaction->attempts : null,
            'last_error' => isset($transaction->last_error) ? $transaction->last_error : null,
            'processed_at' => isset($transaction->processed_at) ? $transaction->processed_at : null,
            'created_at' => isset($transaction->created_at) ? $transaction->created_at : null,
            'updated_at' => isset($transaction->updated_at) ? $transaction->updated_at : null,
        ];
    }

    private function nextActions($transaction)
    {
        $status = $transaction && isset($transaction->status) ? $transaction->status : null;

        if (in_array($status, ['failed', 'timeout', 'unknown'], true)) {
            return ['retry_wallet', 'reconcile_wallet', 'manual_review'];
        }

        if (in_array($status, ['dead_letter', 'manual_review', 'rollback_required'], true)) {
            return ['manual_review', 'reconcile_wallet'];
        }

        if ($status === 'reversed') {
            return [];
        }

        if ($status === 'pending') {
            return ['wait_for_callback', 'reconcile_if_stale'];
        }

        if ($status === 'success') {
            return [];
        }

        return ['inspect_transaction'];
    }

    private function decodeAndRedact($value)
    {
        if ($value === null) {
            return null;
        }

        $decoded = is_string($value) ? json_decode($value, true) : null;
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $this->redactor->redact($decoded);
        }

        return $this->redactor->storageValue($value);
    }
}
