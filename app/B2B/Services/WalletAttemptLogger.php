<?php

namespace VanguardLTE\B2B\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WalletAttemptLogger
{
    protected $redactor;

    public function __construct(B2BPayloadRedactor $redactor)
    {
        $this->redactor = $redactor;
    }

    public function start($transaction, $operator, $type, $url, $timeoutMs, array $payload)
    {
        if (!Schema::hasTable('b2b_wallet_transaction_attempts')) {
            return null;
        }

        $attemptNo = 1;
        if ($transaction && isset($transaction->attempts)) {
            $attemptNo = (int) $transaction->attempts + 1;
        }

        return DB::table('b2b_wallet_transaction_attempts')->insertGetId([
            'wallet_transaction_id' => $transaction && isset($transaction->id) ? $transaction->id : null,
            'operator_id' => $operator && isset($operator->id) ? $operator->id : null,
            'transaction_uid' => $transaction && isset($transaction->transaction_uid) ? $transaction->transaction_uid : null,
            'type' => $type,
            'attempt_no' => $attemptNo,
            'url' => $url,
            'timeout_ms' => $timeoutMs,
            'result' => 'pending',
            'request_body' => $this->redactor->json($payload),
            'started_at' => Carbon::now(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    public function finish($attemptId, $result, $httpStatus = null, $responseBody = null, $error = null, $durationMs = null)
    {
        if (!$attemptId || !Schema::hasTable('b2b_wallet_transaction_attempts')) {
            return;
        }

        DB::table('b2b_wallet_transaction_attempts')->where('id', $attemptId)->update([
            'http_status' => $httpStatus,
            'result' => $result,
            'duration_ms' => $durationMs,
            'response_body' => $this->redactor->storageValue($responseBody),
            'error' => $error,
            'finished_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }
}
