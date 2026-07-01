<?php

namespace VanguardLTE\Support\Settings;

class ArrayPath
{
    private function __construct()
    {
    }

    public static function get(array $data, $key, $default = null)
    {
        if ($key === null) {
            return $data;
        }

        if (is_array($key)) {
            $output = [];
            foreach ($key as $item) {
                static::set($output, $item, static::get($data, $item, static::get((array) $default, $item)));
            }

            return $output;
        }

        foreach (explode('.', $key) as $segment) {
            if (!is_array($data) || !array_key_exists($segment, $data)) {
                return $default;
            }

            $data = $data[$segment];
        }

        return $data;
    }

    public static function has(array $data, $key)
    {
        foreach (explode('.', $key) as $segment) {
            if (!is_array($data) || !array_key_exists($segment, $data)) {
                return false;
            }

            $data = $data[$segment];
        }

        return true;
    }

    public static function set(array &$data, $key, $value)
    {
        $segments = explode('.', $key);
        $last = array_pop($segments);

        foreach ($segments as $segment) {
            if (!array_key_exists($segment, $data)) {
                $data[$segment] = [];
            }

            if (!is_array($data[$segment])) {
                throw new \UnexpectedValueException('Non-array segment encountered');
            }

            $data =& $data[$segment];
        }

        $data[$last] = $value;
    }

    public static function forget(array &$data, $key)
    {
        $segments = explode('.', $key);
        $last = array_pop($segments);

        foreach ($segments as $segment) {
            if (!array_key_exists($segment, $data)) {
                return;
            }

            if (!is_array($data[$segment])) {
                throw new \UnexpectedValueException('Non-array segment encountered');
            }

            $data =& $data[$segment];
        }

        unset($data[$last]);
    }
}
