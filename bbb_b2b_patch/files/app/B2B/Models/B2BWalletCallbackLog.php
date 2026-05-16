<?php

namespace VanguardLTE\B2B\Models;

use Illuminate\Database\Eloquent\Model;

class B2BWalletCallbackLog extends Model
{
    protected $table = 'b2b_wallet_callback_logs';

    protected $fillable = [
        'operator_id',
        'wallet_transaction_id',
        'direction',
        'endpoint',
        'http_status',
        'request_headers',
        'request_body',
        'response_headers',
        'response_body',
        'duration_ms',
        'error_message',
    ];

    protected $casts = [
        'request_headers' => 'array',
        'request_body' => 'array',
        'response_headers' => 'array',
        'response_body' => 'array',
    ];
}
