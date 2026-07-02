<?php

namespace VanguardLTE\B2B\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class B2BReadinessService
{
    private $releaseGate;

    public function __construct(B2BReleaseGate $releaseGate)
    {
        $this->releaseGate = $releaseGate;
    }

    public function check($production = null)
    {
        $production = $production === null
            ? (app()->environment('production') || config('app.env') === 'production')
            : (bool) $production;

        $checks = [
            $this->databaseCheck(),
            $this->tablesCheck(),
            $this->columnsCheck(),
            $this->cacheRuntimeCheck($production),
            $this->queueConfigCheck($production),
            $this->schedulerHeartbeatCheck($production),
            $this->storageCheck(),
        ];

        if ($production) {
            $checks[] = $this->releaseGateCheck();
        }

        $ok = true;
        foreach ($checks as $check) {
            if ($check['status'] === 'fail') {
                $ok = false;
                break;
            }
        }

        return [
            'service' => 'bbb-b2b',
            'status' => $ok ? 'ready' : 'not_ready',
            'environment' => config('app.env'),
            'production_mode' => $production,
            'time' => now()->toIso8601String(),
            'checks' => $checks,
        ];
    }

    public function isReady(array $result)
    {
        return isset($result['status']) && $result['status'] === 'ready';
    }

    private function databaseCheck()
    {
        try {
            DB::connection()->select('select 1');

            return $this->checkResult('database', 'pass', 'Database connection is available.');
        } catch (\Exception $e) {
            return $this->checkResult('database', 'fail', 'Database connection failed.');
        }
    }

    private function tablesCheck()
    {
        $missing = [];
        foreach ($this->requiredTables() as $table) {
            if (!Schema::hasTable($table)) {
                $missing[] = $table;
            }
        }

        if (count($missing) > 0) {
            return $this->checkResult('b2b_tables', 'fail', 'Missing B2B tables: '.implode(', ', $missing).'.');
        }

        return $this->checkResult('b2b_tables', 'pass', 'Required B2B tables are present.');
    }

