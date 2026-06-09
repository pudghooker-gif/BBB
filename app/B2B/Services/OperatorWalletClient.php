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

    public function call($operator, $action, array $payload, $transaction = null)
    {
        if (!$operator) {
            return $this->error('OPERATOR_NOT_FOUND', 'Operator was not resolved.', 0, null);
        }

        if ($this->circuitBreaker->isOpen($operator)) {
            return $this->error('CIRCUIT_OPEN', 'Operator wallet circuit breaker is open.', 0, null);
        }

        $url = $this->resolveUrl($operator, $action);
        if (!$url) {
            return $this->error('WALLET_CALLBACK_NOT_CONFIGURED', 'Operator wallet callback URL is empty.', 0, null);
        }

        $timeoutMs = isset($operator->wallet_timeout_ms) ? (int) $operator->wallet_timeout_ms : 5000;
        if ($timeoutMs < 1000) {
            $timeoutMs = 1000;
        }
        if ($timeoutMs > 15000) {
            $timeoutMs = 15000;
        }

        $payload['action'] = $action;
        $payload['timestamp'] = time();

        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-B2B-Wallet-Action' => $action,
        ];

        if (isset($operator->wallet_secret) && $operator->wallet_secret) {
            $raw = json_encode($payload);
            $headers['X-B2B-Signature'] = hash_hmac('sha256', $raw, $operator->wallet_secret);
        }

        $attemptId = $this->attemptLogger->start($transaction, $operator, $action, $url, $timeoutMs, $payload);
        $started = microtime(true);

        try {
            $response = Http::withHeaders($headers)
                ->timeout((int) ceil($timeoutMs / 1000))
                ->post($url, $payload);

            $durationMs = (int) round((microtime(true) - $started) * 1000);
            $body = $response->body();
            $json = $response->json();

            if ($response->successful()) {
                $this->attemptLogger->finish($attemptId, 'success', $response->status(), $body, null, $durationMs);
                $this->circuitBreaker->markSuccess($operator);

                return [
                    'ok' => true,
                    'status' => 'success',
                    'http_status' => $response->status(),
                    'body' => $json ?: $body,
                    'duration_ms' => $durationMs,
                ];
            }

            $this->attemptLogger->finish($attemptId, 'failed', $response->status(), $body, 'HTTP '.$response->status(), $durationMs);
            $this->circuitBreaker->markFailure($operator, 'HTTP '.$response->status());

            return $this->error('WALLET_HTTP_ERROR', 'Wallet callback returned HTTP '.$response->status(), $response->status(), $json ?: $body, $durationMs);
        } catch (Exception $e) {
            $durationMs = (int) round((microtime(true) - $started) * 1000);
            $this->attemptLogger->finish($attemptId, 'timeout', null, null, $e->getMessage(), $durationMs);
            $this->circuitBreaker->markFailure($operator, $e->getMessage());

            return $this->error('WALLET_TIMEOUT_OR_EXCEPTION', $e->getMessage(), 0, null, $durationMs);
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

    protected function error($code, $message, $httpStatus = 0, $body = null, $durationMs = null)
    {
        return [
            'ok' => false,
            'status' => 'failed',
            'code' => $code,
            'message' => $message,
            'http_status' => $httpStatus,
            'body' => $body,
            'duration_ms' => $durationMs,
        ];
    }
}
