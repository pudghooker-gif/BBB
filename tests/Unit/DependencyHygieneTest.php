<?php

namespace Tests\Unit;

use Tests\TestCase;

class DependencyHygieneTest extends TestCase
{
    public function testRemovedLegacyDependenciesStayOutOfRootRequirements()
    {
        $composer = json_decode(file_get_contents(base_path('composer.json')), true);

        foreach ([
            'fideloper/proxy',
            'intergo/sms.to-laravel-lumen',
            'laravel/ui',
        ] as $package) {
            $this->assertArrayNotHasKey($package, $composer['require']);
            $this->assertArrayNotHasKey($package, $composer['require-dev']);
        }
    }

    public function testUnusedSmsToLaravelWrapperIsNotRegistered()
    {
        $appConfig = file_get_contents(base_path('config/app.php'));
        $smsSender = file_get_contents(base_path('app/Lib/SMS_sender.php'));

        $this->assertStringNotContainsString('SMSToServiceProvider', $appConfig);
        $this->assertStringNotContainsString('Intergo\SmsTo', $appConfig);
        $this->assertStringContainsString('GuzzleHttp\Client', $smsSender);
        $this->assertStringContainsString("config('smsto.base_url')", $smsSender);
    }
}
