<?php 
namespace VanguardLTE\Http\Requests\Auth
{
    class PasswordResetRequest extends \VanguardLTE\Http\Requests\Request
    {
        public function rules()
        {
            return [
                'token' => 'required', 
                'email' => 'required|email', 
                'password' => \VanguardLTE\Support\Security\PasswordPolicy::requiredConfirmedRules()
            ];
        }
    }

}
