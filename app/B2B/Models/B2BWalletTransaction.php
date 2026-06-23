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
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED = 'failed';
    const STATUS_TIMEOUT = 'timeout';
    const STATUS_UNKNOWN = 'unknown';
    const STATUS_ROLLBACK_REQUIRED = 'rollback_required';
    const STATUS_REVERSED = 'reversed';
    const STATUS_MANUAL_REVIEW = 'manual_review';
    const STATUS_DEAD_LETTER = 'dead_letter';

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
        'request_hash',
        'operator_response_code',
        'operator_response_body',
        'last_error',
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
            self::STATUS_SUCCESS,
            self::STATUS_REVERSED,
            self::STATUS_DEAD_LETTER,
        ], true);
    }

    public function transitions()
    {
        return $this->hasMany(B2BWalletTransactionTransition::class, 'wallet_transaction_id');
    }
}
