<?php

namespace VanguardLTE\Exceptions\Authorization;

class RoleDeniedException extends AccessDeniedException
{
    public function __construct($role)
    {
        $this->message = sprintf("You don't have a required ['%s'] role.", $role);
    }
}
