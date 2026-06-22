<?php

namespace Tests\Feature;

use Tests\TestCase;

class B2BResponseEnvelopeTest extends TestCase
{
    public function testPublicB2BHealthUsesStandardEnvelope()
    {
        $response = $this->getJson('/api/b2b/v1/health');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.service', 'bbb-b2b');

        $this->assertNotEmpty($response->json('request_id'));
        $this->assertNotEmpty($response->headers->get('X-Request-Id'));
    }

    public function testMissingB2BAuthUsesStandardErrorEnvelope()
    {
        $response = $this->getJson('/api/b2b/v1/operator/me');

        $response->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('error.code', 'B2B_AUTH_FAILED');

        $this->assertNotEmpty($response->json('request_id'));
        $this->assertNotEmpty($response->headers->get('X-Request-Id'));
    }
}
