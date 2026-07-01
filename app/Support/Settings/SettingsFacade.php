<?php

namespace VanguardLTE\Support\Settings;

use Illuminate\Support\Facades\Facade;

class SettingsFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'settings';
    }
}
