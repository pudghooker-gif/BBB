<?php

namespace Tests\Feature;

use Tests\TestCase;

class B2BBackofficeRouteTest extends TestCase
{
    public function testB2BBackofficeRedirectsGuestsToBackendLogin()
    {
        $this->get('/backend/b2b')
            ->assertRedirect('/backend/login');
    }

    public function testB2BStepUpRedirectsGuestsToBackendLogin()
    {
        $this->get('/backend/b2b/step-up/api_key.rotate')
            ->assertRedirect('/backend/login');
    }

    public function testB2BManualWalletActionsRedirectGuestsToBackendLogin()
    {
        $this->get('/backend/b2b/wallet/manual-actions')
            ->assertRedirect('/backend/login');
    }

    public function testB2BSettlementsRedirectGuestsToBackendLogin()
    {
        $this->get('/backend/b2b/settlements')
            ->assertRedirect('/backend/login');
    }

    public function testB2BCredentialsRedirectGuestsToBackendLogin()
    {
        $this->get('/backend/b2b/credentials')
            ->assertRedirect('/backend/login');
    }

    public function testB2BOperatorsRedirectGuestsToBackendLogin()
    {
        $this->get('/backend/b2b/operators')
            ->assertRedirect('/backend/login');
    }
}
