<?php

namespace VanguardLTE\B2B\Services;

class WalletTransactionStatusResolver
{
    protected $client;
    protected $redactor;

    public function __construct(OperatorWalletClient $client, B2BPayloadRedactor $redactor)
    {
        $this->client = $client;
        $this->redactor = $redactor;
    }

    public function lookup($operator, $transaction)
    {
        if (!$transaction || !isset($transaction->id)) {
            return $this->result(false, false, null, 'missing_transaction', null, 'Wallet transaction is missing.', null, null, null);
        }

        $walletResult = $this->client->call($operator, 'transaction_status', $this->payload($transaction), $transaction);
        $body = isset($walletResult['body']) ? $walletResult['body'] : null;
        $lookupStatus = $this->extractLookupStatus($body);
        $mapped = $this->mapLookupStatus($lookupStatus);
        $ok = !empty($walletResult['ok']);
        $final = $ok && $mapped !== null;

        return $this->result(
            $ok,
            $final,
            $mapped,
            $lookupStatus ?: 'unknown',
            isset($walletResult['http_status']) ? $walletResult['http_status'] : null,
            isset($walletResult['message']) ? $walletResult['message'] : null,
            isset($walletResult['code']) ? $walletResult['code'] : null,
            isset($walletResult['duration_ms']) ? $walletResult['duration_ms'] : null,
            $body
        );
    }

    private function payload($transaction)
    {
        return $this->withoutNulls([
            'transaction_uid' => isset($transaction->transaction_uid) ? $transaction->transaction_uid : null,
            'transaction_id' => isset($transaction->transaction_id) ? $transaction->transaction_id : null,
            'idempotency_key' => isset($transaction->idempotency_key) ? $transaction->idempotency_key : null,
            'round_id' => isset($transaction->round_id) ? $transaction->round_id : null,
            'session_id' => isset($transaction->session_id) ? $transaction->session_id : null,
            'game_uid' => isset($transaction->game_uid) ? $transaction->game_uid : null,
            'game_id' => isset($transaction->game_id) ? $transaction->game_id : null,
            'provider' => isset($transaction->provider) ? $transaction->provider : null,
            'type' => isset($transaction->type) ? $transaction->type : null,
            'amount' => isset($transaction->amount) ? (string) $transaction->amount : null,
            'currency' => isset($transaction->currency) ? $transaction->currency : null,
            'current_status' => isset($transaction->status) ? $transaction->status : null,
            'created_at' => isset($transaction->created_at) ? (string) $transaction->created_at : null,
            'processed_at' => isset($transaction->processed_at) ? (string) $transaction->processed_at : null,
        ]);
    }

    private function extractLookupStatus($body)
    {
        if (is_string($body)) {
            $decoded = json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $this->extractLookupStatus($decoded);
            }

            return $this->normalizeStatusWord($body);
        }

        if (is_object($body)) {
            $body = (array) $body;
        }

        if (!is_array($body)) {
            return null;
        }

        $paths = [
            ['transaction_status'],
            ['status'],
            ['state'],
            ['result'],
            ['outcome'],
            ['data', 'transaction_status'],
            ['data', 'status'],
            ['data', 'state'],
            ['transaction', 'transaction_status'],
            ['transaction', 'status'],
            ['transaction', 'state'],
            ['wallet', 'transaction_status'],
            ['wallet', 'status'],
            ['wallet', 'state'],
        ];

        foreach ($paths as $path) {
            $value = $this->valueAtPath($body, $path);
            if ($value !== null && !is_array($value) && !is_object($value)) {
                return $this->normalizeStatusWord($value);
            }
        }

        return $this->recursiveStatus($body);
    }

    private function recursiveStatus(array $body, $depth = 0)
    {
        if ($depth > 3) {
            return null;
        }

        foreach ($body as $key => $value) {
            $normalizedKey = $this->normalizeStatusWord($key);
            if (in_array($normalizedKey, ['transaction_status', 'status', 'state', 'result', 'outcome'], true)
                && !is_array($value)
                && !is_object($value)) {
                return $this->normalizeStatusWord($value);
            }
        }

        foreach ($body as $value) {
            if (is_object($value)) {
                $value = (array) $value;
            }

            if (is_array($value)) {
                $found = $this->recursiveStatus($value, $depth + 1);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    private function valueAtPath(array $body, array $path)
    {
        $value = $body;
        foreach ($path as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    private function mapLookupStatus($lookupStatus)
    {
        $status = $this->normalizeStatusWord($lookupStatus);

        $map = [
            'success' => WalletTransactionStateMachine::STATUS_SUCCESS,
            'accepted' => WalletTransactionStateMachine::STATUS_SUCCESS,
            'settled' => WalletTransactionStateMachine::STATUS_SUCCESS,
            'processed' => WalletTransactionStateMachine::STATUS_SUCCESS,
            'confirmed' => WalletTransactionStateMachine::STATUS_SUCCESS,
            'complete' => WalletTransactionStateMachine::STATUS_SUCCESS,
            'completed' => WalletTransactionStateMachine::STATUS_SUCCESS,
            'ok' => WalletTransactionStateMachine::STATUS_SUCCESS,
            'failed' => WalletTransactionStateMachine::STATUS_FAILED,
            'fail' => WalletTransactionStateMachine::STATUS_FAILED,
            'declined' => WalletTransactionStateMachine::STATUS_FAILED,
            'rejected' => WalletTransactionStateMachine::STATUS_FAILED,
            'denied' => WalletTransactionStateMachine::STATUS_FAILED,
            'canceled' => WalletTransactionStateMachine::STATUS_FAILED,
            'cancelled' => WalletTransactionStateMachine::STATUS_FAILED,
            'error' => WalletTransactionStateMachine::STATUS_FAILED,
            'rollback_required' => WalletTransactionStateMachine::STATUS_ROLLBACK_REQUIRED,
            'reversal_required' => WalletTransactionStateMachine::STATUS_ROLLBACK_REQUIRED,
            'refund_required' => WalletTransactionStateMachine::STATUS_ROLLBACK_REQUIRED,
            'needs_rollback' => WalletTransactionStateMachine::STATUS_ROLLBACK_REQUIRED,
            'reversed' => WalletTransactionStateMachine::STATUS_REVERSED,
            'rolled_back' => WalletTransactionStateMachine::STATUS_REVERSED,
            'rollback_success' => WalletTransactionStateMachine::STATUS_REVERSED,
            'refunded' => WalletTransactionStateMachine::STATUS_REVERSED,
            'voided' => WalletTransactionStateMachine::STATUS_REVERSED,
        ];

        return isset($map[$status]) ? $map[$status] : null;
    }

    private function normalizeStatusWord($value)
    {
        if ($value === null) {
            return null;
        }

        $value = strtolower(trim((string) $value));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value);
        $value = trim((string) $value, '_');

        return $value === '' ? null : $value;
    }

    private function result($ok, $final, $status, $lookupStatus, $httpStatus, $message, $code, $durationMs, $raw)
    {
        return [
            'ok' => (bool) $ok,
            'final' => (bool) $final,
            'status' => $status,
            'lookup_status' => $lookupStatus,
            'source' => 'operator',
            'http_status' => $httpStatus,
            'message' => $message,
            'code' => $code,
            'duration_ms' => $durationMs,
            'raw' => $this->redactor->redact($raw),
        ];
    }

    private function withoutNulls(array $payload)
    {
        $filtered = [];
        foreach ($payload as $key => $value) {
            if ($value !== null && $value !== '') {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
    }
}
