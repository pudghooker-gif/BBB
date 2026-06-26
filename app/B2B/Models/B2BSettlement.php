<?php

namespace VanguardLTE\B2B\Models;

use Illuminate\Database\Eloquent\Model;

class B2BSettlement extends Model
{
    const STATUS_DRAFT = 'draft';
    const STATUS_EXPORTED = 'exported';
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    protected $table = 'b2b_settlements';

    protected $fillable = [
        'settlement_uid',
        'operator_id',
        'period_start',
        'period_end',
        'currency',
        'bets_amount',
        'wins_amount',
        'refunds_amount',
        'ggr_amount',
        'aggregator_fee_amount',
        'provider_fee_amount',
        'net_amount',
        'status',
        'export_format',
        'export_hash',
        'exported_at',
        'submitted_at',
        'submitted_by',
        'approved_at',
        'approved_by',
        'rejected_at',
        'rejected_by',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    protected $dates = [
        'period_start',
        'period_end',
        'exported_at',
        'submitted_at',
        'approved_at',
        'rejected_at',
    ];
}
