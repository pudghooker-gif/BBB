<?php

namespace VanguardLTE\Events\User;

use VanguardLTE\User;

class UserCredentialsChanged
{
    /**
     * @var User
     */
    private $user;

    /**
     * @var string
     */
    private $reason;

    public function __construct(User $user, $reason = 'password_change')
    {
        $this->user = $user;
        $this->reason = $reason;
    }

    /**
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }

    public function getReason()
    {
        return $this->reason;
    }
}
