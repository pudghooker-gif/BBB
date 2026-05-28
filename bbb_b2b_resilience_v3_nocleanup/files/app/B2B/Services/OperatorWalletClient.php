<?php

namespace VanguardLTE\B2B\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Support\Facades\Crypt;
use VanguardLTE\B2B\Models\B2BOperator;
use VanguardLTE\B2B\Models\B2BWalletCallbackLog;
use VanguardLTE\B2B\Models\B2BWalletTransaction;

class OperatorWalletClient
{
    protected $guard;

    public function __construct(B2BResilienceGuard $guard)
    {
        $this->guard = $guard;
    }

    public function forward(B2BOperator $operator, B2BWalletTransaction $transaction, array $payload)
    {
        if (!$operator->wallet_callback_url) {
            return [
                'forwarded' => false,
                'accepted' => true,
                'status' => 'skipped',
                'error_code' => null,
                'message' => 'Operator wallet_callback_url is not configured. Sandbox/local mode accepted the request.',
            ];
        }

        $body = array_merge($payload, [
            'operator_id' => $operator->operator_uid,
            'aggregator_transaction_id' => $transaction->id,
            'transaction_uid' => $transaction->transaction_uid,
            'type' => $transaction->type,
        ]);

        $rawBody = json_encode($body);
        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(16));
        $secret = $this->callbackSecret($operator);
        $signature = $secret
            ? hash_hmac('sha256', $timestamp . '.' . $nonce . '.' . $rawBody, $secret)
            : null;

        $headers = [
            'Content-Type' => 'application/json',
            'X-Aggregator-Timestamp' => $timestamp,
            'X-Aggregator-Nonce' => $nonce,
        ];

        if ($signature) {
            $headers['X-Aggregator-Signature'] = $signature;
        }

        $startedAt = microtime(true);
        $log = $this->createLog($operator, $transaction, $headers, $body);

        try {
            $client = new Client([
                'timeout' => $this->guard->walletTimeoutSeconds($operator),
                'connect_timeout' => $this->guard->connectTimeoutSeconds($operator),
                'http_errors' => false,
            ]);

            $response = $client->post($operator->wallet_callback_url, [
                'headers' => $headers,
                'body' => $rawBody,
            ]);

            $responseBody = (string) $response->getBody();
            $decoded = json_decode($responseBody, true);
            $httpStatus = $response->getStatusCode();
            $accepted = $httpStatus >= 200 && $httpStatus < 300;

            if (is_array($decoded) && array_key_exists('success', $decoded)) {
                $accepted = $accepted && (bool) $decoded['success'];
            }

            $responsePayload = is_array($decoded) ? $decoded : ['raw' => $responseBody];
            $this->updateLog($log, [
                'http_status' => $httpStatus,
                'response_headers' => $response->getHeaders(),
                'response_body' => $responsePayload,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return [
                'forwarded' => true,
                'accepted' => $accepted,
                'http_status' => $httpStatus,
                'body' => $responsePayload,
                'error_code' => $accepted ? null : 'OPERATOR_REJECTED',
            ];
        } catch (TransferException $e) {
            $errorCode = $this->looksLikeTimeout($e->getMessage()) ? 'CALLBACK_TIMEOUT' : 'CALLBACK_TRANSPORT_ERROR';
            $this->updateLog($log, [
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'error_message' => $e->getMessage(),
            ]);

            return [
                'forwarded' => true,
                'accepted' => false,
                'error_code' => $errorCode,
                'error' => $e->getMessage(),
            ];
        } catch (\Exception $e) {
            $this->updateLog($log, [
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'error_message' => $e->getMessage(),
            ]);

            return [
                'forwarded' => true,
                'accepted' => false,
                'error_code' => 'CALLBACK_EXCEPTION',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function callbackSecret(B2BOperator $operator)
    {
        if (!$operator->callback_secret_encrypted) {
            return null;
        }

        try {
            return Crypt::decryptString($operator->callback_secret_encrypted);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function createLog(B2BOperator $operator, B2BWalletTransaction $transaction, array $headers, array $body)
    {
        try {
            return B2BWalletCallbackLog::create([
                'operator_id' => $operator->id,
                'wallet_transaction_id' => $transaction->id,
                'direction' => 'outbound',
                'endpoint' => $operator->wallet_callback_url,
                'request_headers' => $headers,
                'request_body' => $body,
            ]);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function updateLog($log, array $data)
    {
        if (!$log) {
            return;
        }

        try {
            $log->update($data);
        } catch (\Exception $e) {
            // Logging must never break live wallet traffic.
        }
    }

    private function looksLikeTimeout($message)
    {
        $message = strtolower((string) $message);
        return strpos($message, 'timed out') !== false
            || strpos($message, 'timeout') !== false
            || strpos($message, 'curl error 28') !== false;
    }
}
