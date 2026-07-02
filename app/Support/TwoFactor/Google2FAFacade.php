<?php

namespace VanguardLTE\Support\TwoFactor;

use Illuminate\Support\Facades\Facade;

class Google2FAFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'pragmarx.google2fa';
    }

    public static function logout()
    {
        static::$app['pragmarx.google2fa']->logout(request());
    }
}
