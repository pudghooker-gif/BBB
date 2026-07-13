<?php

namespace VanguardLTE\B2B\Contracts;

use VanguardLTE\B2B\Models\B2BGameSession;

interface GameProviderInterface
{
    const CAPABILITY_SUPPORTED = 'supported';
    const CAPABILITY_UNSUPPORTED = 'unsupported';
    const CAPABILITY_NOT_APPLICABLE = 'not_applicable';
    const CAPABILITY_DEGRADED = 'degraded';

    public function providerCode();

    public function health();

    public function capabilities();

    public function capability($capability);

    public function listGames(array $filters = []);

    public function validateIncomingRequest($action, array $payload);

    public function normalizeTransaction(array $payload);

    public function supportsWalletAction($action);

    public function walletActionContracts();

    public function walletActionContract($action);

    public function prepareLaunch(B2BGameSession $session);

    public function refreshSession(B2BGameSession $session);

    public function closeSession(B2BGameSession $session, $reason = null);

    public function closeRound(B2BGameSession $session, $roundId = null, $reason = null);
}
