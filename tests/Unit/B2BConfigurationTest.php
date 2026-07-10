<?php

namespace Tests\Unit;

use ReflectionClass;
use Tests\TestCase;
use VanguardLTE\B2B\Services\B2BOperatorPortalQuery;
use VanguardLTE\Http\Controllers\Api\B2B\ReportsController;
use VanguardLTE\B2B\Services\OperatorWalletClient;

class B2BConfigurationTest extends TestCase
{
    public function testB2BRoutesAreLoadedOnceAndPointToExistingActions()
    {
        $apiRoutes = file_get_contents(base_path('routes/api.php'));
        $b2bRoutes = file_get_contents(base_path('routes/b2b.php'));
        $b2bWalletRoutes = file_get_contents(base_path('routes/b2b_wallet_v7.php'));
        $b2bSandboxRoutes = file_get_contents(base_path('routes/b2b_sandbox_v8.php'));
        $webRoutes = file_get_contents(base_path('routes/web.php'));
        $kernel = file_get_contents(base_path('app/Http/Kernel.php'));

        $this->assertSame(1, substr_count($apiRoutes, "require base_path('routes/b2b.php')"));
        $this->assertStringContainsString("[GameLaunchController::class, 'store']", $b2bRoutes);
        $this->assertStringContainsString("foreach (['credentials', 'games', 'sessions', 'transactions', 'settlements', 'cases', 'callbacks', 'reports', 'support', 'docs'] as \$portalSection)", $b2bRoutes);
        $this->assertStringContainsString("Route::get('portal/' . \$portalSection, [PortalController::class, 'section'])", $b2bRoutes);
        $this->assertStringContainsString("Route::get('portal/support/cases/{transaction_uid}', [PortalController::class, 'showCase'])", $b2bRoutes);
        $this->assertStringContainsString("Route::get('portal/support/cases/{transaction_uid}/thread', [PortalController::class, 'showCaseThread'])", $b2bRoutes);
        $this->assertStringContainsString("Route::post('portal/support/cases/{transaction_uid}/comments', [PortalController::class, 'commentCase'])", $b2bRoutes);
        $this->assertStringContainsString("Route::get('portal/support/tickets/{ticket_uid}', [PortalController::class, 'showSupportTicket'])", $b2bRoutes);
        $this->assertStringContainsString("Route::get('portal/support/tickets/{ticket_uid}/thread', [PortalController::class, 'showSupportTicketThread'])", $b2bRoutes);
        $this->assertStringContainsString("Route::post('portal/support/tickets', [PortalController::class, 'createSupportTicket'])", $b2bRoutes);
        $this->assertStringContainsString("Route::post('portal/support/tickets/{ticket_uid}/comments', [PortalController::class, 'commentSupportTicket'])", $b2bRoutes);
        $this->assertStringContainsString("Route::post('portal/support/tickets/{ticket_uid}/close', [PortalController::class, 'closeSupportTicket'])", $b2bRoutes);
        $this->assertStringContainsString("Route::get('games/{game_uid}', [GameCatalogController::class, 'show'])", $b2bRoutes);
        foreach ([
            'b2b.scope:operator.read',
            'b2b.scope:portal.read',
            'b2b.scope:support.write',
            'b2b.scope:games.read',
            'b2b.scope:games.launch',
            'b2b.scope:sessions.read',
            'b2b.scope:sessions.close',
            'b2b.scope:wallet.balance',
            'b2b.scope:wallet.status',
            'b2b.scope:wallet.mutate',
            'b2b.scope:reports.read',
            'b2b.scope:reports.export',
        ] as $scopeMiddleware) {
            $this->assertStringContainsString($scopeMiddleware, $b2bRoutes . $b2bWalletRoutes);
        }
        $this->assertStringContainsString('b2b.scope:sandbox.wallet.read', $b2bSandboxRoutes);
        $this->assertStringContainsString('b2b.scope:sandbox.wallet.mutate', $b2bSandboxRoutes);
        $this->assertStringContainsString("middleware('b2b.scope:reports.export')", $b2bRoutes);
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
        $this->assertStringContainsString("'as' => 'backend.b2b.cases.support_ticket.comment'", $webRoutes);
        $this->assertStringContainsString("'as' => 'backend.b2b.cases.support_ticket.close'", $webRoutes);
        $this->assertStringContainsString("'as' => 'backend.b2b.cases.support_ticket.reopen'", $webRoutes);
        $this->assertStringContainsString("'b2b.admin:b2b.cases.view'", $webRoutes);
        $this->assertStringContainsString("'b2b.admin:b2b.cases.manage'", $webRoutes);
        $this->assertStringContainsString("'b2b.web_step_up:case.claim'", $webRoutes);
        $this->assertStringContainsString("'b2b.web_step_up:case.resolve'", $webRoutes);
        $this->assertStringContainsString("'b2b.web_step_up:case.reopen'", $webRoutes);
        $this->assertStringContainsString("'b2b.web_step_up:support_ticket.comment'", $webRoutes);
        $this->assertStringContainsString("'b2b.web_step_up:support_ticket.close'", $webRoutes);
        $this->assertStringContainsString("'b2b.web_step_up:support_ticket.reopen'", $webRoutes);
        $this->assertStringContainsString("'as' => 'backend.b2b.audit.index'", $webRoutes);
        $this->assertStringContainsString("'uses' => 'B2BAuditBackofficeController@index'", $webRoutes);
        $this->assertStringContainsString("'b2b.admin:b2b.audit.view'", $webRoutes);
        $this->assertStringContainsString("'as' => 'backend.b2b.step_up.show'", $webRoutes);
        $this->assertStringContainsString("'uses' => 'B2BStepUpController@show'", $webRoutes);
        $this->assertStringContainsString("'middleware' => ['only_for_admin', 'b2b.admin:b2b.reports.view']", $webRoutes);
        $this->assertStringContainsString("'b2b.signature'", $kernel);
        $this->assertStringContainsString("'b2b.scope'", $kernel);
        $this->assertStringContainsString("'b2b.admin'", $kernel);
        $this->assertStringContainsString("'b2b.web_step_up'", $kernel);
    }

