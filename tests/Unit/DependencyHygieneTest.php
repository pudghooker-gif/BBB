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
            'anlutro/l4-settings',
            'barryvdh/laravel-debugbar',
            'fideloper/proxy',
            'intergo/sms.to-laravel-lumen',
            'jeremykenedy/laravel-roles',
            'eklundkristoffer/seedster',
            'laravelcollective/html',
            'laravel/helpers',
            'laracasts/presenter',
            'laravel/legacy-factories',
            'laravel/ui',
            'pragmarx/google2fa-laravel',
            'proengsoft/laravel-jsvalidation',
            'tymon/jwt-auth',
            'yajra/laravel-datatables-oracle',
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
        $this->assertStringNotContainsString('anlutro\LaravelSettings\Facade', $appConfig);
        $this->assertStringNotContainsString('Yajra\DataTables', $appConfig);
        $this->assertStringContainsString('GuzzleHttp\Client', $smsSender);
        $this->assertStringContainsString("config('smsto.base_url')", $smsSender);
    }

    public function testUnusedServerSideDataTablesPackageConfigIsRemoved()
    {
        $this->assertFileDoesNotExist(base_path('config/datatables.php'));
    }

    public function testLaravelJsValidationPackageIsReplacedByLocalCompatibilityFacade()
    {
        $appConfig = file_get_contents(base_path('config/app.php'));

        $this->assertStringNotContainsString('Proengsoft\JsValidation', $appConfig);
        $this->assertStringContainsString('VanguardLTE\Support\Validation\JsValidatorFacade', $appConfig);
        $this->assertFileExists(base_path('app/Support/Validation/JsValidator.php'));
        $this->assertFileDoesNotExist(base_path('config/jsvalidation.php'));
        $this->assertDirectoryDoesNotExist(base_path('resources/views/vendor/jsvalidation'));
    }

    public function testGoogle2faLaravelWrapperIsReplacedByLocalMiddlewareAndFacade()
    {
        $appConfig = file_get_contents(base_path('config/app.php'));
        $google2faConfig = file_get_contents(base_path('config/google2fa.php'));
        $kernel = file_get_contents(base_path('app/Http/Kernel.php'));

        $this->assertStringNotContainsString('PragmaRX\Google2FALaravel', $appConfig);
        $this->assertStringNotContainsString('PragmaRX\Google2FALaravel', $google2faConfig);
        $this->assertStringNotContainsString('PragmaRX\Google2FALaravel', $kernel);
        $this->assertStringContainsString('VanguardLTE\Support\TwoFactor\Google2FAFacade', $appConfig);
        $this->assertStringContainsString('VanguardLTE\Http\Middleware\Google2FAMiddleware', $kernel);
        $this->assertFileExists(base_path('app/Support/TwoFactor/Google2FAService.php'));
    }

    public function testTymonJwtAuthPackageIsReplacedByLocalCompatibilityLayer()
    {
        $appConfig = file_get_contents(base_path('config/app.php'));
        $jwtConfig = file_get_contents(base_path('config/jwt.php'));
        $jwtServiceProvider = file_get_contents(base_path('app/Services/Auth/Api/JWTServiceProvider.php'));

        $this->assertStringContainsString('VanguardLTE\Services\Auth\Api\JWTServiceProvider', $appConfig);
        $this->assertStringContainsString('Tymon\JWTAuth\Facades\JWTAuth', $appConfig);
        $this->assertStringContainsString("singleton('tymon.jwt.auth'", $jwtServiceProvider);
        $this->assertStringContainsString('new JWTAuth(', $jwtServiceProvider);
        $this->assertStringContainsString("Auth::extend('jwt'", $jwtServiceProvider);
        $this->assertStringNotContainsString('Tymon\JWTAuth\Providers', $jwtConfig);
        $this->assertStringNotContainsString('Namshi', $jwtConfig);
        $this->assertFileExists(base_path('app/Support/Jwt/Tymon/Contracts/JWTSubject.php'));
    }

    public function testLaravelRolesRuntimeModelsUseLocalCompatibilityLayer()
    {
        $appConfig = file_get_contents(base_path('config/app.php'));
        $kernel = file_get_contents(base_path('app/Http/Kernel.php'));
        $rolesConfig = file_get_contents(base_path('config/roles.php'));
        $userModel = file_get_contents(base_path('app/User.php'));
        $roleModel = file_get_contents(base_path('app/Role.php'));
        $permissionModel = file_get_contents(base_path('app/Permission.php'));

        $this->assertStringNotContainsString('jeremykenedy\LaravelRoles\RolesServiceProvider', $appConfig);
        $this->assertStringNotContainsString('jeremykenedy\LaravelRoles\App\Http\Middleware', $kernel);
        $this->assertStringContainsString('VanguardLTE\Http\Middleware\VerifyRole', $kernel);
        $this->assertStringContainsString('VanguardLTE\Http\Middleware\VerifyWebPermission', $kernel);
        $this->assertStringContainsString('VanguardLTE\Http\Middleware\VerifyLevel', $kernel);
        $this->assertStringContainsString('VanguardLTE\Role::class', $rolesConfig);
        $this->assertStringContainsString('VanguardLTE\Permission::class', $rolesConfig);
        $this->assertStringContainsString('VanguardLTE\Support\Authorization\AuthorizationUserTrait', $userModel);
        $this->assertStringContainsString('VanguardLTE\Support\Authorization\AuthorizationRoleTrait', $roleModel);
        $this->assertStringContainsString('class Permission extends Model', $permissionModel);
        $this->assertStringNotContainsString('HasRoleAndPermission', $userModel);
        $this->assertStringNotContainsString('jeremykenedy\LaravelRoles\Models\Role::class', $rolesConfig);
        $this->assertStringNotContainsString('jeremykenedy\LaravelRoles\Models\Permission::class', $rolesConfig);
    }

    public function testLaravelCollectiveHtmlPackageIsReplacedByLocalCompatibilityLayer()
    {
        $appConfig = file_get_contents(base_path('config/app.php'));
        $htmlProvider = file_get_contents(base_path('app/Providers/HtmlServiceProvider.php'));

        $this->assertStringNotContainsString('Collective\Html', $appConfig);
        $this->assertStringNotContainsString('Collective\Html', $htmlProvider);
        $this->assertStringContainsString('VanguardLTE\Support\Html\FormFacade', $appConfig);
        $this->assertStringContainsString('VanguardLTE\Support\Html\HtmlFacade', $appConfig);
        $this->assertStringContainsString('VanguardLTE\Support\Html\FormBuilder', $htmlProvider);
        $this->assertStringContainsString('VanguardLTE\Support\Html\HtmlBuilder', $htmlProvider);
        $this->assertFileExists(base_path('app/Support/Html/FormBuilder.php'));
        $this->assertFileExists(base_path('app/Support/Html/HtmlBuilder.php'));
    }

    public function testRemovedLaravelHelpersPackageIsReplacedByLocalCompatibilityHelpers()
    {
        $helpers = file_get_contents(base_path('app/Support/helpers.php'));

        $this->assertTrue(function_exists('array_get'));
        $this->assertTrue(function_exists('str_slug'));
        $this->assertTrue(function_exists('str_random'));
        $this->assertStringContainsString('function array_get', $helpers);
        $this->assertStringContainsString('function str_slug', $helpers);
        $this->assertStringContainsString('function str_random', $helpers);
    }

    public function testLocalGoogle2faServiceVerifiesOtpAndGeneratesInlineQrCode()
    {
        config(['google2fa.qrcode_image_backend' => 'svg']);
        $this->app->forgetInstance('pragmarx.google2fa');
        $this->app->forgetInstance(\VanguardLTE\Support\TwoFactor\Google2FAService::class);

        $google2fa = app('pragmarx.google2fa');
        $secret = $google2fa->generateSecretKey();

        $this->assertTrue((bool) $google2fa->verifyGoogle2FA($secret, $google2fa->getCurrentOtp($secret)));
        $this->assertStringStartsWith(
            'data:image/svg+xml;base64,',
            $google2fa->getQRCodeInline('GOLDSVET', 'admin@example.test', $secret)
        );
    }

    public function testLegacyFactoryClassmapIsRemoved()
    {
        $composer = json_decode(file_get_contents(base_path('composer.json')), true);

        $this->assertNotContains('database/factories', $composer['autoload']['classmap']);
        $this->assertFileDoesNotExist(base_path('database/factories/UserFactory.php'));
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
