<?php

namespace VanguardLTE\B2B\Models;

use Illuminate\Database\Eloquent\Model;

class B2BSettlement extends Model
{
    protected $table = 'b2b_settlements';

    protected $fillable = [
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
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    protected $dates = [
        'period_start',
        'period_end',
    ];
}
