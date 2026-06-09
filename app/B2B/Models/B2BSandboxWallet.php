<?php

namespace VanguardLTE\B2B\Models;

use Illuminate\Database\Eloquent\Model;

class B2BSandboxWallet extends Model
{
    const STATUS_ACTIVE = 'active';
    const STATUS_BLOCKED = 'blocked';

    protected $table = 'b2b_sandbox_wallets';

    protected $fillable = [
        'operator_id',
        'external_player_id',
        'currency',
        'balance',
        'status',
        'last_transaction_at',
        'metadata',
    ];

    protected $casts = [
        'balance' => 'decimal:8',
        'metadata' => 'array',
    ];

    protected $dates = [
        'last_transaction_at',
    ];
}
