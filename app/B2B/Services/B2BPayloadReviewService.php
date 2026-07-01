<?php

namespace VanguardLTE\B2B\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;

class B2BPayloadReviewService
{
    private $redactor;
    private $audit;

    public function __construct(B2BPayloadRedactor $redactor, B2BOperatorAuditLogger $audit)
    {
        $this->redactor = $redactor;
        $this->audit = $audit;
    }

    public function recentAttempts($limit = 50)
    {
        if (!Schema::hasTable('b2b_wallet_transaction_attempts')) {
            return collect();
        }

        return DB::table('b2b_wallet_transaction_attempts')
            ->select('id', 'operator_id', 'transaction_uid', 'type', 'attempt_no', 'http_status', 'result', 'duration_ms', 'request_body', 'response_body', 'error', 'created_at')
            ->orderBy('id', 'desc')
            ->limit((int) $limit)
            ->get()
            ->map(function ($attempt) {
                $attempt->request_body_display = $this->formatPayload($attempt->request_body, true);
                $attempt->response_body_display = $this->formatPayload($attempt->response_body, true);
                unset($attempt->request_body, $attempt->response_body);

                return $attempt;
            });
    }

    public function rawAttempt($attemptId, $actor, $reason, array $context)
    {
        if (!Schema::hasTable('b2b_wallet_transaction_attempts')) {
            throw new RuntimeException('B2B wallet transaction attempts table is missing.');
        }

        $attempt = DB::table('b2b_wallet_transaction_attempts')
            ->where('id', (int) $attemptId)
            ->first();

        if (!$attempt) {
            throw new InvalidArgumentException('B2B wallet attempt was not found.');
        }

        $actor = trim((string) $actor);
        $reason = trim((string) $reason);
        if ($actor === '' || $reason === '') {
            throw new InvalidArgumentException('Raw payload review requires actor and reason.');
        }

        $this->audit->record(
            isset($attempt->operator_id) ? $attempt->operator_id : null,
            'payload.raw_viewed',
            'wallet_attempt',
            $attempt->id,
            $actor,
            $reason,
            [
                'wallet_transaction_id' => isset($attempt->wallet_transaction_id) ? $attempt->wallet_transaction_id : null,
                'transaction_uid' => isset($attempt->transaction_uid) ? $attempt->transaction_uid : null,
                'type' => isset($attempt->type) ? $attempt->type : null,
                'permission' => isset($context['permission']) ? $context['permission'] : null,
                'step_up' => !empty($context['step_up']),
                'source' => isset($context['source']) ? $context['source'] : null,
                'ip_address' => isset($context['ip_address']) ? $context['ip_address'] : null,
                'user_agent' => isset($context['user_agent']) ? $context['user_agent'] : null,
            ],
            isset($context['ip_address']) ? $context['ip_address'] : null,
            isset($context['user_agent']) ? $context['user_agent'] : null
        );

        $attempt->request_body_display = $this->formatPayload(isset($attempt->request_body) ? $attempt->request_body : null, false);
        $attempt->response_body_display = $this->formatPayload(isset($attempt->response_body) ? $attempt->response_body : null, false);
        unset($attempt->request_body, $attempt->response_body);

        return $attempt;
    }

    private function formatPayload($payload, $redacted)
    {
        if ($payload === null || $payload === '') {
            return '';
        }

        $value = $redacted ? $this->redactor->storageValue($payload) : $payload;
        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return json_last_error() === JSON_ERROR_NONE && is_array($decoded)
                ? $this->prettyJson($decoded)
                : $value;
        }

        if (is_array($value)) {
            return $this->prettyJson($value);
        }

        if (is_object($value)) {
            return $this->prettyJson((array) $value);
        }

        return (string) $value;
    }

    private function prettyJson(array $value)
    {
        $encoded = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return $encoded === false ? '' : $encoded;
    }
}
