<?php

namespace VanguardLTE\B2B\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;

class B2BCaseManagementService
{
    private $redactor;
    private $audit;

    public function __construct(B2BPayloadRedactor $redactor, B2BOperatorAuditLogger $audit)
    {
        $this->redactor = $redactor;
        $this->audit = $audit;
    }

    public function cases($limit = 50)
    {
        if (!Schema::hasTable('b2b_wallet_reconciliation_items')) {
            return collect();
        }

        $query = DB::table('b2b_wallet_reconciliation_items as ri')
            ->select(
                'ri.id',
                'ri.wallet_transaction_id',
                'ri.operator_id',
                'ri.transaction_uid',
                'ri.status',
                'ri.reason',
                'ri.priority',
                'ri.state',
                'ri.context',
                'ri.detected_at',
                'ri.resolved_at',
                'ri.created_at',
                'ri.updated_at'
            );

        if (Schema::hasTable('b2b_wallet_transactions')) {
            $query->leftJoin('b2b_wallet_transactions as tx', 'tx.id', '=', 'ri.wallet_transaction_id')
                ->addSelect(
                    'tx.type as transaction_type',
                    'tx.amount as transaction_amount',
                    'tx.currency as transaction_currency',
                    'tx.attempts as transaction_attempts',
                    'tx.last_error as transaction_last_error'
                );
        }

        return $query
            ->orderByRaw("CASE ri.state WHEN 'open' THEN 0 WHEN 'in_progress' THEN 1 ELSE 2 END")
            ->orderByRaw("CASE ri.priority WHEN 'high' THEN 0 WHEN 'medium' THEN 1 ELSE 2 END")
            ->orderBy('ri.id', 'desc')
            ->limit((int) $limit)
            ->get()
            ->map(function ($case) {
                $case->context_display = $this->formatContext(isset($case->context) ? $case->context : null);

                return $case;
            });
    }

    public function claim($caseId, $actor, $reason, array $context)
    {
        return $this->transition($caseId, 'claim', 'case.claimed', 'in_progress', $actor, $reason, $context);
    }

    public function resolve($caseId, $actor, $reason, array $context)
    {
        return $this->transition($caseId, 'resolve', 'case.resolved', 'resolved', $actor, $reason, $context);
    }

    public function reopen($caseId, $actor, $reason, array $context)
    {
        return $this->transition($caseId, 'reopen', 'case.reopened', 'open', $actor, $reason, $context);
    }

    private function transition($caseId, $action, $eventType, $newState, $actor, $reason, array $context)
    {
        $this->assertTablesReady();

        $case = DB::table('b2b_wallet_reconciliation_items')->where('id', (int) $caseId)->first();
        if (!$case) {
            throw new InvalidArgumentException('B2B case was not found.');
        }

        $actor = $this->requiredText($actor, 'B2B case action requires an actor.');
        $reason = $this->requiredText($reason, 'B2B case action requires a reason.');
        $previousState = isset($case->state) ? $case->state : null;
        $this->assertAllowedTransition($action, $previousState);

        $now = Carbon::now();
        $caseContext = $this->nextContext($case, $action, $newState, $actor, $reason, $context, $now);
        $updates = [
            'state' => $newState,
            'context' => $this->redactor->json($caseContext),
            'updated_at' => $now,
        ];

        if ($action === 'resolve') {
            $updates['resolved_at'] = $now;
        }

        if ($action === 'reopen') {
            $updates['resolved_at'] = null;
        }

        DB::table('b2b_wallet_reconciliation_items')
            ->where('id', $case->id)
            ->update($this->filterColumns('b2b_wallet_reconciliation_items', $updates));

        $fresh = DB::table('b2b_wallet_reconciliation_items')->where('id', $case->id)->first();
        $this->recordAudit($fresh ?: $case, $eventType, $action, $previousState, $newState, $actor, $reason, $context);

        return $fresh ?: $case;
    }

    private function assertTablesReady()
    {
        foreach (['b2b_wallet_reconciliation_items', 'b2b_operator_audit_events'] as $table) {
            if (!Schema::hasTable($table)) {
                throw new RuntimeException('B2B case/audit tables are missing. Run: php artisan migrate');
            }
        }
    }

