<?php

return [
    'login_throttle' => [
        'production_enforced' => env('LOGIN_THROTTLE_PRODUCTION_ENFORCED', true),
        'max_attempts' => env('LOGIN_THROTTLE_MAX_ATTEMPTS', 10),
        'lockout_minutes' => env('LOGIN_THROTTLE_LOCKOUT_MINUTES', 1),
    ],

    'password_policy' => [
        'min_length' => env('PASSWORD_POLICY_MIN_LENGTH', 12),
        'max_length' => env('PASSWORD_POLICY_MAX_LENGTH', 72),
        'require_mixed_case' => env('PASSWORD_POLICY_REQUIRE_MIXED_CASE', true),
        'require_numbers' => env('PASSWORD_POLICY_REQUIRE_NUMBERS', true),
        'require_symbols' => env('PASSWORD_POLICY_REQUIRE_SYMBOLS', false),
        'disallow_whitespace' => env('PASSWORD_POLICY_DISALLOW_WHITESPACE', true),
        'temporary_length' => env('PASSWORD_POLICY_TEMPORARY_LENGTH', 16),
    ],
];
