<?php

namespace VanguardLTE\B2B\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WalletIdempotencyService
{
    public function key($operatorId, $type, $transactionId, $roundId = null)
    {
        return sha1(implode('|', [
            (string) $operatorId,
            (string) $type,
            (string) $transactionId,
            (string) $roundId,
        ]));
    }

    public function requestHash(array $payload)
    {
        ksort($payload);
        return hash('sha256', json_encode($payload));
    }

    public function findExisting($operatorId, $key)
    {
        if (!Schema::hasTable('b2b_wallet_transactions') || !Schema::hasColumn('b2b_wallet_transactions', 'idempotency_key')) {
            return null;
        }

        return DB::table('b2b_wallet_transactions')
            ->where('operator_id', $operatorId)
            ->where('idempotency_key', $key)
            ->first();
    }
}
