<?php

namespace VanguardLTE\B2B\Models;

use Illuminate\Database\Eloquent\Model;

class B2BProviderRequest extends Model
{
    protected $table = 'b2b_provider_requests';

    protected $fillable = [
        'operator_id',
        'provider',
        'game_uid',
        'session_id',
        'request_uid',
        'action',
        'status',
        'request_payload',
        'response_payload',
        'error_message',
        'duration_ms',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
    ];
}
