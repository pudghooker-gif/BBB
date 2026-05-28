<?php

namespace VanguardLTE\B2B\Models;

use Illuminate\Database\Eloquent\Model;

class B2BWalletTransaction extends Model
{
    const TYPE_BALANCE = 'balance';
    const TYPE_BET = 'bet';
    const TYPE_WIN = 'win';
    const TYPE_REFUND = 'refund';
    const TYPE_ROLLBACK = 'rollback';

    const STATUS_PENDING = 'pending';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_REJECTED = 'rejected';
    const STATUS_FAILED = 'failed';
    const STATUS_TIMEOUT = 'timeout';
    const STATUS_ROLLBACK_REQUIRED = 'rollback_required';

    protected $table = 'b2b_wallet_transactions';

    protected $fillable = [
        'operator_id',
        'operator_player_id',
        'session_id',
        'game_uid',
        'provider',
        'round_id',
        'transaction_uid',
        'idempotency_key',
        'type',
        'amount',
        'currency',
        'status',
        'balance_before',
        'balance_after',
        'raw_request',
        'raw_response',
        'error_code',
        'error_message',
        'attempts',
        'last_attempt_at',
        'locked_until',
        'processed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'balance_before' => 'decimal:8',
        'balance_after' => 'decimal:8',
        'raw_request' => 'array',
        'raw_response' => 'array',
        'attempts' => 'integer',
    ];

    protected $dates = [
        'last_attempt_at',
        'locked_until',
        'processed_at',
    ];

    public function isFinal()
    {
        return in_array($this->status, [
            self::STATUS_ACCEPTED,
            self::STATUS_REJECTED,
            self::STATUS_FAILED,
            self::STATUS_TIMEOUT,
        ], true);
    }
}
