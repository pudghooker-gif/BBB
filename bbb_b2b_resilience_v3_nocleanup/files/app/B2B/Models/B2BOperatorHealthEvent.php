<?php

namespace VanguardLTE\B2B\Models;

use Illuminate\Database\Eloquent\Model;

class B2BOperatorHealthEvent extends Model
{
    const UPDATED_AT = null;

    protected $table = 'b2b_operator_health_events';

    protected $fillable = [
        'operator_id',
        'event_type',
        'status',
        'failure_count',
        'message',
        'context',
    ];

    protected $casts = [
        'context' => 'array',
        'failure_count' => 'integer',
    ];

    public function operator()
    {
        return $this->belongsTo(B2BOperator::class, 'operator_id');
    }
}
