<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Validator;
use Tests\TestCase;
use VanguardLTE\Support\Security\PasswordPolicy;

class PasswordPolicyTest extends TestCase
{
    public function testPasswordPolicyRejectsWeakPasswords()
    {
        foreach (['shortA1', 'longbutlowercase1', 'LONGERBUTNOLOWER1', 'LongEnoughNoNumber'] as $password) {
            $validator = Validator::make(
                ['password' => $password],
                ['password' => PasswordPolicy::requiredRules()]
            );

            $this->assertTrue($validator->fails(), $password . ' should not satisfy the production password policy.');
        }
    }

    public function testPasswordPolicyAcceptsStrongPassword()
    {
        $validator = Validator::make(
            ['password' => 'LongEnoughA12'],
            ['password' => PasswordPolicy::requiredRules()]
        );

        $this->assertFalse($validator->fails());
    }

    public function testGeneratedTemporaryPasswordsSatisfyPolicy()
    {
        $password = PasswordPolicy::generateTemporaryPassword();
        $validator = Validator::make(
            ['password' => $password],
            ['password' => PasswordPolicy::requiredRules()]
        );

        $this->assertFalse($validator->fails());
    }

    public function testGeneratedTemporaryCredentialsAreUsernameSafeAndSatisfyDefaultPolicy()
    {
        $credential = PasswordPolicy::generateTemporaryCredential();
        $validator = Validator::make(
            [
                'username' => $credential,
                'password' => $credential,
            ],
            [
                'username' => 'required|regex:/^[A-Za-z0-9]+$/',
                'password' => PasswordPolicy::requiredRules(),
            ]
        );

        $this->assertFalse($validator->fails());
    }
}
