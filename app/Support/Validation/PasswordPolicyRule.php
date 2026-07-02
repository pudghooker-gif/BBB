<?php

namespace VanguardLTE\Support\Validation;

use Illuminate\Contracts\Validation\Rule;

class PasswordPolicyRule implements Rule
{
    private $message;

    public function passes($attribute, $value)
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (!is_string($value) && !(is_object($value) && method_exists($value, '__toString'))) {
            $this->message = 'The password must be a string.';
            return false;
        }

        $value = (string) $value;
        $min = (int) config('security.password_policy.min_length', 12);
        $max = (int) config('security.password_policy.max_length', 72);

        if (strlen($value) < $min) {
            $this->message = 'The password must be at least ' . $min . ' characters.';
            return false;
        }

        if ($max > 0 && strlen($value) > $max) {
            $this->message = 'The password must not be longer than ' . $max . ' characters.';
            return false;
        }

        if ((bool) config('security.password_policy.disallow_whitespace', true) && preg_match('/\s/', $value)) {
            $this->message = 'The password must not contain spaces or control characters.';
            return false;
        }

        if ((bool) config('security.password_policy.require_mixed_case', true)
            && (!preg_match('/[a-z]/', $value) || !preg_match('/[A-Z]/', $value))) {
            $this->message = 'The password must include both uppercase and lowercase letters.';
            return false;
        }

        if ((bool) config('security.password_policy.require_numbers', true) && !preg_match('/[0-9]/', $value)) {
            $this->message = 'The password must include at least one number.';
            return false;
        }

        if ((bool) config('security.password_policy.require_symbols', false) && !preg_match('/[^A-Za-z0-9]/', $value)) {
            $this->message = 'The password must include at least one symbol.';
            return false;
        }

        return true;
    }

    public function message()
    {
        return $this->message ?: 'The password does not meet the production password policy.';
    }
}
