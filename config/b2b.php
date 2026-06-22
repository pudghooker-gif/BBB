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
];
