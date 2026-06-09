<?php

namespace VanguardLTE\B2B\Contracts;

use VanguardLTE\B2B\Models\B2BGameSession;

interface GameProviderInterface
{
    public function prepareLaunch(B2BGameSession $session);
}
