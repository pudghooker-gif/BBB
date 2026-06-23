<?php

namespace Tests\Unit;

use ReflectionClass;
use Tests\TestCase;
use VanguardLTE\Http\Controllers\Api\B2B\ReportsController;
use VanguardLTE\B2B\Services\OperatorWalletClient;

class B2BConfigurationTest extends TestCase
{
    public function testB2BRoutesAreLoadedOnceAndPointToExistingActions()
    {
        $apiRoutes = file_get_contents(base_path('routes/api.php'));
        $b2bRoutes = file_get_contents(base_path('routes/b2b.php'));
        $kernel = file_get_contents(base_path('app/Http/Kernel.php'));

        $this->assertSame(1, substr_count($apiRoutes, "require base_path('routes/b2b.php')"));
        $this->assertStringContainsString("[GameLaunchController::class, 'store']", $b2bRoutes);
        $this->assertStringContainsString("'b2b.signature'", $kernel);
    }

    public function testReportsKeepMoneyAsDecimalStrings()
    {
        $controller = new ReportsController(
            app(\VanguardLTE\B2B\Services\B2BReportQuery::class),
            app(\VanguardLTE\B2B\Services\WalletTransactionLookupService::class)
        );
        $reflection = new ReflectionClass($controller);

        $add = $reflection->getMethod('decimalAdd');
        $add->setAccessible(true);
        $sub = $reflection->getMethod('decimalSub');
        $sub->setAccessible(true);

        $this->assertSame('0.30000000', $add->invoke($controller, '0.10000000', '0.20000000'));
        $this->assertSame('999999999999.99999999', $sub->invoke($controller, '1000000000000.00000000', '0.00000001'));
    }

    public function testWalletCallbackUrlRejectsNonHttpSchemes()
    {
        $reflection = new ReflectionClass(OperatorWalletClient::class);
        $client = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('validateCallbackUrl');
        $method->setAccessible(true);

        $result = $method->invoke($client, 'file:///etc/passwd');

        $this->assertSame('WALLET_CALLBACK_URL_INVALID', $result['code']);
    }
}
