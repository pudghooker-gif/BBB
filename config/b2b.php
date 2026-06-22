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

    'nonce_cache_store' => env('B2B_NONCE_CACHE_STORE', null),

    'rate_limit_cache_store' => env('B2B_RATE_LIMIT_CACHE_STORE', null),

    'sandbox_enabled' => env('B2B_SANDBOX_ENABLED', env('APP_ENV') !== 'production'),
];
