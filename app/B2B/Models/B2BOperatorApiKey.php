<?php

namespace VanguardLTE\B2B\Models;

use Illuminate\Database\Eloquent\Model;

class B2BOperatorApiKey extends Model
{
    const STATUS_ACTIVE = 'active';
    const STATUS_DISABLED = 'disabled';

    protected $table = 'b2b_operator_api_keys';

    protected $fillable = [
        'operator_id',
        'key_id',
        'secret_encrypted',
        'status',
        'max_rps',
        'last_used_at',
        'expires_at',
    ];

    protected $hidden = [
        'secret_encrypted',
    ];

    protected $dates = [
        'last_used_at',
        'expires_at',
    ];

    protected $casts = [
        'max_rps' => 'integer',
    ];

    public function operator()
    {
        return $this->belongsTo(B2BOperator::class, 'operator_id');
    }
}