    private function columnsCheck()
    {
        $missing = [];
        foreach ($this->requiredColumns() as $table => $columns) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (!Schema::hasColumn($table, $column)) {
                    $missing[] = $table.'.'.$column;
                }
            }
        }

        if (count($missing) > 0) {
            return $this->checkResult('b2b_columns', 'fail', 'Missing B2B columns: '.implode(', ', $missing).'.');
        }

        return $this->checkResult('b2b_columns', 'pass', 'Critical B2B columns are present.');
    }

    private function cacheRuntimeCheck($production)
    {
        $stores = array_values(array_unique(array_filter([
            config('cache.default'),
            config('b2b.nonce_cache_store') ?: config('cache.default'),
            config('b2b.rate_limit_cache_store') ?: config('cache.default'),
            config('b2b.scheduler_heartbeat_cache_store') ?: config('cache.default'),
        ])));

        if (count($stores) === 0) {
            return $this->checkResult('cache_runtime', 'fail', 'No cache store is configured.');
        }

        $checked = [];
        foreach ($stores as $store) {
            $driver = config('cache.stores.'.$store.'.driver');
            if ($production && $driver !== 'redis') {
                return $this->checkResult('cache_runtime', 'fail', 'Production cache store must use Redis.');
            }

            try {
                $key = 'b2b:readiness:'.sha1($store.'|'.microtime(true));
                Cache::store($store)->put($key, '1', 5);
                $value = Cache::store($store)->get($key);
                Cache::store($store)->forget($key);

                if ($value !== '1') {
                    return $this->checkResult('cache_runtime', 'fail', 'Cache store read/write verification failed.');
                }
            } catch (\Exception $e) {
                return $this->checkResult('cache_runtime', 'fail', 'Cache store read/write verification failed.');
            }

            $checked[] = $store;
        }

        return $this->checkResult('cache_runtime', 'pass', 'Cache stores are writable: '.implode(', ', $checked).'.');
    }

    private function queueConfigCheck($production)
    {
        $connection = config('queue.default');
        $driver = config('queue.connections.'.$connection.'.driver');
        if (!$connection || !$driver) {
            return $this->checkResult('queue_config', 'fail', 'Queue connection is not configured.');
        }

        if ($production && $driver !== 'redis') {
            return $this->checkResult('queue_config', 'fail', 'Production queue driver must use Redis.');
        }

        $missingQueues = [];
        foreach (['wallet_live', 'wallet_retry', 'provider_callbacks', 'reporting', 'settlement', 'reconciliation', 'notifications', 'maintenance'] as $queueKey) {
            if (!config('b2b_queues.queues.'.$queueKey)) {
                $missingQueues[] = $queueKey;
            }
        }

        if (count($missingQueues) > 0) {
            return $this->checkResult('queue_config', 'fail', 'Missing B2B queue names: '.implode(', ', $missingQueues).'.');
        }

        return $this->checkResult('queue_config', 'pass', 'Queue configuration is present.');
    }

    private function schedulerHeartbeatCheck($production)
    {
        if (!config('b2b.scheduler_heartbeat_required', true)) {
            return $this->checkResult('scheduler_heartbeat', 'pass', 'Scheduler heartbeat freshness enforcement is disabled.');
        }

        if (!$production) {
            return $this->checkResult('scheduler_heartbeat', 'pass', 'Scheduler heartbeat freshness is enforced in production mode.');
        }

        try {
            $status = app(B2BSchedulerHeartbeat::class)->status();
        } catch (\Exception $e) {
            return $this->checkResult('scheduler_heartbeat', 'fail', 'Scheduler heartbeat cache could not be read.');
        }

        if (!$status['present']) {
            return $this->checkResult('scheduler_heartbeat', 'fail', 'B2B scheduler heartbeat is missing; confirm schedule:run is active on one node.');
        }

        if (!$status['fresh']) {
            return $this->checkResult('scheduler_heartbeat', 'fail', 'B2B scheduler heartbeat is stale: '.$status['age_seconds'].' seconds old, max '.$status['max_age_seconds'].'.');
        }

        return $this->checkResult('scheduler_heartbeat', 'pass', 'B2B scheduler heartbeat is fresh: '.$status['age_seconds'].' seconds old.');
    }

    private function storageCheck()
    {
        foreach ([storage_path('framework'), storage_path('logs')] as $path) {
            if (!is_dir($path) || !is_writable($path)) {
                return $this->checkResult('storage', 'fail', 'Laravel storage path is not writable.');
            }
        }

        return $this->checkResult('storage', 'pass', 'Laravel storage paths are writable.');
    }

    private function releaseGateCheck()
    {
        $result = $this->releaseGate->run(true, false);
        if ($result['ok']) {
            return $this->checkResult('release_gate_config', 'pass', 'Production release configuration gates pass.');
        }

        $failed = [];
        foreach ($result['checks'] as $check) {
            if ($check['status'] === 'fail') {
                $failed[] = $check['name'];
            }
        }

        return $this->checkResult('release_gate_config', 'fail', 'Production release configuration gates failed: '.implode(', ', $failed).'.');
    }

    private function requiredTables()
    {
        return [
            'b2b_operators',
            'b2b_operator_api_keys',
            'b2b_operator_audit_events',
            'b2b_operator_players',
            'b2b_operator_health_events',
            'b2b_game_catalog',
            'b2b_operator_game_assignments',
            'b2b_game_sessions',
            'b2b_provider_requests',
            'b2b_wallet_transactions',
            'b2b_wallet_callback_logs',
            'b2b_wallet_transaction_attempts',
            'b2b_wallet_transaction_transitions',
            'b2b_wallet_reconciliation_items',
            'b2b_wallet_manual_actions',
            'b2b_settlements',
            'b2b_operator_support_tickets',
            'b2b_operator_support_ticket_messages',
        ];
    }

    private function requiredColumns()
    {
        return [
            'b2b_wallet_transactions' => [
                'transaction_uid',
                'transaction_id',
                'idempotency_key',
                'request_hash',
                'operator_response_body',
            ],
            'b2b_game_sessions' => [
                'session_uid',
                'token_hash',
                'heartbeat_at',
                'stale_at',
                'close_reason',
            ],
            'b2b_wallet_reconciliation_items' => [
                'wallet_transaction_id',
                'operator_id',
                'state',
                'detected_at',
            ],
            'b2b_settlements' => [
                'settlement_uid',
                'period_start',
                'period_end',
                'status',
            ],
        ];
    }

    private function checkResult($name, $status, $message)
    {
        return [
            'name' => $name,
            'status' => $status,
            'message' => $message,
        ];
    }
}
