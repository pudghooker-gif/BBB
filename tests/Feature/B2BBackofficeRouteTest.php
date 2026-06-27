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
}
