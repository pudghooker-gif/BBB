<?php

namespace VanguardLTE\B2B\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;

class B2BMetricsExporter
{
    private $declared = [];

    public function render()
    {
        $this->declared = [];
        $lines = [];
        $errors = 0;

        $this->metric($lines, 'bbb_b2b_info', 'gauge', 'Static B2B service information.', 1, [
            'service' => 'bbb-b2b',
            'environment' => config('app.env') ?: 'unknown',
        ]);

        $this->collect($errors, function () use (&$lines, &$errors) {
            $this->operators($lines, $errors);
        });
        $this->collect($errors, function () use (&$lines, &$errors) {
            $this->sessions($lines, $errors);
        });
        $this->collect($errors, function () use (&$lines, &$errors) {
            $this->walletTransactions($lines, $errors);
        });
        $this->collect($errors, function () use (&$lines, &$errors) {
            $this->walletCallbacks($lines, $errors);
        });
        $this->collect($errors, function () use (&$lines, &$errors) {
            $this->providerRequests($lines, $errors);
        });
        $this->collect($errors, function () use (&$lines, &$errors) {
            $this->reconciliation($lines, $errors);
        });
        $this->collect($errors, function () use (&$lines, &$errors) {
            $this->settlements($lines, $errors);
        });
        $this->collect($errors, function () use (&$lines, &$errors) {
            $this->queueDepth($lines, $errors);
        });
        $this->collect($errors, function () use (&$lines, &$errors) {
            $this->schedulerHeartbeat($lines, $errors);
        });

        $this->metric($lines, 'bbb_b2b_metrics_collection_errors', 'gauge', 'Number of metric collectors that failed or were unavailable during this scrape.', $errors);

        return implode("\n", $lines) . "\n";
    }

    private function operators(array &$lines, &$errors)
    {
        if (!$this->hasTable('b2b_operators', $errors)) {
            return;
        }

        $this->metric($lines, 'bbb_b2b_operators_total', 'gauge', 'Total configured B2B operators.', DB::table('b2b_operators')->count());
        $this->groupedCount($lines, 'bbb_b2b_operators_status_total', 'gauge', 'B2B operators by status.', 'b2b_operators', ['status']);

        $open = DB::table('b2b_operators')
            ->whereNotNull('circuit_open_until')
            ->where('circuit_open_until', '>', now())
            ->count();

        $this->metric($lines, 'bbb_b2b_operator_circuit_open_total', 'gauge', 'B2B operators with an open circuit breaker.', $open);

        if ($this->hasTable('b2b_operator_health_events', $errors)) {
            $this->groupedCount($lines, 'bbb_b2b_operator_health_events_total', 'gauge', 'B2B operator health events by status.', 'b2b_operator_health_events', ['status']);
        }
    }

    private function sessions(array &$lines, &$errors)
    {
        if (!$this->hasTable('b2b_game_sessions', $errors)) {
            return;
        }

        $this->metric($lines, 'bbb_b2b_sessions_total', 'gauge', 'Total B2B game sessions.', DB::table('b2b_game_sessions')->count());
        $this->groupedCount($lines, 'bbb_b2b_sessions_status_total', 'gauge', 'B2B game sessions by status.', 'b2b_game_sessions', ['status']);
        $this->metric($lines, 'bbb_b2b_sessions_active_total', 'gauge', 'Currently active B2B game sessions.', DB::table('b2b_game_sessions')->where('status', 'active')->count());
    }

    private function walletTransactions(array &$lines, &$errors)
    {
        if (!$this->hasTable('b2b_wallet_transactions', $errors)) {
            return;
        }

        $this->metric($lines, 'bbb_b2b_wallet_transactions_all_total', 'gauge', 'Total B2B wallet transactions.', DB::table('b2b_wallet_transactions')->count());
        $this->groupedCount($lines, 'bbb_b2b_wallet_transactions_status_total', 'gauge', 'B2B wallet transactions by status.', 'b2b_wallet_transactions', ['status']);
        $this->groupedCount($lines, 'bbb_b2b_wallet_transactions_total', 'gauge', 'B2B wallet transactions by status and type.', 'b2b_wallet_transactions', ['status', 'type']);
    }

