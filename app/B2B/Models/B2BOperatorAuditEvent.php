<?php

namespace VanguardLTE\B2B\Models;

use Illuminate\Database\Eloquent\Model;

class B2BOperatorAuditEvent extends Model
{
    protected $table = 'b2b_operator_audit_events';

    protected $fillable = [
        'operator_id',
        'event_type',
        'subject_type',
        'subject_id',
        'actor',
        'reason',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function operator()
    {
        return $this->belongsTo(B2BOperator::class, 'operator_id');
    }
}
