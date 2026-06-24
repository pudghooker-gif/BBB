<?php

namespace VanguardLTE\B2B\Models;

use Illuminate\Database\Eloquent\Model;

class B2BOperator extends Model
{
    const STATUS_ACTIVE = 'active';
    const STATUS_DEGRADED = 'degraded';
    const STATUS_DISABLED = 'disabled';
    const STATUS_SUSPENDED = 'suspended';

    protected $table = 'b2b_operators';

    protected $fillable = [
        'operator_uid',
        'name',
        'shop_id',
        'status',
        'failure_count',
        'last_failure_at',
        'last_success_at',
        'circuit_open_until',
        'circuit_breaker_threshold',
        'circuit_breaker_cooldown_seconds',
        'max_rps',
        'wallet_timeout_ms',
        'connect_timeout_ms',
        'base_url',
        'wallet_callback_url',
        'callback_secret_encrypted',
        'default_currency',
        'allowed_currencies',
        'allowed_countries',
        'ip_whitelist',
        'settings',
    ];

    protected $casts = [
        'allowed_currencies' => 'array',
        'allowed_countries' => 'array',
        'ip_whitelist' => 'array',
        'settings' => 'array',
        'failure_count' => 'integer',
        'circuit_breaker_threshold' => 'integer',
        'circuit_breaker_cooldown_seconds' => 'integer',
        'max_rps' => 'integer',
        'wallet_timeout_ms' => 'integer',
        'connect_timeout_ms' => 'integer',
    ];

    protected $dates = [
        'last_failure_at',
        'last_success_at',
        'circuit_open_until',
    ];

    public function apiKeys()
    {
        return $this->hasMany(B2BOperatorApiKey::class, 'operator_id');
    }

    public function players()
    {
        return $this->hasMany(B2BOperatorPlayer::class, 'operator_id');
    }

    public function gameAssignments()
    {
        return $this->hasMany(B2BOperatorGameAssignment::class, 'operator_id');
    }

    public function isBlockedForTraffic()
    {
        return in_array($this->status, [self::STATUS_DISABLED, self::STATUS_SUSPENDED], true);
    }

    public function isCircuitOpen()
    {
        return $this->circuit_open_until && $this->circuit_open_until->isFuture();
    }
}
