<?php

return [
    /*
    |--------------------------------------------------------------------------
    | B2B Security Controls
    |--------------------------------------------------------------------------
    |
    | Private callback targets are disabled by default outside local/testing
    | environments to reduce SSRF risk. Enable only for isolated sandbox use.
    |
    */

    'allow_private_wallet_callbacks' => env('B2B_ALLOW_PRIVATE_WALLET_CALLBACKS', false),

    'hmac_replay_window_seconds' => env('B2B_HMAC_REPLAY_WINDOW_SECONDS', 300),

    'nonce_cache_store' => env('B2B_NONCE_CACHE_STORE', 'redis'),

    'rate_limit_cache_store' => env('B2B_RATE_LIMIT_CACHE_STORE', 'redis'),

    'api_key_default_max_rps' => env('B2B_API_KEY_DEFAULT_MAX_RPS', null),

    'api_key_usage_audit_sample_seconds' => env('B2B_API_KEY_USAGE_AUDIT_SAMPLE_SECONDS', 60),

    'wallet_retry_max_attempts' => env('B2B_WALLET_RETRY_MAX_ATTEMPTS', 3),

    'wallet_rollback_max_attempts' => env('B2B_WALLET_ROLLBACK_MAX_ATTEMPTS', 3),

    'wallet_reconciliation_pending_minutes' => env('B2B_WALLET_RECONCILIATION_PENDING_MINUTES', 5),

    'settlement_aggregator_fee_bps' => env('B2B_SETTLEMENT_AGGREGATOR_FEE_BPS', 0),

    'settlement_provider_fee_bps' => env('B2B_SETTLEMENT_PROVIDER_FEE_BPS', 0),

    'sandbox_enabled' => env('B2B_SANDBOX_ENABLED', false),
];
