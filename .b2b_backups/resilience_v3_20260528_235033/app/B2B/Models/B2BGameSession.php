<?php

namespace VanguardLTE\B2B\Models;

use Illuminate\Database\Eloquent\Model;

class B2BGameSession extends Model
{
    const STATUS_ACTIVE = 'active';
    const STATUS_EXPIRED = 'expired';
    const STATUS_CLOSED = 'closed';

    protected $table = 'b2b_game_sessions';

    protected $fillable = [
        'operator_id',
        'operator_player_id',
        'session_uid',
        'token_hash',
        'game_uid',
        'provider',
        'mode',
        'currency',
        'language',
        'country',
        'return_url',
        'launch_url',
        'status',
        'expires_at',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    protected $dates = [
        'expires_at',
    ];
}
