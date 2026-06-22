<?php

namespace VanguardLTE\B2B\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use VanguardLTE\B2B\Models\B2BSandboxWallet;
use VanguardLTE\B2B\Models\B2BSandboxWalletEntry;

class SandboxWalletService
{
    public function isEnabled()
    {
        $value = config('b2b.sandbox_enabled');

        if ($value === true || $value === 1 || $value === '1' || $value === 'true' || $value === 'TRUE') {
            return true;
        }

        return false;
    }

    public function tableStatus()
    {
        return [
            'wallets' => Schema::hasTable('b2b_sandbox_wallets'),
            'entries' => Schema::hasTable('b2b_sandbox_wallet_entries'),
            'operators' => Schema::hasTable('b2b_operators'),
        ];
    }

    public function ensureWallet($operatorId, $playerId, $currency, $initialBalance = 0, array $metadata = [])
    {
        if (!Schema::hasTable('b2b_sandbox_wallets')) {
            throw new \RuntimeException('b2b_sandbox_wallets table is missing. Run php artisan migrate.');
        }

        $currency = strtoupper((string) $currency);
        $operatorId = (int) $operatorId;
        $playerId = (string) $playerId;

        return DB::transaction(function () use ($operatorId, $playerId, $currency, $initialBalance, $metadata) {
            $wallet = B2BSandboxWallet::where('operator_id', $operatorId)
                ->where('external_player_id', $playerId)
                ->where('currency', $currency)
                ->lockForUpdate()
                ->first();

            if (!$wallet) {
                $wallet = B2BSandboxWallet::create([
                    'operator_id' => $operatorId,
                    'external_player_id' => $playerId,
                    'currency' => $currency,
                    'balance' => $this->normalizeAmount($initialBalance),
                    'status' => B2BSandboxWallet::STATUS_ACTIVE,
                    'metadata' => $metadata,
                ]);
            }

            return $wallet;
        });
    }

    public function getWallet($operatorId, $playerId, $currency)
    {
        if (!Schema::hasTable('b2b_sandbox_wallets')) {
            return null;
        }

        return B2BSandboxWallet::where('operator_id', (int) $operatorId)
            ->where('external_player_id', (string) $playerId)
            ->where('currency', strtoupper((string) $currency))
            ->first();
    }

