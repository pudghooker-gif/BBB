<?php

namespace VanguardLTE\B2B\Services;

class B2BPayloadRedactor
{
    const REDACTED = '[REDACTED]';

    private $sensitiveKeys = [
        'authorization',
        'bearer',
        'password',
        'passphrase',
        'secret',
        'api_secret',
        'wallet_secret',
        'access_token',
        'refresh_token',
        'api_token',
        'auth_token',
        'token',
        'signature',
        'x_signature',
        'x_b2b_signature',
        'x_api_key',
        'api_key',
        'private_key',
        'card_number',
        'pan',
        'cvv',
        'cvc',
        'iban',
        'ssn',
    ];

    public function redact($value)
    {
        if (is_array($value)) {
            return $this->redactArray($value);
        }

        if (is_object($value)) {
            return $this->redactArray((array) $value);
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $this->redactArray($decoded);
            }

            return $this->redactText($value);
        }

        return $value;
    }

    public function json($value)
    {
        $json = json_encode($this->redact($value));

        return $json === false ? null : $json;
    }

    public function storageValue($value)
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                return $this->redactText($value);
            }
        }

        return $this->json($value);
    }

    private function redactArray(array $payload)
    {
        $redacted = [];

        foreach ($payload as $key => $value) {
            if ($this->isSensitiveKey($key)) {
                $redacted[$key] = self::REDACTED;
                continue;
            }

            $redacted[$key] = $this->redact($value);
        }

        return $redacted;
    }

    private function isSensitiveKey($key)
    {
        $normalized = strtolower((string) $key);
        $normalized = str_replace(['-', '.'], '_', $normalized);

        if (in_array($normalized, $this->sensitiveKeys, true)) {
            return true;
        }

        foreach (['_secret', '_token', '_signature', '_password', '_private_key'] as $needle) {
            if (strpos($normalized, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function redactText($value)
    {
        $value = (string) $value;

        $patterns = [
            '/(authorization\\s*[:=]\\s*(?:bearer\\s+)?)([^\\s,;]+)/i',
            '/(bearer\\s+)([A-Za-z0-9._\\-]+)/i',
            '/((?:api[_-]?key|token|secret|signature|password)\\s*[:=]\\s*)([^\\s,;]+)/i',
        ];

        foreach ($patterns as $pattern) {
            $value = preg_replace($pattern, '$1' . self::REDACTED, $value);
        }

        return $value;
    }
}
