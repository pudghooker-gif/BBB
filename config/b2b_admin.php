<?php

return [
    'permissions' => [
        'b2b.operators.create' => 'Create B2B operators and first credentials.',
        'b2b.operators.update' => 'Update B2B operator configuration.',
        'b2b.operators.suspend' => 'Suspend or resume B2B operators.',
        'b2b.credentials.rotate' => 'Rotate B2B operator API credentials.',
        'b2b.credentials.revoke' => 'Revoke B2B operator API credentials.',
        'b2b.wallet.manual_action' => 'Apply manual wallet state transitions.',
        'b2b.wallet.retry' => 'Retry B2B wallet callbacks.',
        'b2b.wallet.reconcile' => 'Run B2B wallet reconciliation scans.',
        'b2b.payloads.view_redacted' => 'View redacted wallet payloads.',
        'b2b.payloads.view_raw' => 'View raw sensitive payloads after step-up approval.',
        'b2b.reports.view' => 'View B2B reports.',
        'b2b.reports.export' => 'Export B2B reports.',
        'b2b.audit.view' => 'View B2B audit events.',
        'b2b.system.release_check' => 'Run B2B production release checks.',
    ],

    'roles' => [
        'super_admin' => [
            'permissions' => ['*'],
        ],
        'operations' => [
            'permissions' => [
                'b2b.operators.update',
                'b2b.operators.suspend',
                'b2b.credentials.rotate',
                'b2b.credentials.revoke',
                'b2b.wallet.retry',
                'b2b.wallet.reconcile',
                'b2b.payloads.view_redacted',
                'b2b.audit.view',
                'b2b.system.release_check',
            ],
        ],
        'finance' => [
            'permissions' => [
                'b2b.wallet.manual_action',
                'b2b.wallet.reconcile',
                'b2b.payloads.view_redacted',
                'b2b.reports.view',
                'b2b.reports.export',
                'b2b.audit.view',
            ],
        ],
        'support' => [
            'permissions' => [
                'b2b.payloads.view_redacted',
                'b2b.reports.view',
            ],
        ],
        'auditor' => [
            'permissions' => [
                'b2b.payloads.view_redacted',
                'b2b.reports.view',
                'b2b.audit.view',
            ],
        ],
        'integration_manager' => [
            'permissions' => [
                'b2b.operators.create',
                'b2b.operators.update',
                'b2b.credentials.rotate',
                'b2b.credentials.revoke',
                'b2b.payloads.view_redacted',
                'b2b.audit.view',
            ],
        ],
        'read_only' => [
            'permissions' => [
                'b2b.payloads.view_redacted',
                'b2b.reports.view',
                'b2b.audit.view',
            ],
        ],
    ],

    'privileged_actions' => [
        'operator.create' => [
            'permission' => 'b2b.operators.create',
            'confirm' => 'CREATE_OPERATOR',
            'step_up' => true,
        ],
        'api_key.rotate' => [
            'permission' => 'b2b.credentials.rotate',
            'confirm' => 'ROTATE_API_KEY',
            'step_up' => true,
        ],
        'api_key.revoke' => [
            'permission' => 'b2b.credentials.revoke',
            'confirm' => 'REVOKE_API_KEY',
            'step_up' => true,
        ],
        'wallet.manual_action' => [
            'permission' => 'b2b.wallet.manual_action',
            'confirm' => 'MANUAL_WALLET_ACTION',
            'step_up' => true,
        ],
    ],
];