    private function assertAllowedTransition($action, $state)
    {
        $state = trim((string) $state);

        if ($action === 'claim' && in_array($state, ['open', 'in_progress'], true)) {
            return;
        }

        if ($action === 'resolve' && in_array($state, ['open', 'in_progress'], true)) {
            return;
        }

        if ($action === 'reopen' && $state === 'resolved') {
            return;
        }

        throw new InvalidArgumentException('B2B case action ' . $action . ' is not allowed from state ' . ($state ?: 'unknown') . '.');
    }

    private function nextContext($case, $action, $newState, $actor, $reason, array $context, Carbon $now)
    {
        $caseContext = $this->decodeContext(isset($case->context) ? $case->context : null);
        $events = isset($caseContext['case_events']) && is_array($caseContext['case_events'])
            ? $caseContext['case_events']
            : [];

        $event = [
            'action' => $action,
            'state' => $newState,
            'actor' => $actor,
            'reason' => $reason,
            'at' => $now->toIso8601String(),
            'permission' => isset($context['permission']) ? $context['permission'] : null,
            'step_up' => !empty($context['step_up']),
            'source' => isset($context['source']) ? $context['source'] : null,
        ];

        $events[] = $event;
        $caseContext['case_events'] = $events;
        $caseContext['case_state'] = [
            'state' => $newState,
            'last_action' => $action,
            'last_actor' => $actor,
            'last_reason' => $reason,
            'updated_at' => $now->toIso8601String(),
        ];

        if ($action === 'claim') {
            $caseContext['case_assignment'] = [
                'assigned_to' => $actor,
                'assigned_at' => $now->toIso8601String(),
            ];
        }

        if ($action === 'resolve') {
            $caseContext['case_resolution'] = [
                'resolved_by' => $actor,
                'resolved_reason' => $reason,
                'resolved_at' => $now->toIso8601String(),
            ];
        }

        if ($action === 'reopen') {
            unset($caseContext['case_resolution']);
            $caseContext['case_reopened'] = [
                'reopened_by' => $actor,
                'reopened_reason' => $reason,
                'reopened_at' => $now->toIso8601String(),
            ];
        }

        return $caseContext;
    }

    private function decodeContext($context)
    {
        if ($context === null || $context === '') {
            return [];
        }

        if (is_array($context)) {
            return $context;
        }

        $decoded = json_decode((string) $context, true);

        return json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : ['raw_context' => (string) $context];
    }

    private function formatContext($context)
    {
        if ($context === null || $context === '') {
            return '';
        }

        $redacted = $this->redactor->storageValue($context);
        $decoded = json_decode((string) $redacted, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $encoded = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            return $encoded === false ? '' : $encoded;
        }

        return (string) $redacted;
    }

    private function recordAudit($case, $eventType, $action, $previousState, $newState, $actor, $reason, array $context)
    {
        $this->audit->record(
            isset($case->operator_id) ? $case->operator_id : null,
            $eventType,
            'reconciliation_item',
            isset($case->id) ? $case->id : null,
            $actor,
            $reason,
            [
                'case_action' => $action,
                'wallet_transaction_id' => isset($case->wallet_transaction_id) ? $case->wallet_transaction_id : null,
                'transaction_uid' => isset($case->transaction_uid) ? $case->transaction_uid : null,
                'status' => isset($case->status) ? $case->status : null,
                'reason_code' => isset($case->reason) ? $case->reason : null,
                'priority' => isset($case->priority) ? $case->priority : null,
                'previous_state' => $previousState,
                'new_state' => $newState,
                'permission' => isset($context['permission']) ? $context['permission'] : null,
                'step_up' => !empty($context['step_up']),
                'source' => isset($context['source']) ? $context['source'] : null,
                'ip_address' => isset($context['ip_address']) ? $context['ip_address'] : null,
                'user_agent' => isset($context['user_agent']) ? $context['user_agent'] : null,
            ],
            isset($context['ip_address']) ? $context['ip_address'] : null,
            isset($context['user_agent']) ? $context['user_agent'] : null
        );
    }

    private function requiredText($value, $message)
    {
        $value = trim((string) $value);
        if ($value === '') {
            throw new InvalidArgumentException($message);
        }

        return $value;
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
