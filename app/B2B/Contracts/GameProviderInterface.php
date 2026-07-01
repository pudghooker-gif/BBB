<?php

namespace VanguardLTE\B2B\Contracts;

use VanguardLTE\B2B\Models\B2BGameSession;

interface GameProviderInterface
{
    public function providerCode();

    public function health();

    public function supportsWalletAction($action);

    public function walletActionContracts();

    public function walletActionContract($action);

    public function prepareLaunch(B2BGameSession $session);

    public function refreshSession(B2BGameSession $session);

    public function closeSession(B2BGameSession $session, $reason = null);
}
