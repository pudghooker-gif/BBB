<?php

namespace VanguardLTE\B2B\Models;

use Illuminate\Database\Eloquent\Model;

class B2BWalletTransactionTransition extends Model
{
    protected $table = 'b2b_wallet_transaction_transitions';

    public $timestamps = false;

    protected $fillable = [
        'wallet_transaction_id',
        'operator_id',
        'transaction_uid',
        'from_status',
        'to_status',
        'reason',
        'actor',
        'context',
        'created_at',
    ];

    protected $casts = [
        'context' => 'array',
    ];
}
