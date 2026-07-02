<?php 
namespace VanguardLTE\Http\Requests\User
{
    class UpdateProfilePasswordRequest extends \VanguardLTE\Http\Requests\Request
    {
        public function rules()
        {
            return [
                'old_password' => 'required', 
                'password' => array_merge(
                    \VanguardLTE\Support\Security\PasswordPolicy::requiredConfirmedRules(),
                    ['different:old_password']
                ),
                'password_confirmation' => 'required'
            ];
        }
    }

}
