<?php

namespace VanguardLTE\B2B\Services;

use Illuminate\Support\Facades\Schema;
use VanguardLTE\B2B\Models\B2BOperator;
use VanguardLTE\B2B\Models\B2BOperatorAuditEvent;

class B2BOperatorAuditLogger
{
    private $redactor;

    public function __construct(B2BPayloadRedactor $redactor)
    {
        $this->redactor = $redactor;
    }

    public function record($operator, $eventType, $subjectType, $subjectId, $actor, $reason = null, array $metadata = [])
    {
        if (!Schema::hasTable('b2b_operator_audit_events')) {
            return null;
        }

        $operatorId = $operator instanceof B2BOperator ? $operator->id : $operator;
        $actor = trim((string) $actor);

        return B2BOperatorAuditEvent::create([
            'operator_id' => $operatorId ?: null,
            'event_type' => (string) $eventType,
            'subject_type' => $subjectType ?: null,
            'subject_id' => $subjectId !== null ? (string) $subjectId : null,
            'actor' => $actor !== '' ? $actor : 'system',
            'reason' => $reason !== null ? (string) $reason : null,
            'metadata' => $this->redactor->redact($metadata),
        ]);
    }
}
