<?php

namespace VanguardLTE\B2B\Services;

use Carbon\Carbon;
use InvalidArgumentException;
use RuntimeException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WalletManualActionService
{
    protected $stateMachine;
    protected $redactor;
    protected $audit;

    public function __construct(WalletTransactionStateMachine $stateMachine, B2BPayloadRedactor $redactor, B2BOperatorAuditLogger $audit)
    {
        $this->stateMachine = $stateMachine;
        $this->redactor = $redactor;
        $this->audit = $audit;
    }

    public function apply($transactionUid, $action, $reason, $actor, $operatorId = null, array $context = [])
    {
        $transaction = $this->findTransaction($transactionUid, $operatorId);
        $normalizedAction = $this->normalizeAction($action);
        $targetStatus = $this->targetStatus($normalizedAction);
        $reason = trim((string) $reason);
        $actor = trim((string) $actor);

        if ($reason === '') {
            throw new InvalidArgumentException('Manual wallet actions require a reason.');
        }

        if ($actor === '') {
            throw new InvalidArgumentException('Manual wallet actions require an actor.');
        }

        $fromStatus = isset($transaction->status) ? $transaction->status : null;
        if (!$this->stateMachine->canTransition($fromStatus, $targetStatus)) {
            throw new InvalidArgumentException('Manual action '.$normalizedAction.' cannot move wallet transaction from '.$fromStatus.' to '.$targetStatus.'.');
        }

        $updates = [
            'processed_at' => Carbon::now(),
            'last_error' => $this->lastErrorFor($targetStatus, $reason),
        ];

        $transitionContext = array_merge($context, [
            'manual_action' => $normalizedAction,
            'manual_actor' => $actor,
            'manual_reason' => $reason,
        ]);

        return DB::transaction(function () use ($transaction, $normalizedAction, $fromStatus, $targetStatus, $reason, $actor, $updates, $transitionContext) {
            $this->stateMachine->transition(
                $transaction,
                $targetStatus,
                'manual_'.$normalizedAction,
                $updates,
                $transitionContext,
                'manual:'.$actor
            );

            $actionId = $this->recordManualAction($transaction, $normalizedAction, $targetStatus, $reason, $actor, $transitionContext);
            $this->syncReconciliationItem($transaction, $normalizedAction, $targetStatus, $reason, $actor, $actionId);
            $this->recordOperatorAudit($transaction, $normalizedAction, $fromStatus, $targetStatus, $reason, $actor, $actionId, $transitionContext);

            return [
                'wallet_transaction_id' => $transaction->id,
                'transaction_uid' => isset($transaction->transaction_uid) ? $transaction->transaction_uid : null,
                'from_status' => isset($transaction->status) ? $transaction->status : null,
                'to_status' => $targetStatus,
                'manual_action_id' => $actionId,
            ];
        });
    }

    public function supportedActions()
    {
        return [
            'mark-review',
            'resolve-success',
            'resolve-failed',
            'mark-rollback-required',
            'mark-reversed',
            'dead-letter',
        ];
    }

    private function findTransaction($transactionUid, $operatorId = null)
    {
        if (!Schema::hasTable('b2b_wallet_transactions')) {
            throw new RuntimeException('b2b_wallet_transactions table missing.');
        }

        $query = DB::table('b2b_wallet_transactions')
            ->where(function ($q) use ($transactionUid) {
                $q->where('transaction_uid', $transactionUid)
                    ->orWhere('transaction_id', $transactionUid);

                if (ctype_digit((string) $transactionUid)) {
                    $q->orWhere('id', (int) $transactionUid);
                }
            });

        if ($operatorId !== null && $operatorId !== '') {
            $query->where('operator_id', (int) $operatorId);
        }

        $matches = $query->orderBy('id')->limit(2)->get();

        if ($matches->count() === 0) {
            throw new RuntimeException('Wallet transaction was not found.');
        }

        if ($matches->count() > 1) {
            throw new RuntimeException('Wallet transaction lookup is ambiguous; pass --operator-id.');
        }

        return $matches->first();
    }

    private function normalizeAction($action)
    {
        $action = strtolower(trim((string) $action));
        $action = str_replace('_', '-', $action);

        $aliases = [
            'review' => 'mark-review',
            'manual-review' => 'mark-review',
            'success' => 'resolve-success',
            'failed' => 'resolve-failed',
            'fail' => 'resolve-failed',
            'rollback-required' => 'mark-rollback-required',
            'reversed' => 'mark-reversed',
            'reverse' => 'mark-reversed',
            'deadletter' => 'dead-letter',
        ];

        if (isset($aliases[$action])) {
            $action = $aliases[$action];
        }

        if (!in_array($action, $this->supportedActions(), true)) {
            throw new InvalidArgumentException('Unsupported manual wallet action: '.$action.'.');
        }

        return $action;
    }

    private function targetStatus($action)
    {
        $targets = [
            'mark-review' => WalletTransactionStateMachine::STATUS_MANUAL_REVIEW,
            'resolve-success' => WalletTransactionStateMachine::STATUS_SUCCESS,
            'resolve-failed' => WalletTransactionStateMachine::STATUS_FAILED,
            'mark-rollback-required' => WalletTransactionStateMachine::STATUS_ROLLBACK_REQUIRED,
            'mark-reversed' => WalletTransactionStateMachine::STATUS_REVERSED,
            'dead-letter' => WalletTransactionStateMachine::STATUS_DEAD_LETTER,
        ];

        return $targets[$action];
    }

    private function recordManualAction($transaction, $action, $targetStatus, $reason, $actor, array $context)
    {
        if (!Schema::hasTable('b2b_wallet_manual_actions')) {
            return null;
        }

        $row = [
            'wallet_transaction_id' => $transaction->id,
            'operator_id' => isset($transaction->operator_id) ? $transaction->operator_id : null,
            'transaction_uid' => isset($transaction->transaction_uid) ? $transaction->transaction_uid : null,
            'action' => $action,
            'from_status' => isset($transaction->status) ? $transaction->status : null,
            'to_status' => $targetStatus,
            'actor' => $actor,
            'reason' => $reason,
            'context' => $this->redactor->json($context),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];

        return DB::table('b2b_wallet_manual_actions')
            ->insertGetId($this->filterColumns('b2b_wallet_manual_actions', $row));
    }

    private function recordOperatorAudit($transaction, $action, $fromStatus, $targetStatus, $reason, $actor, $actionId, array $context)
    {
        $transactionUid = isset($transaction->transaction_uid) ? $transaction->transaction_uid : null;

        $this->audit->record(
            isset($transaction->operator_id) ? $transaction->operator_id : null,
            'wallet.manual_action.applied',
            'wallet_transaction',
            $transactionUid ?: (isset($transaction->id) ? $transaction->id : null),
            $actor,
            $reason,
            [
                'wallet_transaction_id' => isset($transaction->id) ? $transaction->id : null,
                'transaction_uid' => $transactionUid,
                'manual_action_id' => $actionId,
                'manual_action' => $action,
                'from_status' => $fromStatus,
                'to_status' => $targetStatus,
                'permission' => isset($context['permission']) ? $context['permission'] : null,
                'step_up' => !empty($context['step_up']),
                'source' => isset($context['source']) ? $context['source'] : null,
            ]
        );
    }

    private function syncReconciliationItem($transaction, $action, $targetStatus, $reason, $actor, $actionId)
    {
        if (!Schema::hasTable('b2b_wallet_reconciliation_items')) {
            return;
        }

        if (in_array($targetStatus, [
            WalletTransactionStateMachine::STATUS_MANUAL_REVIEW,
            WalletTransactionStateMachine::STATUS_ROLLBACK_REQUIRED,
            WalletTransactionStateMachine::STATUS_DEAD_LETTER,
        ], true)) {
            $this->openReconciliationItem($transaction, $action, $targetStatus, $reason, $actor, $actionId);
            return;
        }

        $this->resolveOpenReconciliationItems($transaction, $action, $targetStatus, $reason, $actor, $actionId);
    }

    private function openReconciliationItem($transaction, $action, $targetStatus, $reason, $actor, $actionId)
    {
        $reasonCode = $this->reconciliationReason($action, $targetStatus);
        $existing = DB::table('b2b_wallet_reconciliation_items')
            ->where('wallet_transaction_id', $transaction->id)
            ->where('reason', $reasonCode)
            ->whereIn('state', ['open', 'in_progress'])
            ->first();

        $row = [
            'wallet_transaction_id' => $transaction->id,
            'operator_id' => isset($transaction->operator_id) ? $transaction->operator_id : null,
            'transaction_uid' => isset($transaction->transaction_uid) ? $transaction->transaction_uid : null,
            'status' => $targetStatus,
            'reason' => $reasonCode,
            'priority' => in_array($targetStatus, [
                WalletTransactionStateMachine::STATUS_ROLLBACK_REQUIRED,
                WalletTransactionStateMachine::STATUS_DEAD_LETTER,
            ], true) ? 'high' : 'medium',
            'state' => 'open',
            'context' => $this->redactor->json([
                'manual_action_id' => $actionId,
                'manual_action' => $action,
                'manual_actor' => $actor,
                'manual_reason' => $reason,
            ]),
            'detected_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];

        if ($existing) {
            DB::table('b2b_wallet_reconciliation_items')
                ->where('id', $existing->id)
                ->update($this->filterColumns('b2b_wallet_reconciliation_items', $row));
            return;
        }

        $row['created_at'] = Carbon::now();
        DB::table('b2b_wallet_reconciliation_items')
            ->insert($this->filterColumns('b2b_wallet_reconciliation_items', $row));
    }

    private function resolveOpenReconciliationItems($transaction, $action, $targetStatus, $reason, $actor, $actionId)
    {
        DB::table('b2b_wallet_reconciliation_items')
            ->where('wallet_transaction_id', $transaction->id)
            ->whereIn('state', ['open', 'in_progress'])
            ->update($this->filterColumns('b2b_wallet_reconciliation_items', [
                'state' => 'resolved',
                'resolved_at' => Carbon::now(),
                'context' => $this->redactor->json([
                    'manual_action_id' => $actionId,
                    'manual_action' => $action,
                    'manual_actor' => $actor,
                    'manual_reason' => $reason,
                    'resolved_status' => $targetStatus,
                ]),
                'updated_at' => Carbon::now(),
            ]));
    }

    private function reconciliationReason($action, $targetStatus)
    {
        if ($targetStatus === WalletTransactionStateMachine::STATUS_MANUAL_REVIEW) {
            return 'manual_review';
        }

        if ($targetStatus === WalletTransactionStateMachine::STATUS_ROLLBACK_REQUIRED) {
            return 'rollback_required';
        }

        if ($targetStatus === WalletTransactionStateMachine::STATUS_DEAD_LETTER) {
            return 'dead_letter';
        }

        return $action;
    }

    private function lastErrorFor($targetStatus, $reason)
    {
        if (in_array($targetStatus, [
            WalletTransactionStateMachine::STATUS_SUCCESS,
            WalletTransactionStateMachine::STATUS_REVERSED,
        ], true)) {
            return null;
        }

        return 'Manual action: '.$reason;
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
