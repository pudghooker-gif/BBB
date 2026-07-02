<?php

namespace VanguardLTE\B2B\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class B2BStructuredEventLogger
{
    const COMPONENT = 'b2b';

    private $redactor;

    public function __construct(B2BPayloadRedactor $redactor)
    {
        $this->redactor = $redactor;
    }

    public function info($event, array $context = [])
    {
        $this->write('info', $event, $context);
    }

    public function warning($event, array $context = [])
    {
        $this->write('warning', $event, $context);
    }

    public function error($event, array $context = [])
    {
        $this->write('error', $event, $context);
    }

    public function request($event, Request $request, array $context = [], $level = 'info')
    {
        $operator = $request->attributes->get('b2b_operator');
        $apiKey = $request->attributes->get('b2b_api_key');

        $requestContext = [
            'request_id' => $request->attributes->get('request_id') ?: $request->header('X-Request-Id'),
            'method' => strtoupper($request->method()),
            'path' => '/' . ltrim($request->path(), '/'),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];

        if ($operator) {
            $requestContext['operator_id'] = $operator->id;
            $requestContext['operator_uid'] = $operator->operator_uid;
        }

        if ($apiKey) {
            $requestContext['key_id'] = $apiKey->key_id;
        }

        $this->write($level, $event, array_merge($requestContext, $context));
    }

    public function payload($level, $event, array $context = [])
    {
        $payload = [
            'component' => self::COMPONENT,
            'event' => (string) $event,
            'level' => $this->normalizeLevel($level),
        ];

        foreach ($this->sanitize($context) as $key => $value) {
            if ($value !== null) {
                $payload[$key] = $value;
            }
        }

        return $payload;
    }

    private function write($level, $event, array $context = [])
    {
        if (!(bool) config('b2b.structured_logging_enabled', true)) {
            return;
        }

        $level = $this->normalizeLevel($level);
        $payload = $this->payload($level, $event, $context);

        try {
            Log::channel($this->channel())->log($level, 'b2b.event', $payload);
        } catch (Throwable $e) {
            // Observability must not make B2B traffic fail.
        }
    }

    private function sanitize($value)
    {
        $value = $this->redactor->redact($value);

        if (is_array($value)) {
            $sanitized = [];
            foreach ($value as $key => $item) {
                $sanitized[$key] = $this->sanitize($item);
            }

            return $sanitized;
        }

        if (is_object($value)) {
            return $this->sanitize((array) $value);
        }

        if (is_string($value)) {
            return $this->limitString($this->redactor->storageValue($value));
        }

        return $value;
    }

    private function limitString($value)
    {
        $value = (string) $value;

        return strlen($value) > 2000 ? substr($value, 0, 2000) . '...' : $value;
    }

    private function normalizeLevel($level)
    {
        $level = strtolower((string) $level);

        return in_array($level, ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'], true)
            ? $level
            : 'info';
    }

    private function channel()
    {
        return config('b2b.structured_log_channel') ?: config('logging.default', 'stack');
    }
}