    public function testB2BApiKeyScopesProtectSettlementExport()
    {
        $config = file_get_contents(base_path('config/b2b.php'));
        $migration = file_get_contents(base_path('database/migrations/2026_06_24_000011_add_scopes_to_b2b_operator_api_keys_table.php'));
        $model = file_get_contents(base_path('app/B2B/Models/B2BOperatorApiKey.php'));
        $middleware = file_get_contents(base_path('app/Http/Middleware/RequireB2BApiScope.php'));
        $routes = file_get_contents(base_path('routes/b2b.php'));
        $releaseGate = file_get_contents(base_path('app/B2B/Services/B2BReleaseGate.php'));
        $envExample = file_get_contents(base_path('.env.example'));
        $releaseChecks = file_get_contents(base_path('docs/b2b/RELEASE_CHECKS.md'));
        $apiDocs = file_get_contents(base_path('docs/b2b/API.md'));

        $this->assertStringContainsString("'api_key_default_scopes'", $config);
        $this->assertNotContains('reports.export', config('b2b.api_key_default_scopes'));
        $this->assertNotContains('*', config('b2b.api_key_default_scopes'));
        $this->assertStringContainsString("json('scopes')", $migration);
        $this->assertStringContainsString("'scopes' => 'array'", $model);
        $this->assertStringContainsString('B2B_SCOPE_DENIED', $middleware);
        $this->assertStringContainsString("middleware('b2b.scope:reports.export')", $routes);
        $this->assertStringContainsString('api_key_scopes', $releaseGate);
        $this->assertStringContainsString('B2B_API_KEY_DEFAULT_SCOPES=', $envExample);
        $this->assertStringContainsString('reports.export', $releaseChecks);
        $this->assertStringContainsString('dedicated `reports.export` scope', $apiDocs);
    }

