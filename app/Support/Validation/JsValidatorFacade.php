<?php

namespace VanguardLTE\Support\Validation;

use Illuminate\Support\Facades\Facade;

class JsValidatorFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return JsValidator::class;
    }
}
