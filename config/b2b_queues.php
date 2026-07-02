<?php

$connection = env('B2B_QUEUE_CONNECTION', 'redis');

$queues = [
    'wallet_live' => env('B2B_QUEUE_WALLET_LIVE', 'b2b-wallet-live'),
    'wallet_retry' => env('B2B_QUEUE_WALLET_RETRY', 'b2b-wallet-retry'),
    'provider_callbacks' => env('B2B_QUEUE_PROVIDER_CALLBACKS', 'b2b-provider-callbacks'),
    'reporting' => env('B2B_QUEUE_REPORTING', 'b2b-reporting'),
    'settlement' => env('B2B_QUEUE_SETTLEMENT', 'b2b-settlement'),
    'reconciliation' => env('B2B_QUEUE_RECONCILIATION', 'b2b-reconciliation'),
    'notifications' => env('B2B_QUEUE_NOTIFICATIONS', 'b2b-notifications'),
    'maintenance' => env('B2B_QUEUE_MAINTENANCE', 'b2b-maintenance'),
];

return [
    'connection' => $connection,

    'queues' => $queues,

    'workers' => [
        'wallet_live' => [
            'connection' => $connection,
            'queue' => $queues['wallet_live'],
            'processes' => env('B2B_WORKERS_WALLET_LIVE', 4),
            'sleep' => 1,
            'tries' => 1,
            'timeout' => 10,
            'max_time' => 3600,
        ],
        'wallet_retry' => [
            'connection' => $connection,
            'queue' => $queues['wallet_retry'],
            'processes' => env('B2B_WORKERS_WALLET_RETRY', 2),
            'sleep' => 3,
            'tries' => 2,
            'timeout' => 30,
            'max_time' => 3600,
        ],
        'provider_callbacks' => [
            'connection' => $connection,
            'queue' => $queues['provider_callbacks'],
            'processes' => env('B2B_WORKERS_PROVIDER_CALLBACKS', 2),
            'sleep' => 1,
            'tries' => 2,
            'timeout' => 30,
            'max_time' => 3600,
        ],
        'reporting' => [
            'connection' => $connection,
            'queue' => $queues['reporting'],
            'processes' => env('B2B_WORKERS_REPORTING', 1),
            'sleep' => 5,
            'tries' => 1,
            'timeout' => 120,
            'max_time' => 3600,
        ],
        'settlement' => [
            'connection' => $connection,
            'queue' => $queues['settlement'],
            'processes' => env('B2B_WORKERS_SETTLEMENT', 1),
            'sleep' => 5,
            'tries' => 1,
            'timeout' => 180,
            'max_time' => 3600,
        ],
        'reconciliation' => [
            'connection' => $connection,
            'queue' => $queues['reconciliation'],
            'processes' => env('B2B_WORKERS_RECONCILIATION', 1),
            'sleep' => 5,
            'tries' => 1,
            'timeout' => 120,
            'max_time' => 3600,
        ],
        'notifications' => [
            'connection' => $connection,
            'queue' => $queues['notifications'],
            'processes' => env('B2B_WORKERS_NOTIFICATIONS', 1),
            'sleep' => 5,
            'tries' => 2,
            'timeout' => 60,
            'max_time' => 3600,
        ],
        'maintenance' => [
            'connection' => $connection,
            'queue' => $queues['maintenance'],
            'processes' => env('B2B_WORKERS_MAINTENANCE', 1),
            'sleep' => 10,
            'tries' => 1,
            'timeout' => 180,
            'max_time' => 3600,
        ],
    ],

    'scheduled_commands' => [
        'scheduler_heartbeat' => [
            'command' => 'b2b:scheduler-heartbeat --source=scheduler',
            'frequency' => 'everyMinute',
            'queue' => 'maintenance',
        ],
        'wallet_retry' => [
            'command' => 'b2b:retry-wallet --limit=50 --dispatch',
            'frequency' => 'everyMinute',
            'queue' => 'wallet_retry',
        ],
        'wallet_rollback_recovery' => [
            'command' => 'b2b:recover-rollbacks --limit=50 --dispatch',
            'frequency' => 'everyFiveMinutes',
            'queue' => 'wallet_retry',
        ],
        'wallet_reconciliation' => [
            'command' => 'b2b:reconcile-wallet --limit=100 --pending-minutes=' . env('B2B_WALLET_RECONCILIATION_PENDING_MINUTES', 5) . ' --dispatch',
            'frequency' => 'everyFiveMinutes',
            'queue' => 'reconciliation',
        ],
        'stale_sessions' => [
            'command' => 'b2b:close-stale-sessions --minutes=30 --dispatch',
            'frequency' => 'everyFiveMinutes',
            'queue' => 'maintenance',
        ],
    ],
];
