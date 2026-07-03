<?php

namespace VanguardLTE\Exceptions\Authorization;

class PermissionDeniedException extends AccessDeniedException
{
    public function __construct($permission)
    {
        $this->message = sprintf("You don't have a required ['%s'] permission.", $permission);
    }
}
