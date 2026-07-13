<?php

namespace VanguardLTE\B2B\Models;

use Illuminate\Database\Eloquent\Model;

class B2BGameSession extends Model
{
    const STATUS_ACTIVE = 'active';
    const STATUS_STALE = 'stale';
    const STATUS_EXPIRED = 'expired';
    const STATUS_CLOSED = 'closed';
    const STATUS_FAILED = 'failed';

    protected $table = 'b2b_game_sessions';

    protected $fillable = [
        'operator_id',
        'operator_player_id',
        'shadow_user_id',
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
        'legacy_launch_token',
        'legacy_launch_url',
        'status',
        'expires_at',
        'last_seen_at',
        'heartbeat_at',
        'stale_at',
        'launched_at',
        'closed_at',
        'close_reason',
        'heartbeat_timeout_seconds',
        'launch_attempts',
        'failure_code',
        'failure_message',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'heartbeat_timeout_seconds' => 'integer',
        'launch_attempts' => 'integer',
        'expires_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'heartbeat_at' => 'datetime',
        'stale_at' => 'datetime',
        'launched_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    protected $dates = [
        'expires_at',
        'last_seen_at',
        'heartbeat_at',
        'stale_at',
        'launched_at',
        'closed_at',
    ];

    public function operator()
    {
        return $this->belongsTo(B2BOperator::class, 'operator_id');
    }

    public function player()
    {
        return $this->belongsTo(B2BOperatorPlayer::class, 'operator_player_id');
    }
}
