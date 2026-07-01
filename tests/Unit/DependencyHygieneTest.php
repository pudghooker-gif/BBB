<?php

namespace Tests\Unit;

use Tests\TestCase;
use VanguardLTE\User;

class DependencyHygieneTest extends TestCase
{
    public function testRemovedLegacyDependenciesStayOutOfRootRequirements()
    {
        $composer = json_decode(file_get_contents(base_path('composer.json')), true);

        foreach ([
            'barryvdh/laravel-debugbar',
            'fideloper/proxy',
            'intergo/sms.to-laravel-lumen',
            'laracasts/presenter',
            'laravel/legacy-factories',
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

    public function testLegacyFactoryClassmapIsRemoved()
    {
        $composer = json_decode(file_get_contents(base_path('composer.json')), true);

        $this->assertNotContains('database/factories', $composer['autoload']['classmap']);
        $this->assertFileNotExists(base_path('database/factories/UserFactory.php'));
    }

    public function testLocalPresenterKeepsUserPresentationBehavior()
    {
        $user = new User();
        $user->username = 'alice';
        $user->first_name = 'Ada';
        $user->last_name = 'Lovelace';

        $this->assertSame('alice', $user->present()->username);
        $this->assertSame('Ada Lovelace', $user->present()->name);
        $this->assertSame($user->present(), $user->present());
    }
}
