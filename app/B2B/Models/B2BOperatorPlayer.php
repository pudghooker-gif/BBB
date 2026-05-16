<?php

namespace VanguardLTE\B2B\Models;

use Illuminate\Database\Eloquent\Model;

class B2BOperatorPlayer extends Model
{
    const STATUS_ACTIVE = 'active';
    const STATUS_BLOCKED = 'blocked';

    protected $table = 'b2b_operator_players';

    protected $fillable = [
        'operator_id',
        'external_player_id',
        'shadow_user_id',
        'currency',
        'country',
        'language',
        'status',
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