    private function walletCallbacks(array &$lines, &$errors)
    {
        if (!$this->hasTable('b2b_wallet_callback_logs', $errors)) {
            return;
        }

        $success = DB::table('b2b_wallet_callback_logs')
            ->whereBetween('http_status', [200, 299])
            ->count();
        $failed = DB::table('b2b_wallet_callback_logs')
            ->where(function ($query) {
                $query->whereNull('http_status')
                    ->orWhere('http_status', '<', 200)
                    ->orWhere('http_status', '>=', 300);
            })
            ->count();

        $this->metric($lines, 'bbb_b2b_wallet_callbacks_total', 'gauge', 'B2B wallet callback attempts by outcome.', $success, ['outcome' => 'success']);
        $this->metric($lines, 'bbb_b2b_wallet_callbacks_total', 'gauge', 'B2B wallet callback attempts by outcome.', $failed, ['outcome' => 'failed']);

        if (Schema::hasColumn('b2b_wallet_callback_logs', 'duration_ms')) {
            $avg = DB::table('b2b_wallet_callback_logs')->whereNotNull('duration_ms')->avg('duration_ms');
            $this->metric($lines, 'bbb_b2b_wallet_callback_duration_ms_average', 'gauge', 'Average B2B wallet callback latency in milliseconds.', $avg ?: 0);
        }
    }

    private function providerRequests(array &$lines, &$errors)
    {
        if (!$this->hasTable('b2b_provider_requests', $errors)) {
            return;
        }

        $this->groupedCount($lines, 'bbb_b2b_provider_requests_total', 'gauge', 'B2B provider requests by provider, action, and status.', 'b2b_provider_requests', ['provider', 'action', 'status']);

        if (Schema::hasColumn('b2b_provider_requests', 'duration_ms')) {
            $rows = DB::table('b2b_provider_requests')
                ->select('provider', 'action', DB::raw('AVG(duration_ms) as aggregate'))
                ->whereNotNull('duration_ms')
                ->groupBy('provider', 'action')
                ->get();

            foreach ($rows as $row) {
                $this->metric($lines, 'bbb_b2b_provider_request_duration_ms_average', 'gauge', 'Average B2B provider request latency in milliseconds.', $row->aggregate ?: 0, [
                    'provider' => $this->labelValue($row->provider),
                    'action' => $this->labelValue($row->action),
                ]);
            }
        }
    }

    private function reconciliation(array &$lines, &$errors)
    {
        if (!$this->hasTable('b2b_wallet_reconciliation_items', $errors)) {
            return;
        }

        $this->metric($lines, 'bbb_b2b_reconciliation_items_open_total', 'gauge', 'Open B2B wallet reconciliation items.', DB::table('b2b_wallet_reconciliation_items')->where('state', 'open')->count());
        $this->groupedCount($lines, 'bbb_b2b_reconciliation_items_total', 'gauge', 'B2B wallet reconciliation items by state and status.', 'b2b_wallet_reconciliation_items', ['state', 'status']);
    }

    private function settlements(array &$lines, &$errors)
    {
        if (!$this->hasTable('b2b_settlements', $errors)) {
            return;
        }

        $this->groupedCount($lines, 'bbb_b2b_settlements_total', 'gauge', 'B2B settlements by status.', 'b2b_settlements', ['status']);
    }

    private function queueDepth(array &$lines, &$errors)
    {
        $connection = config('queue.default');
        $driver = config('queue.connections.' . $connection . '.driver');
        $queues = (array) config('b2b_queues.queues', []);

        if (!$driver || count($queues) === 0) {
            $errors++;
            return;
        }

        foreach ($queues as $key => $queue) {
            if ($driver === 'database') {
                $this->databaseQueueDepth($lines, $queue, $key, $errors);
            } elseif ($driver === 'redis') {
                $this->redisQueueDepth($lines, $queue, $key, $errors);
            }
        }
    }

