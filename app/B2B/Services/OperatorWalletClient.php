<?php

namespace VanguardLTE\B2B\Services;

use Exception;
use Illuminate\Support\Facades\Http;

class OperatorWalletClient
{
    protected $attemptLogger;
    protected $circuitBreaker;

    public function __construct(WalletAttemptLogger $attemptLogger, OperatorCircuitBreaker $circuitBreaker)
    {
        $this->attemptLogger = $attemptLogger;
        $this->circuitBreaker = $circuitBreaker;
    }

    public function call($operator, $action, array $payload, $transaction = null, array $context = [])
    {
        $correlation = $this->correlationContext($context, $transaction);

        if (!$operator) {
            return $this->error('OPERATOR_NOT_FOUND', 'Operator was not resolved.', 0, null, null, $correlation);
        }

        if ($this->circuitBreaker->isOpen($operator)) {
            return $this->error('CIRCUIT_OPEN', 'Operator wallet circuit breaker is open.', 0, null, null, $correlation);
        }

        $url = $this->resolveUrl($operator, $action);
        if (!$url) {
            return $this->error('WALLET_CALLBACK_NOT_CONFIGURED', 'Operator wallet callback URL is empty.', 0, null, null, $correlation);
        }

        $urlError = $this->validateCallbackUrl($url);
        if ($urlError) {
            return $this->error($urlError['code'], $urlError['message'], 0, null, null, $correlation);
        }

        $timeoutMs = isset($operator->wallet_timeout_ms) ? (int) $operator->wallet_timeout_ms : 5000;
        if ($timeoutMs < 1000) {
            $timeoutMs = 1000;
        }
        if ($timeoutMs > 15000) {
            $timeoutMs = 15000;
        }

        $connectTimeoutMs = isset($operator->connect_timeout_ms) ? (int) $operator->connect_timeout_ms : 1500;
        if ($connectTimeoutMs < 250) {
            $connectTimeoutMs = 250;
        }
        if ($connectTimeoutMs > $timeoutMs) {
            $connectTimeoutMs = $timeoutMs;
        }

        $payload['action'] = $action;
        $payload['timestamp'] = time();

        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-B2B-Wallet-Action' => $action,
        ];

        if (!empty($correlation['request_id'])) {
            $headers['X-Request-Id'] = $correlation['request_id'];
        }

        if (!empty($correlation['transaction_uid'])) {
            $headers['X-B2B-Transaction-Uid'] = $correlation['transaction_uid'];
        }

        if (isset($operator->wallet_secret) && $operator->wallet_secret) {
            $raw = json_encode($payload);
            $headers['X-B2B-Signature'] = hash_hmac('sha256', $raw, $operator->wallet_secret);
        }

        $attemptId = $this->attemptLogger->start($transaction, $operator, $action, $url, $timeoutMs, $payload, $correlation);
        $started = microtime(true);

        try {
            $response = Http::withHeaders($headers)
                ->withOptions(['connect_timeout' => (int) ceil($connectTimeoutMs / 1000)])
                ->timeout((int) ceil($timeoutMs / 1000))
                ->post($url, $payload);

            $durationMs = (int) round((microtime(true) - $started) * 1000);
            $body = $response->body();
            $json = $response->json();

            if ($response->successful()) {
                $this->attemptLogger->finish($attemptId, 'success', $response->status(), $body, null, $durationMs);
                $this->circuitBreaker->markSuccess($operator);

                return $this->withCorrelation([
                    'ok' => true,
                    'status' => 'success',
                    'http_status' => $response->status(),
                    'body' => $json ?: $body,
                    'duration_ms' => $durationMs,
                ], $correlation);
            }

            $this->attemptLogger->finish($attemptId, 'failed', $response->status(), $body, 'HTTP '.$response->status(), $durationMs);
            $this->circuitBreaker->markFailure($operator, 'HTTP '.$response->status());

            return $this->error('WALLET_HTTP_ERROR', 'Wallet callback returned HTTP '.$response->status(), $response->status(), $json ?: $body, $durationMs, $correlation);
        } catch (Exception $e) {
            $durationMs = (int) round((microtime(true) - $started) * 1000);
            $this->attemptLogger->finish($attemptId, 'timeout', null, null, $e->getMessage(), $durationMs);
            $this->circuitBreaker->markFailure($operator, $e->getMessage());

            return $this->error('WALLET_TIMEOUT_OR_EXCEPTION', $e->getMessage(), 0, null, $durationMs, $correlation);
        }
    }

    protected function resolveUrl($operator, $action)
    {
        $specificColumn = 'wallet_'.$action.'_url';
        if (isset($operator->{$specificColumn}) && $operator->{$specificColumn}) {
            return $operator->{$specificColumn};
        }

        if (isset($operator->wallet_callback_url) && $operator->wallet_callback_url) {
            return $operator->wallet_callback_url;
        }

        if (isset($operator->callback_url) && $operator->callback_url) {
            return $operator->callback_url;
        }

        return null;
    }

    protected function validateCallbackUrl($url)
    {
        $parts = parse_url($url);
        $scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : null;
        $host = isset($parts['host']) ? strtolower($parts['host']) : null;

        if (!in_array($scheme, ['http', 'https'], true) || !$host) {
            return [
                'code' => 'WALLET_CALLBACK_URL_INVALID',
                'message' => 'Wallet callback URL must be absolute HTTP or HTTPS URL.',
            ];
        }

        if ($this->privateCallbackTargetsAllowed()) {
            return null;
        }

        if (in_array($host, ['localhost', 'localhost.localdomain'], true)) {
            return [
                'code' => 'WALLET_CALLBACK_URL_BLOCKED',
                'message' => 'Wallet callback URL points to a local host.',
            ];
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : gethostbynamel($host);
        if (!$ips || !is_array($ips)) {
            return [
                'code' => 'WALLET_CALLBACK_HOST_UNRESOLVED',
                'message' => 'Wallet callback host could not be resolved.',
            ];
        }

        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return [
                    'code' => 'WALLET_CALLBACK_URL_BLOCKED',
                    'message' => 'Wallet callback URL resolves to a private or reserved IP address.',
                ];
            }
        }

        return null;
    }

    protected function privateCallbackTargetsAllowed()
    {
        return app()->environment('local', 'testing') || (bool) config('b2b.allow_private_wallet_callbacks', false);
    }

    protected function correlationContext(array $context, $transaction = null)
    {
        $correlation = [];

        if (!empty($context['request_id'])) {
            $correlation['request_id'] = (string) $context['request_id'];
        }

        if ($transaction && isset($transaction->transaction_uid) && $transaction->transaction_uid) {
            $correlation['transaction_uid'] = (string) $transaction->transaction_uid;
        }

        return $correlation;
    }

    protected function error($code, $message, $httpStatus = 0, $body = null, $durationMs = null, array $correlation = [])
    {
        return $this->withCorrelation([
            'ok' => false,
            'status' => 'failed',
            'code' => $code,
            'message' => $message,
            'http_status' => $httpStatus,
            'body' => $body,
            'duration_ms' => $durationMs,
        ], $correlation);
    }

    protected function withCorrelation(array $result, array $correlation)
    {
        foreach (['request_id', 'transaction_uid'] as $key) {
            if (!empty($correlation[$key])) {
                $result[$key] = $correlation[$key];
            }
        }

        return $result;
    }
}