    public function testB2BPayloadRedactionAuditCommandAndDocsArePresent()
    {
        $redactor = file_get_contents(base_path('app/B2B/Services/B2BPayloadRedactor.php'));
        $auditor = file_get_contents(base_path('app/B2B/Services/B2BPayloadRedactionAuditor.php'));
        $console = file_get_contents(base_path('routes/b2b_console.php'));
        $releaseGate = file_get_contents(base_path('app/B2B/Services/B2BReleaseGate.php'));
        $releaseChecks = file_get_contents(base_path('docs/b2b/RELEASE_CHECKS.md'));
        $runbook = file_get_contents(base_path('docs/deployment/PRODUCTION_RUNBOOK.md'));

        $this->assertStringContainsString('return $this->redactText($value);', $redactor);
        $this->assertStringContainsString('b2b_wallet_transaction_attempts', $auditor);
        $this->assertStringContainsString('needsRedaction', $auditor);
        $this->assertStringContainsString('b2b:payload-redaction-audit', $console);
        $this->assertStringContainsString('payload_redaction_audit', $releaseGate);
        $this->assertStringContainsString('b2b:payload-redaction-audit', $releaseChecks);
        $this->assertStringContainsString('PAYLOAD_REDACTION_ARTIFACT', $runbook);
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

    public function testSessionCookieSecurityDefaultsAndProductionEnvDocsArePresent()
    {
        $session = file_get_contents(base_path('config/session.php'));
        $envExample = file_get_contents(base_path('.env.example'));
        $releaseChecks = file_get_contents(base_path('docs/b2b/RELEASE_CHECKS.md'));

        $this->assertStringContainsString("env('SESSION_SECURE_COOKIE', env('APP_ENV', 'production') === 'production')", $session);
        $this->assertStringContainsString("env('SESSION_HTTP_ONLY', true)", $session);
        $this->assertStringContainsString("env('SESSION_SAME_SITE', 'lax')", $session);

        foreach (['SESSION_SECURE_COOKIE', 'SESSION_HTTP_ONLY', 'SESSION_SAME_SITE'] as $key) {
            $this->assertStringContainsString($key, $envExample);
            $this->assertStringContainsString($key, $releaseChecks);
        }
    }

    public function testLoginThrottleSecurityPolicyAndProductionDocsArePresent()
    {
        $security = file_get_contents(base_path('config/security.php'));
        $backendAuth = file_get_contents(base_path('app/Http/Controllers/Web/Backend/Auth/AuthController.php'));
        $frontendAuth = file_get_contents(base_path('app/Http/Controllers/Web/Frontend/Auth/AuthController.php'));
        $envExample = file_get_contents(base_path('.env.example'));
        $releaseChecks = file_get_contents(base_path('docs/b2b/RELEASE_CHECKS.md'));

        foreach ([
            'LOGIN_THROTTLE_PRODUCTION_ENFORCED',
            'LOGIN_THROTTLE_MAX_ATTEMPTS',
            'LOGIN_THROTTLE_LOCKOUT_MINUTES',
        ] as $key) {
            $this->assertStringContainsString($key, $envExample);
            $this->assertStringContainsString($key, $releaseChecks);
        }

        $this->assertStringContainsString("'max_attempts' => env('LOGIN_THROTTLE_MAX_ATTEMPTS', 10)", $security);
        $this->assertStringContainsString("'lockout_minutes' => env('LOGIN_THROTTLE_LOCKOUT_MINUTES', 1)", $security);

        foreach ([$backendAuth, $frontendAuth] as $controller) {
            $this->assertStringContainsString('loginThrottlingEnabled', $controller);
            $this->assertStringContainsString('productionLoginThrottleEnforced', $controller);
            $this->assertStringContainsString('security.login_throttle.max_attempts', $controller);
            $this->assertStringNotContainsString('lockoutTime() / 60', $controller);
        }
    }

    public function testPasswordPolicySecurityAndProductionDocsArePresent()
    {
        $security = file_get_contents(base_path('config/security.php'));
        $policy = file_get_contents(base_path('app/Support/Security/PasswordPolicy.php'));
        $rule = file_get_contents(base_path('app/Support/Validation/PasswordPolicyRule.php'));
        $releaseGate = file_get_contents(base_path('app/B2B/Services/B2BReleaseGate.php'));
        $envExample = file_get_contents(base_path('.env.example'));
        $releaseChecks = file_get_contents(base_path('docs/b2b/RELEASE_CHECKS.md'));

        foreach ([
            'PASSWORD_POLICY_MIN_LENGTH',
            'PASSWORD_POLICY_MAX_LENGTH',
            'PASSWORD_POLICY_REQUIRE_MIXED_CASE',
            'PASSWORD_POLICY_REQUIRE_NUMBERS',
            'PASSWORD_POLICY_REQUIRE_SYMBOLS',
            'PASSWORD_POLICY_DISALLOW_WHITESPACE',
            'PASSWORD_POLICY_TEMPORARY_LENGTH',
        ] as $key) {
            $this->assertStringContainsString($key, $envExample);
            $this->assertStringContainsString($key, $releaseChecks);
        }

        $this->assertStringContainsString("'min_length' => env('PASSWORD_POLICY_MIN_LENGTH', 12)", $security);
        $this->assertStringContainsString("'max_length' => env('PASSWORD_POLICY_MAX_LENGTH', 72)", $security);
        $this->assertStringContainsString('PasswordPolicyRule', $policy);
        $this->assertStringContainsString('generateTemporaryPassword', $policy);
        $this->assertStringContainsString('generateTemporaryCredential', $policy);
        $this->assertStringContainsString('require_mixed_case', $rule);
        $this->assertStringContainsString('password_policy_security', $releaseGate);
    }

    public function testB2BWebStepUpRequiresCurrentPasswordInProductionDocs()
    {
        $adminConfig = file_get_contents(base_path('config/b2b_admin.php'));
        $guard = file_get_contents(base_path('app/B2B/Services/B2BWebStepUpGuard.php'));
        $controller = file_get_contents(base_path('app/Http/Controllers/Web/Backend/B2BStepUpController.php'));
        $view = file_get_contents(base_path('resources/views/backend/b2b/step-up.blade.php'));
        $releaseGate = file_get_contents(base_path('app/B2B/Services/B2BReleaseGate.php'));
        $envExample = file_get_contents(base_path('.env.example'));
        $releaseChecks = file_get_contents(base_path('docs/b2b/RELEASE_CHECKS.md'));

        $this->assertStringContainsString("'web_step_up_requires_password' => env('B2B_WEB_STEP_UP_REQUIRES_PASSWORD', true)", $adminConfig);
        $this->assertStringContainsString('B2B_WEB_STEP_UP_REQUIRES_PASSWORD=true', $envExample);
        $this->assertStringContainsString('B2B_WEB_STEP_UP_REQUIRES_PASSWORD=true', $releaseChecks);
        $this->assertStringContainsString('Hash::check', $guard);
        $this->assertStringContainsString('password_verified_at', $guard);
        $this->assertStringContainsString('current_password', $controller);
        $this->assertStringContainsString("'2fa'", $controller);
        $this->assertStringContainsString('current_password', $view);
        $this->assertStringContainsString('web_step_up_requires_password', $releaseGate);
    }

    public function testCredentialSessionRevocationPolicyAndDocsArePresent()
    {
        $session = file_get_contents(base_path('config/session.php'));
        $eventProvider = file_get_contents(base_path('app/Providers/EventServiceProvider.php'));
        $listener = file_get_contents(base_path('app/Listeners/Users/InvalidateSessionsAndTokens.php'));
        $migration = file_get_contents(base_path('database/migrations/2026_06_24_000009_create_sessions_runtime_table.php'));
        $apiTokensMigration = file_get_contents(base_path('database/migrations/2026_06_24_000010_create_api_tokens_runtime_table.php'));
        $releaseGate = file_get_contents(base_path('app/B2B/Services/B2BReleaseGate.php'));
        $envExample = file_get_contents(base_path('.env.example'));
        $releaseChecks = file_get_contents(base_path('docs/b2b/RELEASE_CHECKS.md'));

        $this->assertStringContainsString("env('SESSION_DRIVER', 'database')", $session);
        $this->assertStringContainsString('SESSION_DRIVER=database', $envExample);
        $this->assertStringContainsString('SESSION_DRIVER=database', $releaseChecks);
        $this->assertStringContainsString('UserCredentialsChanged::class', $eventProvider);
        $this->assertStringContainsString('InvalidateSessionsAndTokens::class', $eventProvider);
        $this->assertStringContainsString('invalidateAllSessionsForUser', $listener);
        $this->assertStringContainsString("Token::where('user_id'", $listener);
        $this->assertStringContainsString("Schema::hasTable('api_tokens')", $listener);
        $this->assertStringContainsString("Schema::create('sessions'", $migration);
        $this->assertStringContainsString("\$table->unsignedInteger('user_id')->nullable()->index()", $migration);
        $this->assertStringContainsString("Schema::create('api_tokens'", $apiTokensMigration);
        $this->assertStringContainsString("\$table->string('id', 80)->primary()", $apiTokensMigration);
        $this->assertStringContainsString("\$table->unsignedInteger('user_id')->index()", $apiTokensMigration);
        $this->assertStringContainsString("\$table->timestamp('expires_at')->nullable()->index()", $apiTokensMigration);
        $this->assertStringContainsString('credential_session_revocation', $releaseGate);
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

        $portalQuery = app(B2BOperatorPortalQuery::class);
        $portalReflection = new ReflectionClass($portalQuery);
        $normalize = $portalReflection->getMethod('decimalNormalize');
        $normalize->setAccessible(true);

        $this->assertSame('999999999999.99999999', $normalize->invoke($portalQuery, '999999999999.99999999'));
        $this->assertSame('0.30000000', $normalize->invoke($portalQuery, '0.30000000'));
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

    public function testB2BHmacDocumentationIncludesRunnableSigningExamples()
    {
        $hmacDocs = file_get_contents(base_path('docs/api/HMAC_AUTHENTICATION.md'));
        $apiDocs = file_get_contents(base_path('docs/b2b/API.md'));

        foreach ([
            '## PHP Example',
            "hash_hmac('sha256'",
            '## Node.js Example',
            'createHmac("sha256", secret)',
            'await fetch(`https://api.example.com${path}`',
            '## cURL Example',
            'openssl dgst -sha256 -hmac "$secret"',
            'curl --request "$method"',
        ] as $needle) {
            $this->assertStringContainsString($needle, $hmacDocs);
        }

        $this->assertStringContainsString('PHP, Node.js, and cURL signing examples', $apiDocs);
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
            '/portal/support',
            '/portal/support/cases/{transaction_uid}',
            '/portal/support/cases/{transaction_uid}/thread',
            '/portal/support/cases/{transaction_uid}/comments',
            '/portal/support/tickets',
            '/portal/support/tickets/{ticket_uid}',
            '/portal/support/tickets/{ticket_uid}/thread',
            '/portal/support/tickets/{ticket_uid}/comments',
            '/portal/support/tickets/{ticket_uid}/close',
            '/portal/docs',
            '/games',
            '/games/{game_uid}',
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

        foreach (['/portal', '/portal/overview'] as $portalPath) {
            $portalParameters = [];
            foreach ($openapi['paths'][$portalPath]['get']['parameters'] as $parameter) {
                if (isset($parameter['name'])) {
                    $portalParameters[] = $parameter['name'];
                }
            }
            foreach (['from', 'to', 'limit'] as $parameter) {
                $this->assertContains($parameter, $portalParameters);
            }
            $this->assertArrayHasKey('422', $openapi['paths'][$portalPath]['get']['responses']);
        }

        $this->assertSame(
            '#/components/schemas/PortalOverviewSuccess',
            $openapi['paths']['/portal/overview']['get']['responses']['200']['content']['application/json']['schema']['$ref']
        );
        $this->assertStringContainsString(
            'support-ticket message counts',
            $openapi['components']['pathItems']['PortalPage']['get']['description']
        );
        $portalTicketProperties = $openapi['components']['schemas']['PortalSupportTicketSummary']['properties'];
        foreach (['message_count', 'latest_message', 'detail_endpoint', 'thread_endpoint', 'last_message_at'] as $property) {
            $this->assertArrayHasKey($property, $portalTicketProperties);
        }
        $portalQuery = file_get_contents(base_path('app/B2B/Services/B2BOperatorPortalQuery.php'));
        foreach (['support_case_detail_template', 'support_case_thread_template', 'support_ticket_detail_template', 'support_ticket_thread_template', 'support_case_detail_endpoint', 'support_case_thread_endpoint', 'recent_cases', 'detail_endpoint', 'thread_endpoint'] as $needle) {
            $this->assertStringContainsString($needle, $portalQuery);
        }
        foreach ([
            base_path('resources/views/b2b/operator-portal/overview.blade.php'),
            base_path('resources/views/b2b/operator-portal/section.blade.php'),
            base_path('resources/views/b2b/operator-portal/thread.blade.php'),
        ] as $portalView) {
            $portalViewContents = file_get_contents($portalView);
            if (strpos($portalView, 'thread.blade.php') === false) {
                $this->assertStringContainsString('Detail Endpoint', $portalViewContents);
                $this->assertStringContainsString('Thread Page', $portalViewContents);
                $this->assertStringContainsString('support_case_detail_endpoint', $portalViewContents);
                $this->assertStringContainsString('support_case_thread_endpoint', $portalViewContents);
                $this->assertStringContainsString('detail_endpoint', $portalViewContents);
                $this->assertStringContainsString('thread_endpoint', $portalViewContents);
            } else {
                $this->assertStringContainsString("\$thread_type === 'case'", $portalViewContents);
                $this->assertStringContainsString('Case Summary', $portalViewContents);
                $this->assertStringContainsString('Ticket Summary', $portalViewContents);
                $this->assertStringContainsString('API Detail Endpoint', $portalViewContents);
            }
            if (strpos($portalView, 'section.blade.php') !== false) {
                $this->assertStringContainsString('Recent Cases', $portalViewContents);
                $this->assertStringContainsString('recent_cases', $portalViewContents);
            }
        }
        $latestMessageProperties = $openapi['components']['schemas']['PortalSupportTicketMessageSummary']['properties'];
        foreach (['actor', 'source', 'message', 'metadata', 'created_at'] as $property) {
            $this->assertArrayHasKey($property, $latestMessageProperties);
        }

        $this->assertArrayHasKey('422', $openapi['components']['pathItems']['PortalPage']['get']['responses']);
        $this->assertArrayHasKey('422', $openapi['paths']['/wallet/transactions/{transaction_uid}/status']['get']['responses']);
        $this->assertArrayHasKey('422', $openapi['paths']['/reports/transactions/{transaction_uid}']['get']['responses']);
        $this->assertArrayHasKey('422', $openapi['paths']['/reports/settlements/{settlement_uid}']['get']['responses']);
        $this->assertArrayHasKey('422', $openapi['paths']['/sessions/{session_uid}']['get']['responses']);
        $this->assertArrayHasKey('422', $openapi['paths']['/sessions/{session_uid}/close']['post']['responses']);
        $this->assertSame(191, $openapi['paths']['/portal/support/cases/{transaction_uid}']['get']['parameters'][0]['schema']['maxLength']);
        $this->assertSame(100, $openapi['paths']['/portal/support/cases/{transaction_uid}']['get']['parameters'][1]['schema']['maximum']);
        $this->assertSame(191, $openapi['paths']['/portal/support/cases/{transaction_uid}/thread']['get']['parameters'][0]['schema']['maxLength']);
        $this->assertSame(100, $openapi['paths']['/portal/support/cases/{transaction_uid}/thread']['get']['parameters'][1]['schema']['maximum']);
        $this->assertSame(
            '#/components/schemas/PortalSupportCaseDetailSuccess',
            $openapi['paths']['/portal/support/cases/{transaction_uid}']['get']['responses']['200']['content']['application/json']['schema']['$ref']
        );
        $caseCommentProperties = $openapi['components']['schemas']['PortalSupportCaseComment']['properties'];
        foreach (['actor', 'source', 'message', 'external_reference', 'created_at'] as $property) {
            $this->assertArrayHasKey($property, $caseCommentProperties);
        }
        $this->assertSame(191, $openapi['paths']['/portal/support/cases/{transaction_uid}/comments']['post']['parameters'][0]['schema']['maxLength']);
        $this->assertSame(80, $openapi['paths']['/portal/support/tickets/{ticket_uid}']['get']['parameters'][0]['schema']['maxLength']);
        $this->assertSame(100, $openapi['paths']['/portal/support/tickets/{ticket_uid}']['get']['parameters'][1]['schema']['maximum']);
        $this->assertSame(80, $openapi['paths']['/portal/support/tickets/{ticket_uid}/thread']['get']['parameters'][0]['schema']['maxLength']);
        $this->assertSame(100, $openapi['paths']['/portal/support/tickets/{ticket_uid}/thread']['get']['parameters'][1]['schema']['maximum']);
        $this->assertSame(
            '#/components/schemas/PortalSupportTicketThreadSuccess',
            $openapi['paths']['/portal/support/tickets/{ticket_uid}']['get']['responses']['200']['content']['application/json']['schema']['$ref']
        );
        $this->assertSame(80, $openapi['paths']['/portal/support/tickets/{ticket_uid}/comments']['post']['parameters'][0]['schema']['maxLength']);
        $this->assertSame(80, $openapi['paths']['/portal/support/tickets/{ticket_uid}/close']['post']['parameters'][0]['schema']['maxLength']);

        $gameListParameters = [];
        foreach ($openapi['paths']['/games']['get']['parameters'] as $parameter) {
            if (isset($parameter['name'])) {
                $gameListParameters[] = $parameter['name'];
            }
        }
        foreach (['limit', 'provider', 'category', 'search', 'currency', 'country', 'mode', 'sort'] as $parameter) {
            $this->assertContains($parameter, $gameListParameters);
        }

        $sessionListParameters = [];
        foreach ($openapi['paths']['/sessions']['get']['parameters'] as $parameter) {
            if (isset($parameter['name'])) {
                $sessionListParameters[] = $parameter['name'];
            }
        }
        foreach (['limit', 'status', 'player_id', 'game_id', 'sort'] as $parameter) {
            $this->assertContains($parameter, $sessionListParameters);
        }

        $transactionListParameters = [];
        foreach ($openapi['paths']['/reports/transactions']['get']['parameters'] as $parameter) {
            if (isset($parameter['name'])) {
                $transactionListParameters[] = $parameter['name'];
            }
        }
        foreach (['from', 'to', 'limit', 'status', 'type', 'player_id', 'game_id', 'round_id', 'currency', 'sort'] as $parameter) {
            $this->assertContains($parameter, $transactionListParameters);
        }

        $settlementListParameters = [];
        foreach ($openapi['paths']['/reports/settlements']['get']['parameters'] as $parameter) {
            if (isset($parameter['name'])) {
                $settlementListParameters[] = $parameter['name'];
            }
        }
        foreach (['from', 'to', 'limit', 'status', 'currency', 'sort'] as $parameter) {
            $this->assertContains($parameter, $settlementListParameters);
        }

        foreach (['/reports/summary', '/reports/ggr'] as $aggregatePath) {
            $aggregateParameters = [];
            foreach ($openapi['paths'][$aggregatePath]['get']['parameters'] as $parameter) {
                if (isset($parameter['name'])) {
                    $aggregateParameters[] = $parameter['name'];
                }
            }
            foreach (['from', 'to', 'status', 'type', 'player_id', 'game_id', 'round_id', 'currency'] as $parameter) {
                $this->assertContains($parameter, $aggregateParameters);
            }
        }

        $reconciliationParameters = [];
        foreach ($openapi['paths']['/reports/reconciliation']['get']['parameters'] as $parameter) {
            if (isset($parameter['name'])) {
                $reconciliationParameters[] = $parameter['name'];
            }
        }
        foreach (['from', 'to', 'limit', 'state', 'reason', 'priority', 'currency', 'game_id', 'round_id'] as $parameter) {
            $this->assertContains($parameter, $reconciliationParameters);
        }

        $attemptParameters = [];
        foreach ($openapi['paths']['/wallet/transactions/{transaction_uid}/attempts']['get']['parameters'] as $parameter) {
            if (isset($parameter['name'])) {
                $attemptParameters[] = $parameter['name'];
            } elseif (isset($parameter['$ref']) && $parameter['$ref'] === '#/components/parameters/TransactionUid') {
                $attemptParameters[] = 'transaction_uid';
            }
        }
        foreach (['transaction_uid', 'limit'] as $parameter) {
            $this->assertContains($parameter, $attemptParameters);
        }
        $this->assertArrayHasKey('422', $openapi['paths']['/wallet/transactions/{transaction_uid}/attempts']['get']['responses']);

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
            '/api/b2b/v1/portal/support?limit=10',
            '/api/b2b/v1/portal/support/cases/{{transactionId}}?limit=50',
            '/api/b2b/v1/portal/support/cases/{{transactionId}}/thread?limit=50',
            '/api/b2b/v1/portal/support/cases/{{transactionId}}/comments',
            '/api/b2b/v1/portal/support/tickets',
            '/api/b2b/v1/portal/support/tickets/{{supportTicketId}}?limit=50',
            '/api/b2b/v1/portal/support/tickets/{{supportTicketId}}/thread?limit=50',
            '/api/b2b/v1/portal/support/tickets/{{supportTicketId}}/comments',
            '/api/b2b/v1/portal/support/tickets/{{supportTicketId}}/close',
            '/api/b2b/v1/games?currency={{currency}}&country=BR&mode=real&limit=50&sort=title',
            '/api/b2b/v1/games/launch',
            '/api/b2b/v1/games/{{gameId}}',
            '/api/b2b/v1/sessions?limit=100&status=active&sort=-created_at',
            '/api/b2b/v1/sessions/{{sessionId}}/close',
            '/api/b2b/v1/wallet/bet',
            '/api/b2b/v1/wallet/transactions/{{transactionId}}/status',
            '/api/b2b/v1/wallet/transactions/{{transactionId}}/attempts?limit=100',
            '/api/b2b/v1/reports/settlements/export',
            '/api/b2b/v1/reports/summary?from=2026-06-01&to=2026-06-24&currency={{currency}}',
            '/api/b2b/v1/reports/ggr?from=2026-06-01&to=2026-06-24&currency={{currency}}',
            '/api/b2b/v1/reports/reconciliation?from=2026-06-01&to=2026-06-24&state=open&priority=high&currency={{currency}}&limit=20',
            '/api/b2b/v1/reports/transactions?limit=100&status=success&type=bet&currency={{currency}}&sort=-created_at',
            '/api/b2b/v1/reports/settlements?from=2026-06-01&to=2026-06-24&status=exported&currency={{currency}}&sort=-created_at&limit=100',
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
