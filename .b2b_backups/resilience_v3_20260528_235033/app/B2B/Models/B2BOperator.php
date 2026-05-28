<?php

namespace VanguardLTE\B2B\Models;

use Illuminate\Database\Eloquent\Model;

class B2BOperator extends Model
{
    const STATUS_ACTIVE = 'active';
    const STATUS_DISABLED = 'disabled';
    const STATUS_SUSPENDED = 'suspended';

    protected $table = 'b2b_operators';

    protected $fillable = [
        'operator_uid',
        'name',
        'shop_id',
        'status',
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
    ];

    public function apiKeys()
    {
        return $this->hasMany(B2BOperatorApiKey::class, 'operator_id');
    }

    public function players()
    {
        return $this->hasMany(B2BOperatorPlayer::class, 'operator_id');
    }
}
