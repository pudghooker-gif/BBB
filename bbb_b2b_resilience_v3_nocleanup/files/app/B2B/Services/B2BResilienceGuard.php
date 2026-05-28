<?php

namespace VanguardLTE\B2B\Services;

use Illuminate\Support\Facades\Cache;
use VanguardLTE\B2B\Models\B2BOperator;
use VanguardLTE\B2B\Models\B2BOperatorHealthEvent;

class B2BResilienceGuard
{
    public function checkOperatorAvailable(B2BOperator $operator)
    {
        if ($operator->isBlockedForTraffic()) {
            return [
                'ok' => false,
                'http_status' => 403,
                'code' => 'OPERATOR_DISABLED',
                'message' => 'Operator is disabled or suspended.',
            ];
        }

        if ($operator->isCircuitOpen()) {
            return [
                'ok' => false,
                'http_status' => 503,
                'code' => 'OPERATOR_CIRCUIT_OPEN',
                'message' => 'Operator is temporarily unavailable because circuit breaker is open.',
                'retry_after' => max(1, now()->diffInSeconds($operator->circuit_open_until, false)),
            ];
        }

        return ['ok' => true];
    }

    public function checkRateLimit(B2BOperator $operator, $bucket)
    {
        $maxRps = (int) ($operator->max_rps ?: 50);

        if ($maxRps <= 0) {
            return ['ok' => true];
        }

        $key = 'b2b:rate:' . $bucket . ':operator:' . $operator->id . ':' . time();

        try {
            Cache::add($key, 0, 2);
            $current = Cache::increment($key);
        } catch (\Exception $e) {
            return ['ok' => true];
        }

        if ($current > $maxRps) {
            return [
                'ok' => false,
                'http_status' => 429,
                'code' => 'RATE_LIMITED',
                'message' => 'Operator request rate limit exceeded.',
                'limit' => $maxRps,
                'current' => $current,
            ];
        }

        return ['ok' => true, 'limit' => $maxRps, 'current' => $current];
    }

    public function recordSuccess(B2BOperator $operator, $eventType = 'callback_success', array $context = [])
    {
        $operator->forceFill([
            'failure_count' => 0,
            'last_success_at' => now(),
            'circuit_open_until' => null,
            'status' => $operator->status === B2BOperator::STATUS_DEGRADED
                ? B2BOperator::STATUS_ACTIVE
                : $operator->status,
        ])->save();

        $this->healthEvent($operator, $eventType, 'success', 'Operator request succeeded.', $context);
    }

    public function recordFailure(B2BOperator $operator, $eventType, $message, array $context = [])
    {
        $failureCount = (int) $operator->failure_count + 1;
        $threshold = (int) ($operator->circuit_breaker_threshold ?: 5);
        $cooldown = (int) ($operator->circuit_breaker_cooldown_seconds ?: 30);

        $fields = [
            'failure_count' => $failureCount,
            'last_failure_at' => now(),
        ];

        if ($failureCount >= $threshold) {
            $fields['status'] = B2BOperator::STATUS_DEGRADED;
            $fields['circuit_open_until'] = now()->addSeconds($cooldown);
        }

        $operator->forceFill($fields)->save();

        $this->healthEvent($operator, $eventType, 'failure', $message, array_merge($context, [
            'failure_count' => $failureCount,
            'threshold' => $threshold,
            'cooldown_seconds' => $cooldown,
        ]));
    }

    public function walletTimeoutSeconds(B2BOperator $operator)
    {
        $timeoutMs = (int) ($operator->wallet_timeout_ms ?: 5000);
        return max(1, (int) ceil($timeoutMs / 1000));
    }

    public function connectTimeoutSeconds(B2BOperator $operator)
    {
        $timeoutMs = (int) ($operator->connect_timeout_ms ?: 1500);
        return max(1, (int) ceil($timeoutMs / 1000));
    }

    private function healthEvent(B2BOperator $operator, $eventType, $status, $message, array $context = [])
    {
        try {
            B2BOperatorHealthEvent::create([
                'operator_id' => $operator->id,
                'event_type' => $eventType,
                'status' => $status,
                'failure_count' => (int) $operator->failure_count,
                'message' => $message,
                'context' => $context,
            ]);
        } catch (\Exception $e) {
            // Health events must never break live wallet or launch traffic.
        }
    }
}
