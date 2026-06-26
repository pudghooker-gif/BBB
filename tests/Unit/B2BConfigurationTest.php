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
            app(\VanguardLTE\B2B\Services\B2BReconciliationReportQuery::class),
            app(\VanguardLTE\B2B\Services\B2BSettlementWorkflowService::class),
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

    public function testB2BApiArtifactsAreValidAndCoverVerifiedRoutes()
    {
        $openapi = $this->decodeJsonArtifact('docs/b2b/openapi.json');
        $postman = $this->decodeJsonArtifact('docs/b2b/postman_collection.json');

        $expectedPaths = [
            '/health',
            '/operator/me',
            '/games',
            '/games/launch',
            '/sessions',
            '/sessions/{session_uid}',
            '/sessions/{session_uid}/close',
            '/wallet/health',
            '/wallet/balance',
            '/wallet/bet',
            '/wallet/win',
            '/wallet/refund',
            '/wallet/rollback',
            '/wallet/transactions/{transaction_uid}/status',
            '/wallet/transactions/{transaction_uid}/attempts',
            '/reports/summary',
            '/reports/transactions',
            '/reports/ggr',
            '/reports/settlements',
            '/reports/settlements/export',
            '/reports/settlements/{settlement_uid}',
            '/reports/reconciliation',
            '/reports/transactions/{transaction_uid}',
        ];

        foreach ($expectedPaths as $path) {
            $this->assertArrayHasKey($path, $openapi['paths']);
        }

        $urls = $this->collectPostmanUrls($postman['item']);
        foreach ([
            '/api/b2b/v1/health',
            '/api/b2b/v1/games/launch',
            '/api/b2b/v1/sessions/{{sessionId}}/close',
            '/api/b2b/v1/wallet/bet',
            '/api/b2b/v1/wallet/transactions/{{transactionId}}/status',
            '/api/b2b/v1/reports/settlements/export',
            '/api/b2b/v1/reports/reconciliation',
            '/api/b2b/v1/reports/transactions/{{transactionId}}',
        ] as $needle) {
            $this->assertTrue(
                $this->postmanUrlsContain($urls, $needle),
                'Postman collection is missing ' . $needle
            );
        }
    }

    private function decodeJsonArtifact($path)
    {
        $decoded = json_decode(file_get_contents(base_path($path)), true);

        $this->assertSame(JSON_ERROR_NONE, json_last_error(), $path . ': ' . json_last_error_msg());
        $this->assertIsArray($decoded);

        return $decoded;
    }

    private function collectPostmanUrls(array $items)
    {
        $urls = [];

        foreach ($items as $item) {
            if (isset($item['item']) && is_array($item['item'])) {
                $urls = array_merge($urls, $this->collectPostmanUrls($item['item']));
                continue;
            }

            if (isset($item['request']['url']) && is_string($item['request']['url'])) {
                $urls[] = $item['request']['url'];
            }
        }

        return $urls;
    }

    private function postmanUrlsContain(array $urls, $needle)
    {
        foreach ($urls as $url) {
            if (strpos($url, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}
