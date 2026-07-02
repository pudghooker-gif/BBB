<?php

namespace Tymon\JWTAuth\Facades;

use Illuminate\Support\Facades\Facade;

class JWTAuth extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'tymon.jwt.auth';
    }
}
