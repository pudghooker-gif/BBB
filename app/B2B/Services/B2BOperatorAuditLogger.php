<?php

namespace VanguardLTE\B2B\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use VanguardLTE\B2B\Models\B2BOperator;
use VanguardLTE\B2B\Models\B2BOperatorApiKey;
use VanguardLTE\B2B\Models\B2BOperatorAuditEvent;

class B2BOperatorAuditLogger
{
    private $redactor;

    private $structuredLogger;

    public function __construct(B2BPayloadRedactor $redactor, B2BStructuredEventLogger $structuredLogger)
    {
        $this->redactor = $redactor;
        $this->structuredLogger = $structuredLogger;
    }

    public function record($operator, $eventType, $subjectType, $subjectId, $actor, $reason = null, array $metadata = [], $ipAddress = null, $userAgent = null)
    {
        if (!Schema::hasTable('b2b_operator_audit_events')) {
            return null;
        }

        $operatorId = $operator instanceof B2BOperator ? $operator->id : $operator;
        $actor = trim((string) $actor);

        $metadata = $this->redactor->redact($metadata);

        $event = B2BOperatorAuditEvent::create([
            'operator_id' => $operatorId ?: null,
            'event_type' => (string) $eventType,
            'subject_type' => $subjectType ?: null,
            'subject_id' => $subjectId !== null ? (string) $subjectId : null,
            'actor' => $actor !== '' ? $actor : 'system',
            'reason' => $reason !== null ? (string) $reason : null,
            'ip_address' => $ipAddress !== null ? (string) $ipAddress : null,
            'user_agent' => $userAgent !== null ? substr((string) $userAgent, 0, 500) : null,
            'metadata' => $metadata,
        ]);

        $this->structuredLogger->info('audit.event', [
            'operator_id' => $operatorId ?: null,
            'event_type' => (string) $eventType,
            'subject_type' => $subjectType ?: null,
            'subject_id' => $subjectId !== null ? (string) $subjectId : null,
            'actor' => $actor !== '' ? $actor : 'system',
            'reason' => $reason !== null ? (string) $reason : null,
            'ip_address' => $ipAddress !== null ? (string) $ipAddress : null,
            'user_agent' => $userAgent !== null ? substr((string) $userAgent, 0, 500) : null,
            'metadata' => $metadata,
        ]);

        return $event;
    }

    public function recordApiKeyUsed(B2BOperator $operator, B2BOperatorApiKey $apiKey, Request $request)
    {
        if (!Schema::hasTable('b2b_operator_audit_events')) {
            return null;
        }

        $method = strtoupper($request->method());
        $path = '/' . ltrim($request->path(), '/');
        $ipAddress = $request->ip();

        if (!$this->shouldRecordUsage($operator, $apiKey, $method, $path, $ipAddress)) {
            return null;
        }

        return $this->record($operator, 'api_key.used', 'api_key', $apiKey->key_id, 'api:' . $operator->operator_uid, null, [
            'method' => $method,
            'path' => $path,
            'request_id' => $request->attributes->get('request_id') ?: $request->header('X-Request-Id'),
            'key_id' => $apiKey->key_id,
        ], $ipAddress, $request->userAgent());
    }

    private function shouldRecordUsage(B2BOperator $operator, B2BOperatorApiKey $apiKey, $method, $path, $ipAddress)
    {
        $seconds = (int) config('b2b.api_key_usage_audit_sample_seconds', 60);
        if ($seconds <= 0) {
            return true;
        }

        $slice = intdiv(time(), $seconds);
        $key = 'b2b:audit:api-key-used:' . sha1(implode('|', [
            $operator->id,
            $apiKey->id,
            $method,
            $path,
            $ipAddress,
            $slice,
        ]));

        try {
            return Cache::add($key, 1, $seconds + 5);
        } catch (\Exception $e) {
            return true;
        }
    }
}