    private function databaseQueueDepth(array &$lines, $queue, $key, &$errors)
    {
        $table = config('queue.connections.database.table', 'jobs');
        if (!Schema::hasTable($table)) {
            $errors++;
            return;
        }

        $base = DB::table($table)->where('queue', $queue);
        $depth = (clone $base)->count();
        $oldestCreatedAt = (clone $base)->min('created_at');
        $oldestAge = $oldestCreatedAt ? max(0, time() - (int) $oldestCreatedAt) : 0;

        $this->metric($lines, 'bbb_b2b_queue_depth', 'gauge', 'B2B queue depth by logical queue.', $depth, ['queue' => $key]);
        $this->metric($lines, 'bbb_b2b_queue_oldest_job_age_seconds', 'gauge', 'Age in seconds of the oldest queued B2B job.', $oldestAge, ['queue' => $key]);
    }

    private function redisQueueDepth(array &$lines, $queue, $key, &$errors)
    {
        try {
            $redisConnection = config('queue.connections.redis.connection', 'default');
            $depth = Redis::connection($redisConnection)->llen('queues:' . $queue);
            $this->metric($lines, 'bbb_b2b_queue_depth', 'gauge', 'B2B queue depth by logical queue.', $depth, ['queue' => $key]);
        } catch (\Exception $e) {
            $errors++;
        }
    }

    private function schedulerHeartbeat(array &$lines, &$errors)
    {
        $status = app(B2BSchedulerHeartbeat::class)->status();
        $labels = ['cache_store' => $this->labelValue($status['cache_store'])];

        $this->metric(
            $lines,
            'bbb_b2b_scheduler_heartbeat_age_seconds',
            'gauge',
            'Age in seconds of the latest B2B scheduler heartbeat, or -1 when missing.',
            $status['age_seconds'] === null ? -1 : $status['age_seconds'],
            $labels
        );
        $this->metric(
            $lines,
            'bbb_b2b_scheduler_heartbeat_fresh',
            'gauge',
            'Whether the latest B2B scheduler heartbeat is within the configured freshness window.',
            $status['fresh'] ? 1 : 0,
            $labels
        );
        $this->metric(
            $lines,
            'bbb_b2b_scheduler_heartbeat_max_age_seconds',
            'gauge',
            'Configured freshness window for the B2B scheduler heartbeat.',
            $status['max_age_seconds'],
            $labels
        );
    }

    private function groupedCount(array &$lines, $name, $type, $help, $table, array $columns)
    {
        $select = $columns;
        $select[] = DB::raw('COUNT(*) as aggregate');
        $rows = DB::table($table)
            ->select($select)
            ->groupBy($columns)
            ->get();

        foreach ($rows as $row) {
            $labels = [];
            foreach ($columns as $column) {
                $labels[$column] = $this->labelValue(isset($row->{$column}) ? $row->{$column} : null);
            }

            $this->metric($lines, $name, $type, $help, $row->aggregate, $labels);
        }
    }

    private function hasTable($table, &$errors)
    {
        if (Schema::hasTable($table)) {
            return true;
        }

        $errors++;
        return false;
    }

    private function collect(&$errors, $callback)
    {
        try {
            $callback();
        } catch (\Exception $e) {
            $errors++;
        }
    }

    private function metric(array &$lines, $name, $type, $help, $value, array $labels = [])
    {
        if (!isset($this->declared[$name])) {
            $lines[] = '# HELP ' . $name . ' ' . $help;
            $lines[] = '# TYPE ' . $name . ' ' . $type;
            $this->declared[$name] = true;
        }

        $lines[] = $name . $this->labels($labels) . ' ' . $this->number($value);
    }

    private function labels(array $labels)
    {
        if (count($labels) === 0) {
            return '';
        }

        $pairs = [];
        foreach ($labels as $key => $value) {
            $pairs[] = $key . '="' . str_replace(["\\", "\n", '"'], ["\\\\", "\\n", "\\\""], (string) $value) . '"';
        }

        return '{' . implode(',', $pairs) . '}';
    }

    private function labelValue($value)
    {
        if ($value === null || $value === '') {
            return 'unknown';
        }

        return (string) $value;
    }

    private function number($value)
    {
        if ($value === null || $value === '') {
            return '0';
        }

        if (is_float($value)) {
            return rtrim(rtrim(sprintf('%.6F', $value), '0'), '.');
        }

        return (string) $value;
    }
}
