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

        $this->assertOperator($operator);
        $transactionUid = $this->requiredTransactionUid($transactionUid);

        $message = $this->safeText($message);
        if ($message === '') {
            throw new InvalidArgumentException('Support case comment message is required.');
        }

        $externalReference = $this->safeText(isset($context['external_reference']) ? $context['external_reference'] : null);

        return DB::transaction(function () use ($operator, $transactionUid, $message, $externalReference, $context) {
            $case = $this->ownedCase((int) $operator->id, $transactionUid, true);

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

    public function show($operator, $transactionUid, $limit = 50)
    {
        $this->assertTablesReady();
        $this->assertOperator($operator);

        $case = $this->ownedCase((int) $operator->id, $this->requiredTransactionUid($transactionUid), false);
        $context = $this->decodeContext(isset($case->context) ? $case->context : null);
        $comments = $this->operatorComments($context, $limit);

        $detailEndpoint = isset($case->transaction_uid) ? $this->supportCaseDetailEndpoint($case->transaction_uid) : null;
        $isCommentable = in_array(isset($case->state) ? $case->state : null, ['open', 'in_progress'], true);

        return [
            'case_id' => (int) $case->id,
            'transaction_uid' => isset($case->transaction_uid) ? $this->safeText($case->transaction_uid, 191) : null,
            'status' => isset($case->status) ? $this->safeText($case->status, 60) : null,
            'reason' => isset($case->reason) ? $this->safeText($case->reason, 160) : null,
            'priority' => isset($case->priority) ? $this->safeText($case->priority, 40) : null,
            'state' => isset($case->state) ? $this->safeText($case->state, 40) : null,
            'comment_count' => $this->operatorCommentCount($context),
            'detail_endpoint' => $detailEndpoint,
            'thread_endpoint' => $detailEndpoint === null ? null : $detailEndpoint . '/thread',
            'comment_endpoint' => $isCommentable && $detailEndpoint !== null ? $detailEndpoint . '/comments' : null,
            'latest_comment' => $this->latestOperatorComment($context),
            'comments' => $comments,
            'detected_at' => isset($case->detected_at) ? $this->isoTime($case->detected_at) : null,
            'resolved_at' => isset($case->resolved_at) ? $this->isoTime($case->resolved_at) : null,
            'created_at' => isset($case->created_at) ? $this->isoTime($case->created_at) : null,
            'updated_at' => isset($case->updated_at) ? $this->isoTime($case->updated_at) : null,
        ];
    }

    private function assertTablesReady()
    {
        foreach (['b2b_wallet_reconciliation_items', 'b2b_operator_audit_events'] as $table) {
            if (!Schema::hasTable($table)) {
                throw new RuntimeException('B2B support case tables are missing. Run: php artisan migrate');
            }
        }
    }

    private function assertOperator($operator)
    {
        if (!$operator || !isset($operator->id) || !isset($operator->operator_uid)) {
            throw new InvalidArgumentException('B2B operator context is missing.');
        }
    }

    private function requiredTransactionUid($transactionUid)
    {
        $transactionUid = trim((string) $transactionUid);
        if ($transactionUid === '') {
            throw new InvalidArgumentException('Support case transaction UID is required.');
        }

        return $transactionUid;
    }

    private function ownedCase($operatorId, $transactionUid, $openOnly)
    {
        $query = DB::table('b2b_wallet_reconciliation_items')
            ->where('operator_id', (int) $operatorId)
            ->where('transaction_uid', $transactionUid);

        if ($openOnly) {
            $query->whereIn('state', ['open', 'in_progress']);
        }

        $case = $query->orderBy('id', 'desc')->first();
        if (!$case) {
            throw new InvalidArgumentException('Support case was not found for this operator.');
        }

        return $case;
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

    private function operatorComments(array $context, $limit)
    {
        $comments = $this->rawOperatorComments($context);
        if (!$comments) {
            return [];
        }

        $limit = max(1, min((int) $limit, 100));

        return array_values(array_map(function ($comment) {
            return $this->operatorCommentPayload($comment);
        }, array_slice($comments, 0, $limit)));
    }

    private function latestOperatorComment(array $context)
    {
        $comments = $this->rawOperatorComments($context);
        if (!$comments) {
            return null;
        }

        return $this->operatorCommentPayload($comments[count($comments) - 1]);
    }

    private function operatorCommentCount(array $context)
    {
        return count($this->rawOperatorComments($context));
    }

    private function rawOperatorComments(array $context)
    {
        if (!isset($context['operator_comments']) || !is_array($context['operator_comments'])) {
            return [];
        }

        return array_values(array_filter($context['operator_comments'], function ($comment) {
            return is_array($comment);
        }));
    }

    private function operatorCommentPayload(array $comment)
    {
        return [
            'actor' => isset($comment['actor']) ? $this->safeNullableText($comment['actor'], 100) : null,
            'source' => isset($comment['source']) ? $this->safeNullableText($comment['source'], 40) : null,
            'message' => isset($comment['message']) ? $this->safeText($comment['message'], 1000) : '',
            'external_reference' => isset($comment['external_reference']) ? $this->safeNullableText($comment['external_reference'], 120) : null,
            'created_at' => isset($comment['at']) ? $this->isoTime($comment['at']) : null,
        ];
    }

    private function supportCaseDetailEndpoint($transactionUid)
    {
        $transactionUid = trim((string) $transactionUid);

        return $transactionUid === ''
            ? null
            : '/api/b2b/v1/portal/support/cases/' . rawurlencode($transactionUid);
    }

    private function safeNullableText($value, $limit)
    {
        $value = $this->safeText($value, $limit);

        return $value === '' ? null : $value;
    }

    private function safeText($value, $limit = 1000)
    {
        if ($value === null) {
            return '';
        }

        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        return substr($this->redactor->storageValue($value), 0, $limit);
    }

    private function isoTime($value)
    {
        if (!$value) {
            return null;
        }

        try {
            return $value instanceof Carbon
                ? $value->toIso8601String()
                : Carbon::parse($value)->toIso8601String();
        } catch (\Exception $e) {
            return null;
        }
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
