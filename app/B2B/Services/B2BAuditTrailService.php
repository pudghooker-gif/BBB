<?php

namespace VanguardLTE\B2B\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class B2BAuditTrailService
{
    private $redactor;

    public function __construct(B2BPayloadRedactor $redactor)
    {
        $this->redactor = $redactor;
    }

    public function events(array $filters = [], $limit = 100, $maxLimit = 200)
    {
        if (!Schema::hasTable('b2b_operator_audit_events')) {
            return collect();
        }

        $maxLimit = max(1, (int) $maxLimit);
        $limit = max(1, min((int) $limit, $maxLimit));
        $select = [
            'ae.id',
            'ae.operator_id',
            'ae.event_type',
            'ae.subject_type',
            'ae.subject_id',
            'ae.actor',
            'ae.reason',
            'ae.ip_address',
            'ae.user_agent',
            'ae.metadata',
            'ae.created_at',
        ];

        $query = DB::table('b2b_operator_audit_events as ae');

        if (Schema::hasTable('b2b_operators')) {
            $query->leftJoin('b2b_operators as op', 'op.id', '=', 'ae.operator_id');
            $select[] = 'op.operator_uid';
            $select[] = 'op.name as operator_name';
        } else {
            $select[] = DB::raw('NULL as operator_uid');
            $select[] = DB::raw('NULL as operator_name');
        }

        $query->select($select);
        $this->applyFilters($query, $filters);

        return $query
            ->orderBy('ae.id', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($event) {
                $event->reason_display = $this->safeText(isset($event->reason) ? $event->reason : null);
                $event->metadata_display = $this->formatMetadata(isset($event->metadata) ? $event->metadata : null);

                return $event;
            });
    }

    private function applyFilters($query, array $filters)
    {
        foreach (['event_type', 'subject_type', 'subject_id', 'actor'] as $field) {
            $value = trim((string) (isset($filters[$field]) ? $filters[$field] : ''));
            if ($value !== '') {
                $query->where('ae.' . $field, $value);
            }
        }

        $operatorUid = trim((string) (isset($filters['operator_uid']) ? $filters['operator_uid'] : ''));
        if ($operatorUid !== '' && Schema::hasTable('b2b_operators')) {
            $query->where('op.operator_uid', $operatorUid);
        }

        $operatorId = trim((string) (isset($filters['operator_id']) ? $filters['operator_id'] : ''));
        if ($operatorId !== '' && ctype_digit($operatorId)) {
            $query->where('ae.operator_id', (int) $operatorId);
        }

        $from = $this->parseDate(isset($filters['from']) ? $filters['from'] : null, true);
        if ($from) {
            $query->where('ae.created_at', '>=', $from);
        }

        $to = $this->parseDate(isset($filters['to']) ? $filters['to'] : null, false);
        if ($to) {
            $query->where('ae.created_at', '<=', $to);
        }
    }

    private function parseDate($value, $startOfDay)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        try {
            $date = Carbon::parse($value);

            return $startOfDay ? $date->startOfDay() : $date->endOfDay();
        } catch (\Exception $e) {
            return null;
        }
    }

    private function formatMetadata($metadata)
    {
        if ($metadata === null || $metadata === '') {
            return '';
        }

        $redacted = $this->redactor->storageValue($metadata);
        $decoded = json_decode((string) $redacted, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $encoded = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            return $encoded === false ? '' : $encoded;
        }

        return (string) $redacted;
    }

    private function safeText($value)
    {
        if ($value === null || $value === '') {
            return '';
        }

        return $this->redactor->storageValue((string) $value);
    }
}
