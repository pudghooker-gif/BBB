<?php

namespace VanguardLTE\B2B\Models;

use Illuminate\Database\Eloquent\Model;

class B2BOperatorGameAssignment extends Model
{
    const STATUS_ALLOWED = 'allowed';
    const STATUS_BLOCKED = 'blocked';
    const STATUS_DISABLED = 'disabled';

    protected $table = 'b2b_operator_game_assignments';

    protected $fillable = [
        'operator_id',
        'game_uid',
        'provider',
        'status',
        'demo_enabled',
        'real_enabled',
        'allowed_currencies',
        'allowed_countries',
        'metadata',
    ];

    protected $casts = [
        'demo_enabled' => 'boolean',
        'real_enabled' => 'boolean',
        'allowed_currencies' => 'array',
        'allowed_countries' => 'array',
        'metadata' => 'array',
    ];

    public function operator()
    {
        return $this->belongsTo(B2BOperator::class, 'operator_id');
    }

    public function isAllowed()
    {
        return $this->status === self::STATUS_ALLOWED;
    }

    public function isBlocked()
    {
        return $this->status === self::STATUS_BLOCKED;
    }
}
