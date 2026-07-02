<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Local JWT Authentication
    |--------------------------------------------------------------------------
    |
    | API tokens are signed with HS256 by the local
    | VanguardLTE\Services\Auth\Api\JWTAuth service. Each JWT carries a jti
    | stored in api_tokens, so logout, password changes, bans, and credential
    | resets can revoke active API sessions without the abandoned Tymon package.
    |
    */

    'secret' => env('JWT_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Token Lifetime
    |--------------------------------------------------------------------------
    |
    | Token lifetime is expressed in minutes. Set JWT_TTL to null only for a
    | controlled integration that has an external revocation process.
    |
    */

    'ttl' => env('JWT_TTL', 60),

    /*
    |--------------------------------------------------------------------------
    | Expired Token Cleanup Lottery
    |--------------------------------------------------------------------------
    |
    | Expired api_tokens rows are swept opportunistically when new tokens are
    | issued. The default odds are 5 out of 100 token creations.
    |
    */

    'lottery' => [5, 100],

    'algo' => 'HS256',
];
