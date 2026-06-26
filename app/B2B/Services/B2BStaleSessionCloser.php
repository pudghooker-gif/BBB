<?php

namespace VanguardLTE\B2B\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class B2BStaleSessionCloser
{
    public function close($minutes)
    {
        if (!Schema::hasTable('b2b_game_sessions')) {
            return [
                'closed' => 0,
                'message' => 'b2b_game_sessions table missing.',
            ];
        }

        $minutes = (int) $minutes;
        if ($minutes < 1) {
            $minutes = 30;
        }

        $cutoff = Carbon::now()->subMinutes($minutes);
        $updates = [
            'status' => 'stale',
            'stale_at' => Carbon::now(),
            'close_reason' => 'heartbeat_timeout',
            'updated_at' => Carbon::now(),
        ];

        foreach (['stale_at', 'close_reason'] as $column) {
            if (!Schema::hasColumn('b2b_game_sessions', $column)) {
                unset($updates[$column]);
            }
        }

        $count = DB::table('b2b_game_sessions')
            ->whereIn('status', ['active', 'launched'])
            ->where(function ($query) use ($cutoff) {
                $query->where(function ($q) use ($cutoff) {
                    if (Schema::hasColumn('b2b_game_sessions', 'last_seen_at')) {
                        $q->whereNotNull('last_seen_at')->where('last_seen_at', '<', $cutoff);
                    }
                });
                if (Schema::hasColumn('b2b_game_sessions', 'heartbeat_at')) {
                    $query->orWhere(function ($q) use ($cutoff) {
                        $q->whereNotNull('heartbeat_at')->where('heartbeat_at', '<', $cutoff);
                    });
                }
                $query->orWhere(function ($q) use ($cutoff) {
                    $q->whereNull('last_seen_at')->where('created_at', '<', $cutoff);
                });
            })
            ->update($updates);

        return [
            'closed' => $count,
        ];
    }
}
