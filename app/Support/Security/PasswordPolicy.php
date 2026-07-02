<?php

namespace VanguardLTE\Support\Security;

use VanguardLTE\Support\Validation\PasswordPolicyRule;

class PasswordPolicy
{
    const ALPHANUMERIC_UPPER = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    const ALPHANUMERIC_LOWER = 'abcdefghijkmnopqrstuvwxyz';
    const ALPHANUMERIC_DIGITS = '23456789';
    const SYMBOLS = '!@#$%^&*()-_=+[]{}:,.?';

    public static function requiredRules()
    {
        return ['required', new PasswordPolicyRule()];
    }

    public static function nullableRules()
    {
        return ['nullable', new PasswordPolicyRule()];
    }

    public static function requiredConfirmedRules()
    {
        return array_merge(self::requiredRules(), ['confirmed']);
    }

    public static function nullableConfirmedRules()
    {
        return array_merge(self::nullableRules(), ['confirmed']);
    }

    public static function generateTemporaryPassword($length = null)
    {
        $sets = [
            self::ALPHANUMERIC_UPPER,
            self::ALPHANUMERIC_LOWER,
        ];

        if ((bool) config('security.password_policy.require_numbers', true)) {
            $sets[] = self::ALPHANUMERIC_DIGITS;
        }

        if ((bool) config('security.password_policy.require_symbols', false)) {
            $sets[] = self::SYMBOLS;
        }

        return self::generateFromSets($sets, $length);
    }

    public static function generateTemporaryCredential($length = null)
    {
        return self::generateFromSets([
            self::ALPHANUMERIC_UPPER,
            self::ALPHANUMERIC_LOWER,
            self::ALPHANUMERIC_DIGITS,
        ], $length);
    }

    private static function generateFromSets(array $sets, $length = null)
    {
        $min = (int) config('security.password_policy.min_length', 12);
        $max = (int) config('security.password_policy.max_length', 72);
        $configuredLength = (int) ($length ?: config('security.password_policy.temporary_length', 16));
        $length = max($min, $configuredLength, count($sets));

        if ($max > 0) {
            $length = min($length, $max);
        }

        $characters = [];
        foreach ($sets as $set) {
            $characters[] = self::randomCharacter($set);
        }

        $combined = implode('', $sets);
        while (count($characters) < $length) {
            $characters[] = self::randomCharacter($combined);
        }

        for ($i = count($characters) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            $tmp = $characters[$i];
            $characters[$i] = $characters[$j];
            $characters[$j] = $tmp;
        }

        return implode('', $characters);
    }

    private static function randomCharacter($characters)
    {
        return $characters[random_int(0, strlen($characters) - 1)];
    }
}
