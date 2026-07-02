<?php

namespace VanguardLTE\B2B\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;

class B2BOperatorSupportCaseService
{
    private $redactor;
    private $audit;

    public function __construct(B2BPayloadRedactor $redactor, B2BOperatorAuditLogger $audit)
    {
        $this->redactor = $redactor;
        $this->audit = $audit;
    }

    public function comment($operator, $transactionUid, $message, array $context = [])
    {
        $this->assertTablesReady();

        if (!$operator || !isset($operator->id) || !isset($operator->operator_uid)) {
            throw new InvalidArgumentException('B2B operator context is missing.');
        }

        $transactionUid = trim((string) $transactionUid);
        if ($transactionUid === '') {
            throw new InvalidArgumentException('Support case transaction UID is required.');
        }

        $message = $this->safeText($message);
        if ($message === '') {
            throw new InvalidArgumentException('Support case comment message is required.');
        }

        $externalReference = $this->safeText(isset($context['external_reference']) ? $context['external_reference'] : null);

        return DB::transaction(function () use ($operator, $transactionUid, $message, $externalReference, $context) {
            $case = DB::table('b2b_wallet_reconciliation_items')
                ->where('operator_id', (int) $operator->id)
                ->where('transaction_uid', $transactionUid)
                ->whereIn('state', ['open', 'in_progress'])
                ->orderBy('id', 'desc')
                ->first();

            if (!$case) {
                throw new InvalidArgumentException('Support case was not found for this operator.');
            }

            $now = Carbon::now();
            $caseContext = $this->decodeContext(isset($case->context) ? $case->context : null);
            $comments = isset($caseContext['operator_comments']) && is_array($caseContext['operator_comments'])
                ? $caseContext['operator_comments']
                : [];

            $comment = [
                'message' => $message,
                'external_reference' => $externalReference ?: null,
                'actor' => 'operator:' . $operator->operator_uid,
                'source' => 'operator_portal',
                'request_id' => isset($context['request_id']) ? $context['request_id'] : null,
                'at' => $now->toIso8601String(),
            ];

            $comments[] = $comment;
            $caseContext['operator_comments'] = $comments;
            $caseContext['operator_follow_up'] = [
                'last_message' => $message,
                'last_external_reference' => $externalReference ?: null,
                'last_actor' => 'operator:' . $operator->operator_uid,
                'last_at' => $now->toIso8601String(),
                'comment_count' => count($comments),
            ];

            DB::table('b2b_wallet_reconciliation_items')
                ->where('id', $case->id)
                ->where('operator_id', (int) $operator->id)
                ->update($this->filterColumns('b2b_wallet_reconciliation_items', [
                    'context' => $this->redactor->json($caseContext),
                    'updated_at' => $now,
                ]));

            $this->audit->record(
                (int) $operator->id,
                'case.operator_commented',
                'reconciliation_item',
                $case->id,
                'operator:' . $operator->operator_uid,
                $message,
                [
                    'transaction_uid' => $transactionUid,
                    'state' => isset($case->state) ? $case->state : null,
                    'status' => isset($case->status) ? $case->status : null,
                    'priority' => isset($case->priority) ? $case->priority : null,
                    'source' => 'operator_portal',
                    'request_id' => isset($context['request_id']) ? $context['request_id'] : null,
                    'external_reference' => $externalReference ?: null,
                    'comment_count' => count($comments),
                ],
                isset($context['ip_address']) ? $context['ip_address'] : null,
                isset($context['user_agent']) ? $context['user_agent'] : null
            );

            return [
                'case_id' => (int) $case->id,
                'transaction_uid' => $transactionUid,
                'state' => isset($case->state) ? $case->state : null,
                'comment_count' => count($comments),
                'commented_at' => $now->toIso8601String(),
            ];
        });
    }

    private function assertTablesReady()
    {
        foreach (['b2b_wallet_reconciliation_items', 'b2b_operator_audit_events'] as $table) {
            if (!Schema::hasTable($table)) {
                throw new RuntimeException('B2B support case tables are missing. Run: php artisan migrate');
            }
        }
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

        return json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : ['raw_context' => $this->safeText($context)];
    }

    private function safeText($value)
    {
        if ($value === null) {
            return '';
        }

        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        return substr($this->redactor->storageValue($value), 0, 1000);
    }

    private function filterColumns($table, array $values)
    {
        $filtered = [];
        foreach ($values as $column => $value) {
            if (Schema::hasColumn($table, $column)) {
                $filtered[$column] = $value;
            }
        }

        return $filtered;
    }
}
