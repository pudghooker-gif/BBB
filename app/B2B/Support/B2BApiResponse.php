<?php

namespace VanguardLTE\B2B\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class B2BApiResponse
{
    public static function success($request, $data = null, $httpStatus = 200, array $meta = [])
    {
        $requestId = self::requestId($request);

        $payload = [
            'success' => true,
            'status' => 'success',
            'request_id' => $requestId,
            'data' => $data,
        ];

        if (count($meta) > 0) {
            $payload['meta'] = $meta;
        }

        return self::json($payload, $httpStatus, $requestId);
    }

    public static function error($request, $code, $message = null, $httpStatus = null, $details = null, array $meta = [])
    {
        $requestId = self::requestId($request);
        $status = $httpStatus ?: B2BErrorCatalog::httpStatus($code);

        $payload = [
            'success' => false,
            'status' => 'error',
            'request_id' => $requestId,
            'error' => [
                'code' => $code,
                'message' => B2BErrorCatalog::message($code, $message),
            ],
        ];

        if ($details !== null) {
            $payload['error']['details'] = $details;
        }

        if (count($meta) > 0) {
            $payload['meta'] = $meta;
        }

        return self::json($payload, $status, $requestId);
    }

    private static function requestId($request)
    {
        if ($request instanceof Request) {
            $requestId = $request->attributes->get('request_id') ?: $request->header('X-Request-Id');
            if (!$requestId) {
                $requestId = (string) Str::uuid();
                $request->attributes->set('request_id', $requestId);
            }

            return $requestId;
        }

        if (is_string($request) && $request !== '') {
            return $request;
        }

        return (string) Str::uuid();
    }

    private static function json(array $payload, $httpStatus, $requestId)
    {
        return response()
            ->json($payload, $httpStatus)
            ->header('X-Request-Id', $requestId);
    }
}
