<?php

use Illuminate\Support\Str;						   
use Illuminate\Support\Arr;

if (! function_exists('array_get')) {
    function array_get($array, $key, $default = null)
    {
        return Arr::get($array, $key, $default);
    }
}

if (! function_exists('str_slug')) {
    function str_slug($title, $separator = '-', $language = 'en')
    {
        return Str::slug($title, $separator, $language);
    }
}

if (! function_exists('str_random')) {
    function str_random($length = 16)
    {
        return Str::random($length);
    }
}

if (! function_exists('settings')) {
    /**
     * Get / set the specified settings value.
     *
     * If an array is passed as the key, we will assume you want to set an array of values.
     *
     * @param  array|string  $key
     * @param  mixed  $default
     * @return mixed
     */
    function settings($key = null, $default = null)
    {
        if (is_null($key)) {
            return app('settings');
        }

        return app('settings')->get($key, $default);
    }
}

function encoded($str)
{
    return base64_encode(base64_encode($str));
}
function decoded($str)
{
    return base64_decode(base64_decode($str));
}

function hpRand($digit = 4)
{
    return substr(rand(0, 12345) . strrev(time()), 0, $digit);
}
function hpRandStr($digit = 4)
{
    $random = Str::random($digit);
    return $random;
}
