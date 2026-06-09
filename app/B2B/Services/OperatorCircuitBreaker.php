<?php

namespace VanguardLTE\B2B\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class OperatorCircuitBreaker
{
    protected $failureThreshold = 5;
    protected $openSeconds = 60;

    public function isOpen($operator)
    {
        if (!$operator || !isset($operator->circuit_open_until) || !$operator->circuit_open_until) {
            return false;
        }

        return Carbon::parse($operator->circuit_open_until)->isFuture();
    }

    public function markSuccess($operator)
    {
        if (!$operator || !isset($operator->id)) {
            return;
        }

        DB::table('b2b_operators')->where('id', $operator->id)->update([
            'failure_count' => 0,
            'last_failure_at' => null,
            'circuit_open_until' => null,
            'updated_at' => Carbon::now(),
        ]);
    }

    public function markFailure($operator, $reason = null)
    {
        if (!$operator || !isset($operator->id)) {
            return;
        }

        $failureCount = isset($operator->failure_count) ? (int) $operator->failure_count + 1 : 1;
        $updates = [
            'failure_count' => $failureCount,
            'last_failure_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];

        if ($failureCount >= $this->failureThreshold) {
            $updates['circuit_open_until'] = Carbon::now()->addSeconds($this->openSeconds);
        }

        DB::table('b2b_operators')->where('id', $operator->id)->update($updates);
    }
}
