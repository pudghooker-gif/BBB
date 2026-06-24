<?php

namespace VanguardLTE\B2B\Support;

class B2BErrorCatalog
{
    private static $errors = [
        'ATTEMPTS_TABLE_MISSING' => [500, 'Wallet attempts table is missing.'],
        'AMOUNT_REQUIRED' => [422, 'Amount must be greater than zero.'],
        'B2B_AUTH_FAILED' => [401, 'B2B authentication failed.'],
        'B2B_BODY_HASH_MISMATCH' => [401, 'Invalid request body hash.'],
        'B2B_REPLAY_DETECTED' => [409, 'Replay detected.'],
        'B2B_WALLET_TABLE_MISSING' => [500, 'B2B wallet transaction table is missing.'],
        'CURRENCY_NOT_ALLOWED' => [422, 'Currency is not allowed for this operator.'],
        'GAME_NOT_AVAILABLE' => [404, 'Game is not available for this operator.'],
        'IDEMPOTENCY_CONFLICT' => [409, 'Transaction idempotency key was already used with a different payload.'],
        'OPERATOR_CIRCUIT_OPEN' => [503, 'Operator circuit breaker is open.'],
        'OPERATOR_CONTEXT_MISSING' => [401, 'B2B operator context is missing.'],
        'OPERATOR_DISABLED' => [403, 'Operator is disabled or suspended.'],
        'OPERATOR_NOT_FOUND' => [401, 'Operator was not resolved.'],
        'PLAYER_BLOCKED' => [403, 'Player is not active.'],
        'RATE_LIMITED' => [429, 'Operator request rate limit exceeded.'],
        'RETURN_URL_NOT_ALLOWED' => [422, 'Return URL is not allowed for this operator.'],
        'SANDBOX_WALLET_NOT_FOUND' => [404, 'Sandbox wallet was not found.'],
        'SANDBOX_DISABLED' => [403, 'B2B sandbox wallet is disabled.'],
        'SANDBOX_TABLES_MISSING' => [500, 'Sandbox wallet tables are missing.'],
        'SANDBOX_WALLET_FAILED' => [502, 'Sandbox wallet request failed.'],
        'SESSION_NOT_FOUND' => [404, 'Session was not found.'],
        'TRANSACTION_NOT_FOUND' => [404, 'Transaction was not found.'],
        'UNSUPPORTED_ACTION' => [422, 'Unsupported wallet action.'],
        'WALLET_BLOCKED' => [402, 'Wallet is blocked.'],
        'WALLET_OPERATION_FAILED' => [502, 'Wallet operation failed.'],
        'VALIDATION_FAILED' => [422, 'Request validation failed.'],
    ];

    public static function httpStatus($code, $fallback = 400)
    {
        return isset(self::$errors[$code]) ? self::$errors[$code][0] : $fallback;
    }

    public static function message($code, $fallback = null)
    {
        if ($fallback !== null) {
            return $fallback;
        }

        return isset(self::$errors[$code]) ? self::$errors[$code][1] : 'B2B request failed.';
    }
}