    public function process($operator, $action, array $payload)
    {
        if (!$this->isEnabled()) {
            return $this->error('SANDBOX_DISABLED', 'B2B sandbox wallet is disabled.', 403);
        }

        $tables = $this->tableStatus();
        if (!$tables['wallets'] || !$tables['entries']) {
            return $this->error('SANDBOX_TABLES_MISSING', 'Run php artisan migrate before using sandbox wallet.', 500, $tables);
        }

        if (!$operator || !isset($operator->id)) {
            return $this->error('OPERATOR_NOT_FOUND', 'Operator was not resolved for sandbox wallet.', 404);
        }

        $action = strtolower((string) $action);
        if (!in_array($action, ['balance', 'bet', 'win', 'refund', 'rollback', 'credit', 'debit'], true)) {
            return $this->error('UNSUPPORTED_ACTION', 'Unsupported sandbox wallet action: '.$action, 422);
        }

        $playerId = isset($payload['player_id']) ? $payload['player_id'] : (isset($payload['external_player_id']) ? $payload['external_player_id'] : null);
        if (!$playerId) {
            return $this->error('PLAYER_ID_REQUIRED', 'player_id is required.', 422);
        }

        $currency = isset($payload['currency']) && $payload['currency'] ? strtoupper((string) $payload['currency']) : (isset($operator->default_currency) ? strtoupper((string) $operator->default_currency) : 'USD');
        $transactionId = isset($payload['transaction_id']) && $payload['transaction_id'] ? (string) $payload['transaction_id'] : (isset($payload['tx_id']) ? (string) $payload['tx_id'] : null);
        $roundId = isset($payload['round_id']) ? (string) $payload['round_id'] : '';
        $amount = isset($payload['amount']) ? $this->normalizeAmount($payload['amount']) : 0;

        if ($action !== 'balance' && $amount <= 0) {
            return $this->error('AMOUNT_REQUIRED', 'amount must be greater than zero for '.$action.'.', 422);
        }

        $idempotencyKey = $this->idempotencyKey($operator->id, $action, $transactionId, $roundId, $playerId, $amount, $currency);

        return DB::transaction(function () use ($operator, $action, $payload, $playerId, $currency, $transactionId, $amount, $idempotencyKey) {
            $existing = B2BSandboxWalletEntry::where('operator_id', $operator->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                $response = is_array($existing->response_payload) ? $existing->response_payload : [];
                $response['duplicate'] = true;
                return [
                    'ok' => $existing->status === B2BSandboxWalletEntry::STATUS_SUCCESS,
                    'http_status' => $existing->status === B2BSandboxWalletEntry::STATUS_REJECTED ? 402 : 200,
                    'body' => $response,
                ];
            }

            $wallet = B2BSandboxWallet::where('operator_id', $operator->id)
                ->where('external_player_id', (string) $playerId)
                ->where('currency', $currency)
                ->lockForUpdate()
                ->first();

            if (!$wallet) {
                $wallet = B2BSandboxWallet::create([
                    'operator_id' => $operator->id,
                    'external_player_id' => (string) $playerId,
                    'currency' => $currency,
                    'balance' => 0,
                    'status' => B2BSandboxWallet::STATUS_ACTIVE,
                    'metadata' => ['created_by' => 'sandbox_wallet_callback'],
                ]);
                $wallet = B2BSandboxWallet::where('id', $wallet->id)->lockForUpdate()->first();
            }

            if ($wallet->status !== B2BSandboxWallet::STATUS_ACTIVE) {
                return $this->writeEntry($wallet, $operator, $action, $transactionId, $idempotencyKey, $amount, $currency, $payload, [
                    'status' => 'rejected',
                    'code' => 'WALLET_BLOCKED',
                    'message' => 'Sandbox wallet is blocked.',
                    'balance' => (float) $wallet->balance,
                    'currency' => $currency,
                ], B2BSandboxWalletEntry::STATUS_REJECTED, $wallet->balance, 402);
            }

            $before = $this->normalizeAmount($wallet->balance);
            $after = $before;
            $entryStatus = B2BSandboxWalletEntry::STATUS_SUCCESS;
            $httpStatus = 200;
            $businessStatus = 'success';
            $code = null;
            $message = null;

            if ($action === 'bet' || $action === 'debit') {
                if ($before < $amount) {
                    $entryStatus = B2BSandboxWalletEntry::STATUS_REJECTED;
                    $httpStatus = 402;
                    $businessStatus = 'rejected';
                    $code = 'INSUFFICIENT_FUNDS';
                    $message = 'Sandbox wallet has insufficient funds.';
                } else {
                    $after = $this->normalizeAmount($before - $amount);
                }
            } elseif ($action === 'win' || $action === 'refund' || $action === 'rollback' || $action === 'credit') {
                $after = $this->normalizeAmount($before + $amount);
            }

            if ($entryStatus === B2BSandboxWalletEntry::STATUS_SUCCESS && $action !== 'balance') {
                $wallet->balance = $after;
                $wallet->last_transaction_at = Carbon::now();
                $wallet->save();
            }

            $body = [
                'status' => $businessStatus,
                'sandbox' => true,
                'action' => $action,
                'player_id' => (string) $playerId,
                'transaction_id' => $transactionId,
                'balance' => (float) $wallet->balance,
                'balance_before' => (float) $before,
                'balance_after' => (float) ($entryStatus === B2BSandboxWalletEntry::STATUS_SUCCESS ? $after : $before),
                'currency' => $currency,
                'duplicate' => false,
            ];

            if ($code) {
                $body['code'] = $code;
            }
            if ($message) {
                $body['message'] = $message;
            }

            return $this->writeEntry($wallet, $operator, $action, $transactionId, $idempotencyKey, $amount, $currency, $payload, $body, $entryStatus, $before, $httpStatus);
        });
    }

    protected function writeEntry($wallet, $operator, $action, $transactionId, $idempotencyKey, $amount, $currency, array $payload, array $body, $entryStatus, $balanceBefore, $httpStatus)
    {
        B2BSandboxWalletEntry::create([
            'wallet_id' => $wallet->id,
            'operator_id' => $operator->id,
            'action' => $action,
            'transaction_id' => $transactionId,
            'idempotency_key' => $idempotencyKey,
            'amount' => $amount,
            'currency' => $currency,
            'balance_before' => is_numeric($balanceBefore) ? $balanceBefore : $wallet->balance,
            'balance_after' => isset($body['balance_after']) ? $body['balance_after'] : $wallet->balance,
            'status' => $entryStatus,
            'raw_payload' => $payload,
            'response_payload' => $body,
        ]);

        return [
            'ok' => $entryStatus === B2BSandboxWalletEntry::STATUS_SUCCESS,
            'http_status' => $httpStatus,
            'body' => $body,
        ];
    }

    protected function idempotencyKey($operatorId, $action, $transactionId, $roundId, $playerId, $amount, $currency)
    {
        if ($transactionId) {
            return hash('sha256', implode('|', [$operatorId, $action, $transactionId]));
        }

        return hash('sha256', implode('|', [$operatorId, $action, $roundId, $playerId, $amount, $currency]));
    }

    protected function normalizeAmount($value)
    {
        return round((float) $value, 8);
    }

    protected function error($code, $message, $httpStatus, $details = null)
    {
        $body = [
            'status' => 'error',
            'sandbox' => true,
            'code' => $code,
            'message' => $message,
        ];

        if ($details !== null) {
            $body['details'] = $details;
        }

        return [
            'ok' => false,
            'http_status' => $httpStatus,
            'body' => $body,
        ];
    }
}
