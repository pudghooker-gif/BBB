<?php

namespace VanguardLTE\B2B\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Crypt;
use VanguardLTE\B2B\Models\B2BOperator;
use VanguardLTE\B2B\Models\B2BWalletCallbackLog;
use VanguardLTE\B2B\Models\B2BWalletTransaction;

class OperatorWalletClient
{
    public function forward(B2BOperator $operator, B2BWalletTransaction $transaction, array $payload)
    {
        if (!$operator->wallet_callback_url) {
            return [
                'forwarded' => false,
                'status' => 'skipped',
                'message' => 'Operator wallet_callback_url is not configured',
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
        $log = B2BWalletCallbackLog::create([
            'operator_id' => $operator->id,
            'wallet_transaction_id' => $transaction->id,
            'direction' => 'outbound',
            'endpoint' => $operator->wallet_callback_url,
            'request_headers' => $headers,
            'request_body' => $body,
        ]);

        try {
            $client = new Client(['timeout' => 8, 'connect_timeout' => 3]);
            $response = $client->post($operator->wallet_callback_url, [
                'headers' => $headers,
                'body' => $rawBody,
            ]);

            $responseBody = (string) $response->getBody();
            $decoded = json_decode($responseBody, true);

            $log->update([
                'http_status' => $response->getStatusCode(),
                'response_headers' => $response->getHeaders(),
                'response_body' => is_array($decoded) ? $decoded : ['raw' => $responseBody],
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return [
                'forwarded' => true,
                'http_status' => $response->getStatusCode(),
                'body' => is_array($decoded) ? $decoded : ['raw' => $responseBody],
            ];
        } catch (\Exception $e) {
            $log->update([
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'error_message' => $e->getMessage(),
            ]);

            return [
                'forwarded' => true,
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
}
