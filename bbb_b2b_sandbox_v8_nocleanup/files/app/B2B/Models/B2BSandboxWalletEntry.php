<?php

namespace VanguardLTE\B2B\Models;

use Illuminate\Database\Eloquent\Model;

class B2BSandboxWalletEntry extends Model
{
    const STATUS_SUCCESS = 'success';
    const STATUS_REJECTED = 'rejected';
    const STATUS_FAILED = 'failed';

    protected $table = 'b2b_sandbox_wallet_entries';

    protected $fillable = [
        'wallet_id',
        'operator_id',
        'action',
        'transaction_id',
        'idempotency_key',
        'amount',
        'currency',
        'balance_before',
        'balance_after',
        'status',
        'raw_payload',
        'response_payload',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'balance_before' => 'decimal:8',
        'balance_after' => 'decimal:8',
        'raw_payload' => 'array',
        'response_payload' => 'array',
    ];
}
