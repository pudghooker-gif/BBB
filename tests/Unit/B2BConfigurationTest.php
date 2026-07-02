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
        $webRoutes = file_get_contents(base_path('routes/web.php'));
        $kernel = file_get_contents(base_path('app/Http/Kernel.php'));

        $this->assertSame(1, substr_count($apiRoutes, "require base_path('routes/b2b.php')"));
        $this->assertStringContainsString("[GameLaunchController::class, 'store']", $b2bRoutes);
        $this->assertStringContainsString("foreach (['credentials', 'games', 'sessions', 'transactions', 'settlements', 'cases', 'callbacks', 'reports', 'docs'] as \$portalSection)", $b2bRoutes);
        $this->assertStringContainsString("Route::get('portal/' . \$portalSection, [PortalController::class, 'section'])", $b2bRoutes);
        $this->assertStringContainsString("'as' => 'backend.b2b.dashboard'", $webRoutes);
        $this->assertStringContainsString("'uses' => 'B2BDashboardController@index'", $webRoutes);
        $this->assertStringContainsString("'as' => 'backend.b2b.wallet_manual_actions.index'", $webRoutes);
        $this->assertStringContainsString("'uses' => 'B2BWalletManualActionController@index'", $webRoutes);
        $this->assertStringContainsString("'as' => 'backend.b2b.wallet_manual_actions.store'", $webRoutes);
        $this->assertStringContainsString("'b2b.web_step_up:wallet.manual_action'", $webRoutes);
        $this->assertStringContainsString("'as' => 'backend.b2b.settlements.index'", $webRoutes);
        $this->assertStringContainsString("'uses' => 'B2BSettlementBackofficeController@index'", $webRoutes);
        $this->assertStringContainsString("'as' => 'backend.b2b.settlements.submit'", $webRoutes);
        $this->assertStringContainsString("'b2b.web_step_up:settlement.submit'", $webRoutes);
        $this->assertStringContainsString("'as' => 'backend.b2b.settlements.approve'", $webRoutes);
        $this->assertStringContainsString("'b2b.web_step_up:settlement.approve'", $webRoutes);
        $this->assertStringContainsString("'as' => 'backend.b2b.settlements.reject'", $webRoutes);
        $this->assertStringContainsString("'b2b.web_step_up:settlement.reject'", $webRoutes);
        $this->assertStringContainsString("'as' => 'backend.b2b.credentials.index'", $webRoutes);
        $this->assertStringContainsString("'uses' => 'B2BCredentialBackofficeController@index'", $webRoutes);
        $this->assertStringContainsString("'as' => 'backend.b2b.credentials.rotate'", $webRoutes);
        $this->assertStringContainsString("'b2b.web_step_up:api_key.rotate'", $webRoutes);
        $this->assertStringContainsString("'as' => 'backend.b2b.credentials.revoke'", $webRoutes);
        $this->assertStringContainsString("'b2b.web_step_up:api_key.revoke'", $webRoutes);
        $this->assertStringContainsString("'as' => 'backend.b2b.operators.index'", $webRoutes);
        $this->assertStringContainsString("'uses' => 'B2BOperatorBackofficeController@index'", $webRoutes);
        $this->assertStringContainsString("'as' => 'backend.b2b.operators.update'", $webRoutes);
        $this->assertStringContainsString("'b2b.web_step_up:operator.update'", $webRoutes);
        $this->assertStringContainsString("'as' => 'backend.b2b.operators.suspend'", $webRoutes);
        $this->assertStringContainsString("'b2b.web_step_up:operator.suspend'", $webRoutes);
        $this->assertStringContainsString("'as' => 'backend.b2b.operators.resume'", $webRoutes);
        $this->assertStringContainsString("'b2b.web_step_up:operator.resume'", $webRoutes);
        $this->assertStringContainsString("'as' => 'backend.b2b.payloads.index'", $webRoutes);
        $this->assertStringContainsString("'uses' => 'B2BPayloadBackofficeController@index'", $webRoutes);
        $this->assertStringContainsString("'as' => 'backend.b2b.payloads.raw'", $webRoutes);
        $this->assertStringContainsString("'b2b.admin:b2b.payloads.view_redacted'", $webRoutes);
        $this->assertStringContainsString("'b2b.admin:b2b.payloads.view_raw'", $webRoutes);
        $this->assertStringContainsString("'b2b.web_step_up:payload.view_raw'", $webRoutes);
        $this->assertStringContainsString("'as' => 'backend.b2b.cases.index'", $webRoutes);
        $this->assertStringContainsString("'uses' => 'B2BCaseBackofficeController@index'", $webRoutes);
        $this->assertStringContainsString("'as' => 'backend.b2b.cases.claim'", $webRoutes);
        $this->assertStringContainsString("'as' => 'backend.b2b.cases.resolve'", $webRoutes);
        $this->assertStringContainsString("'as' => 'backend.b2b.cases.reopen'", $webRoutes);
        $this->assertStringContainsString("'b2b.admin:b2b.cases.view'", $webRoutes);
        $this->assertStringContainsString("'b2b.admin:b2b.cases.manage'", $webRoutes);
        $this->assertStringContainsString("'b2b.web_step_up:case.claim'", $webRoutes);
        $this->assertStringContainsString("'b2b.web_step_up:case.resolve'", $webRoutes);
        $this->assertStringContainsString("'b2b.web_step_up:case.reopen'", $webRoutes);
        $this->assertStringContainsString("'as' => 'backend.b2b.audit.index'", $webRoutes);
        $this->assertStringContainsString("'uses' => 'B2BAuditBackofficeController@index'", $webRoutes);
        $this->assertStringContainsString("'b2b.admin:b2b.audit.view'", $webRoutes);
        $this->assertStringContainsString("'as' => 'backend.b2b.step_up.show'", $webRoutes);
        $this->assertStringContainsString("'uses' => 'B2BStepUpController@show'", $webRoutes);
        $this->assertStringContainsString("'middleware' => ['only_for_admin', 'b2b.admin:b2b.reports.view']", $webRoutes);
        $this->assertStringContainsString("'b2b.signature'", $kernel);
        $this->assertStringContainsString("'b2b.admin'", $kernel);
        $this->assertStringContainsString("'b2b.web_step_up'", $kernel);
    }

    public function testTrustedProxyConfigurationUsesLaravelMiddlewareAndEnvironment()
    {
        $middleware = file_get_contents(base_path('app/Http/Middleware/TrustProxies.php'));
        $config = file_get_contents(base_path('config/trustedproxy.php'));
        $envExample = file_get_contents(base_path('.env.example'));
        $composer = file_get_contents(base_path('composer.json'));

        $this->assertStringContainsString('Illuminate\Http\Middleware\TrustProxies', $middleware);
        $this->assertStringContainsString("env('TRUSTED_PROXIES') ?: null", $config);
        $this->assertStringContainsString('TRUSTED_PROXIES=', $envExample);
        $this->assertStringNotContainsString('Fideloper', $middleware);
        $this->assertStringNotContainsString('fideloper/proxy', $composer);
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
            '/readiness',
            '/metrics',
            '/operator/me',
            '/portal',
            '/portal/overview',
            '/portal/credentials',
            '/portal/games',
            '/portal/sessions',
            '/portal/transactions',
            '/portal/settlements',
            '/portal/cases',
            '/portal/callbacks',
            '/portal/reports',
            '/portal/docs',
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
            '/api/b2b/v1/readiness',
            '/api/b2b/v1/metrics',
            '/api/b2b/v1/portal?limit=10',
            '/api/b2b/v1/portal/overview',
            '/api/b2b/v1/portal/transactions?limit=10',
            '/api/b2b/v1/portal/cases?limit=10',
            '/api/b2b/v1/portal/callbacks?limit=10',
            '/api/b2b/v1/portal/reports?limit=10',
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
