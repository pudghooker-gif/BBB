<?php

return [
    'web_step_up_ttl_seconds' => env('B2B_WEB_STEP_UP_TTL_SECONDS', 300),

    'permissions' => [
        'b2b.operators.create' => 'Create B2B operators and first credentials.',
        'b2b.operators.update' => 'Update B2B operator configuration.',
        'b2b.operators.suspend' => 'Suspend or resume B2B operators.',
        'b2b.credentials.rotate' => 'Rotate B2B operator API credentials.',
        'b2b.credentials.revoke' => 'Revoke B2B operator API credentials.',
        'b2b.wallet.manual_action' => 'Apply manual wallet state transitions.',
        'b2b.wallet.retry' => 'Retry B2B wallet callbacks.',
        'b2b.wallet.reconcile' => 'Run B2B wallet reconciliation scans.',
        'b2b.cases.view' => 'View B2B reconciliation cases.',
        'b2b.cases.manage' => 'Claim, resolve, and reopen B2B reconciliation cases.',
        'b2b.payloads.view_redacted' => 'View redacted wallet payloads.',
        'b2b.payloads.view_raw' => 'View raw sensitive payloads after step-up approval.',
        'b2b.reports.view' => 'View B2B reports.',
        'b2b.reports.export' => 'Export B2B reports.',
        'b2b.settlements.submit' => 'Submit exported B2B settlements for finance approval.',
        'b2b.settlements.approve' => 'Approve or reject submitted B2B settlements.',
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
                'b2b.cases.view',
                'b2b.cases.manage',
                'b2b.payloads.view_redacted',
                'b2b.audit.view',
                'b2b.system.release_check',
            ],
        ],
        'finance' => [
            'permissions' => [
                'b2b.wallet.manual_action',
                'b2b.wallet.reconcile',
                'b2b.cases.view',
                'b2b.cases.manage',
                'b2b.payloads.view_redacted',
                'b2b.reports.view',
                'b2b.reports.export',
                'b2b.settlements.submit',
                'b2b.settlements.approve',
                'b2b.audit.view',
            ],
        ],
        'support' => [
            'permissions' => [
                'b2b.payloads.view_redacted',
                'b2b.cases.view',
                'b2b.reports.view',
            ],
        ],
        'auditor' => [
            'permissions' => [
                'b2b.payloads.view_redacted',
                'b2b.cases.view',
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
                'b2b.cases.view',
                'b2b.payloads.view_redacted',
                'b2b.audit.view',
            ],
        ],
        'read_only' => [
            'permissions' => [
                'b2b.payloads.view_redacted',
                'b2b.cases.view',
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
        'operator.update' => [
            'permission' => 'b2b.operators.update',
            'confirm' => 'UPDATE_OPERATOR',
            'step_up' => true,
        ],
        'operator.suspend' => [
            'permission' => 'b2b.operators.suspend',
            'confirm' => 'SUSPEND_OPERATOR',
            'step_up' => true,
        ],
        'operator.resume' => [
            'permission' => 'b2b.operators.suspend',
            'confirm' => 'RESUME_OPERATOR',
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
        'payload.view_raw' => [
            'permission' => 'b2b.payloads.view_raw',
            'confirm' => 'VIEW_RAW_PAYLOAD',
            'step_up' => true,
        ],
        'case.claim' => [
            'permission' => 'b2b.cases.manage',
            'confirm' => 'CLAIM_CASE',
            'step_up' => true,
        ],
        'case.resolve' => [
            'permission' => 'b2b.cases.manage',
            'confirm' => 'RESOLVE_CASE',
            'step_up' => true,
        ],
        'case.reopen' => [
            'permission' => 'b2b.cases.manage',
            'confirm' => 'REOPEN_CASE',
            'step_up' => true,
        ],
        'settlement.submit' => [
            'permission' => 'b2b.settlements.submit',
            'confirm' => 'SUBMIT_SETTLEMENT',
            'step_up' => true,
        ],
        'settlement.approve' => [
            'permission' => 'b2b.settlements.approve',
            'confirm' => 'APPROVE_SETTLEMENT',
            'step_up' => true,
        ],
        'settlement.reject' => [
            'permission' => 'b2b.settlements.approve',
            'confirm' => 'REJECT_SETTLEMENT',
            'step_up' => true,
        ],
    ],
];
