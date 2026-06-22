<?php

namespace VanguardLTE\B2B\Services;

use Illuminate\Http\Request;

class B2BSignature
{
    public static function bodyHash($body)
    {
        return hash('sha256', (string) $body);
    }

    public static function canonicalRequest(Request $request, $bodyHash, $timestamp, $nonce)
    {
        return self::canonicalFromParts(
            $request->getMethod(),
            $request->getPathInfo(),
            self::canonicalQueryString($request->query()),
            $bodyHash,
            $timestamp,
            $nonce
        );
    }

    public static function canonicalFromParts($method, $path, $queryString, $bodyHash, $timestamp, $nonce)
    {
        return implode("\n", [
            strtoupper((string) $method),
            self::normalizePath($path),
            self::canonicalQueryStringFromString($queryString),
            strtolower((string) $bodyHash),
            (string) $timestamp,
            (string) $nonce,
        ]);
    }

    public static function canonicalQueryStringFromString($queryString)
    {
        if (!$queryString) {
            return '';
        }

        parse_str((string) $queryString, $query);

        return self::canonicalQueryString($query);
    }

    public static function canonicalQueryString(array $query)
    {
        if (count($query) === 0) {
            return '';
        }

        $query = self::sortRecursive($query);

        return http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    private static function normalizePath($path)
    {
        $path = (string) $path;
        if ($path === '') {
            return '/';
        }

        return '/' . ltrim($path, '/');
    }

    private static function sortRecursive(array $value)
    {
        ksort($value);

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = self::sortRecursive($item);
            }
        }

        return $value;
    }
}
