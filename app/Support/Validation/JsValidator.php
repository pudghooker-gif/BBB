<?php

namespace VanguardLTE\Support\Validation;

use Illuminate\Support\HtmlString;

class JsValidator
{
    public function formRequest($formRequest, $selector = null)
    {
        return new HtmlString('');
    }

    public function form($rules, $selector = null, array $messages = [], array $customAttributes = [])
    {
        return new HtmlString('');
    }
}
