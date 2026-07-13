<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\B2BApiTestHelpers;
use Tests\TestCase;

class B2BOperatorPortalTest extends TestCase
{
    use B2BApiTestHelpers;

    private $operatorA;
    private $operatorB;
    private $secretA = 'portal_secret_a_1234567890';
    private $secretB = 'portal_secret_b_1234567890';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->resetB2BTables();

        $this->operatorA = $this->createB2BOperator('op_portal_a', 'key_portal_a', $this->secretA, [
            'name' => 'Portal Operator A',
            'base_url' => 'https://operator-a.example/portal?token=base-url-secret',
            'wallet_callback_url' => 'https://wallet-a.example/callback?token=operator-callback-secret',
            'allowed_currencies' => ['USD', 'EUR'],
        ]);
        $this->operatorB = $this->createB2BOperator('op_portal_b', 'key_portal_b', $this->secretB, [
            'name' => 'Portal Operator B',
            'base_url' => 'https://operator-b.example',
            'wallet_callback_url' => 'https://wallet-b.example/callback',
        ]);

        $this->seedPortalData();
    }

    public function testPortalOverviewBootsForSignedOperator()
    {
        $response = $this->signedGet('op_portal_a', 'key_portal_a', $this->secretA, '/api/b2b/v1/portal/overview?limit=5', 'portal-overview-a');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.operator.id', 'op_portal_a')
            ->assertJsonPath('data.operator.base_url', 'https://operator-a.example/portal')
            ->assertJsonPath('data.operator.wallet_callback_url', 'https://wallet-a.example/callback')
            ->assertJsonPath('data.operator.wallet_callback_configured', true)
            ->assertJsonPath('data.api_key.key_id', 'key_portal_a')
            ->assertJsonPath('data.api_key.scopes_count', count(config('b2b.api_key_default_scopes', [])))
            ->assertJsonPath('data.summary.players', 1)
            ->assertJsonPath('data.summary.active_sessions', 1)
            ->assertJsonPath('data.summary.wallet_transactions', 2)
            ->assertJsonPath('data.summary.open_reconciliation_items', 1)
            ->assertJsonPath('data.wallet.by_status.success.count', 1)
            ->assertJsonPath('data.sessions.by_status.active.count', 1)
            ->assertJsonPath('data.credentials.by_status.active.count', 1)
            ->assertJsonPath('data.game_assignments.by_status.allowed.count', 1)
            ->assertJsonPath('data.game_assignments.recent_assignments.0.detail_endpoint', '/api/b2b/v1/portal/games/book_portal_a')
            ->assertJsonPath('data.settlements.by_status.submitted.count', 1)
            ->assertJsonPath('data.reconciliation.by_state.open.count', 1)
            ->assertJsonPath('data.callbacks.by_result.server_error.count', 1)
            ->assertJsonPath('data.callbacks.recent_logs.0.endpoint', 'https://wallet-a.example/callback')
            ->assertJsonPath('data.callbacks.recent_attempts.0.endpoint', 'https://wallet-a.example/callback')
            ->assertJsonPath('data.launch_diagnostics.by_status.failed.count', 1)
            ->assertJsonPath('data.launch_diagnostics.recent_requests.0.request_uid', 'diag_portal_a_launch')
            ->assertJsonPath('data.launch_diagnostics.recent_requests.0.detail_endpoint', '/api/b2b/v1/portal/diagnostics/diag_portal_a_launch')
            ->assertJsonPath('data.launch_diagnostics.recent_requests.0.session_detail_endpoint', '/api/b2b/v1/portal/sessions/sess_portal_a')
            ->assertJsonPath('data.launch_diagnostics.failed_sessions.0.session_uid', 'sess_portal_failed')
            ->assertJsonPath('data.provider_health.ok', true)
            ->assertJsonPath('data.provider_health.providers.0.provider', 'goldsvet_internal')
            ->assertJsonPath('data.provider_health.providers.0.status', 'ok')
            ->assertJsonPath('data.provider_health.providers.0.health.games_table_available', true)
            ->assertJsonPath('data.support.by_status.degraded.count', 1)
            ->assertJsonPath('data.support.recent_events.0.event_type', 'wallet_degraded')
            ->assertJsonPath('data.support.tickets_by_status.open.count', 1)
            ->assertJsonPath('data.support.recent_tickets.0.ticket_uid', 'sup_portal_a')
            ->assertJsonPath('data.support.recent_tickets.0.message_count', 2)
            ->assertJsonPath('data.support.recent_tickets.0.latest_message.actor', 'web:support_admin')
            ->assertJsonPath('data.support.recent_tickets.0.latest_message.source', 'web_backoffice')
            ->assertJsonPath('data.support.recent_tickets.0.detail_endpoint', '/api/b2b/v1/portal/support/tickets/sup_portal_a')
            ->assertJsonPath('data.support.recent_tickets.0.thread_endpoint', '/api/b2b/v1/portal/support/tickets/sup_portal_a/thread')
            ->assertJsonPath('data.support.recent_tickets.0.comment_endpoint', '/api/b2b/v1/portal/support/tickets/sup_portal_a/comments')
            ->assertJsonPath('data.support.recent_tickets.0.close_endpoint', '/api/b2b/v1/portal/support/tickets/sup_portal_a/close')
            ->assertJsonPath('data.audit.recent_events.0.event_type', 'api_key.used')
            ->assertJsonPath('data.audit.recent_events.0.actor', 'api:op_portal_a')
            ->assertJsonPath('data.audit.recent_events.0.subject_type', 'api_key')
            ->assertJsonPath('data.audit.recent_events.0.subject_id', 'key_portal_a')
            ->assertJsonPath('data.reconciliation.open_items.0.support_case_detail_endpoint', '/api/b2b/v1/portal/support/cases/tx_portal_a_win')
            ->assertJsonPath('data.reconciliation.open_items.0.support_case_thread_endpoint', '/api/b2b/v1/portal/support/cases/tx_portal_a_win/thread')
            ->assertJsonPath('data.reconciliation.open_items.0.support_case_comment_endpoint', '/api/b2b/v1/portal/support/cases/tx_portal_a_win/comments')
            ->assertJsonPath('data.reconciliation.recent_cases.0.transaction_uid', 'tx_portal_a_case_thread')
            ->assertJsonPath('data.reconciliation.recent_cases.0.state', 'resolved')
            ->assertJsonPath('data.reconciliation.recent_cases.0.support_case_detail_endpoint', '/api/b2b/v1/portal/support/cases/tx_portal_a_case_thread')
            ->assertJsonPath('data.reconciliation.recent_cases.0.support_case_thread_endpoint', '/api/b2b/v1/portal/support/cases/tx_portal_a_case_thread/thread')
            ->assertJsonPath('data.reconciliation.recent_cases.0.support_case_comment_endpoint', null)
            ->assertJsonPath('data.links.portal_game_detail_template', '/api/b2b/v1/portal/games/{game_uid}')
            ->assertJsonPath('data.links.portal_diagnostics', '/api/b2b/v1/portal/diagnostics')
            ->assertJsonPath('data.links.portal_diagnostic_detail_template', '/api/b2b/v1/portal/diagnostics/{request_uid}')
            ->assertJsonPath('data.links.portal_session_detail_template', '/api/b2b/v1/portal/sessions/{session_uid}')
            ->assertJsonPath('data.links.portal_transaction_detail_template', '/api/b2b/v1/portal/transactions/{transaction_uid}')
            ->assertJsonPath('data.links.portal_settlement_detail_template', '/api/b2b/v1/portal/settlements/{settlement_uid}')
            ->assertJsonPath('data.links.support_case_detail_template', '/api/b2b/v1/portal/support/cases/{transaction_uid}')
            ->assertJsonPath('data.links.support_case_thread_template', '/api/b2b/v1/portal/support/cases/{transaction_uid}/thread')
            ->assertJsonPath('data.links.support_ticket_detail_template', '/api/b2b/v1/portal/support/tickets/{ticket_uid}')
            ->assertJsonPath('data.links.support_ticket_thread_template', '/api/b2b/v1/portal/support/tickets/{ticket_uid}/thread')
            ->assertJsonPath('data.links.portal_logs', '/api/b2b/v1/portal/logs')
            ->assertJsonPath('data.links.portal_openapi_download', '/api/b2b/v1/portal/docs/openapi.json')
            ->assertJsonPath('data.links.portal_postman_download', '/api/b2b/v1/portal/docs/postman_collection.json')
            ->assertJsonPath('data.recent_sessions.0.session_uid', 'sess_portal_a')
            ->assertJsonPath('data.recent_sessions.0.detail_endpoint', '/api/b2b/v1/portal/sessions/sess_portal_a')
            ->assertJsonPath('data.settlements.recent_settlements.0.detail_endpoint', '/api/b2b/v1/portal/settlements/settlement_portal_a')
            ->assertJsonPath('data.recent_transactions.0.transaction_uid', 'tx_portal_a_win')
            ->assertJsonPath('data.recent_transactions.0.detail_endpoint', '/api/b2b/v1/portal/transactions/tx_portal_a_win');

        $this->assertStringContainsString('[REDACTED]', $response->json('data.support.recent_tickets.0.latest_message.message'));
        $closedTicket = collect($response->json('data.support.recent_tickets'))->firstWhere('ticket_uid', 'sup_portal_a_closed');
        $this->assertNotNull($closedTicket);
        $this->assertSame('/api/b2b/v1/portal/support/tickets/sup_portal_a_closed/reopen', $closedTicket['reopen_endpoint']);
        $this->assertStringContainsString('[REDACTED]', $response->json('data.audit.recent_events.0.reason'));
        $this->assertStringContainsString('[REDACTED]', $response->json('data.audit.recent_events.0.metadata_summary'));
        $this->assertContains('portal.read', $response->json('data.api_key.scopes'));
        $this->assertContains('support.write', $response->json('data.api_key.scopes'));
        $this->assertContains('portal.read', $response->json('data.credentials.recent_keys.0.scopes'));

        $legacyKey = collect($response->json('data.credentials.recent_keys'))->firstWhere('key_id', 'key_portal_legacy');
        $this->assertNotNull($legacyKey);
        $this->assertSame(['portal.read', 'reports.export'], $legacyKey['scopes']);
    }

    public function testPortalOverviewIsTenantScopedAndRedacted()
    {
        $response = $this->signedGet('op_portal_a', 'key_portal_a', $this->secretA, '/api/b2b/v1/portal/overview?limit=10', 'portal-redacted-a');

        $content = $response->getContent();

        $this->assertStringContainsString('tx_portal_a_bet', $content);
        $this->assertStringNotContainsString('tx_portal_b_bet', $content);
        $this->assertStringNotContainsString('sess_portal_b', $content);
        $this->assertStringNotContainsString('secret_encrypted', $content);
        $this->assertStringNotContainsString('raw_request', $content);
        $this->assertStringNotContainsString('raw_response', $content);
        $this->assertStringNotContainsString('super-secret-value', $content);
        $this->assertStringNotContainsString('callback-secret-value', $content);
        $this->assertStringNotContainsString('attempt-secret-value', $content);
        $this->assertStringNotContainsString('provider-request-secret', $content);
        $this->assertStringNotContainsString('provider-response-secret', $content);
        $this->assertStringNotContainsString('provider-error-secret', $content);
        $this->assertStringNotContainsString('provider-foreign-secret', $content);
        $this->assertStringNotContainsString('launch-failure-secret', $content);
        $this->assertStringNotContainsString('diag_portal_b_launch', $content);
        $this->assertStringNotContainsString('support-secret-value', $content);
        $this->assertStringNotContainsString('support-ticket-secret', $content);
        $this->assertStringNotContainsString('audit-secret-value', $content);
        $this->assertStringNotContainsString('audit-foreign-secret', $content);
        $this->assertStringNotContainsString('base-url-secret', $content);
        $this->assertStringNotContainsString('operator-callback-secret', $content);
        $this->assertStringNotContainsString('closed-ticket-secret', $content);
        $this->assertStringNotContainsString('sup_portal_b', $content);
        $this->assertStringNotContainsString('api:op_portal_b', $content);
        $this->assertStringNotContainsString('wallet-b.example', $content);
        $this->assertStringNotContainsString('wallet_restored', $content);
    }

    public function testPortalOverviewRequiresSignature()
    {
        $this->get('/api/b2b/v1/portal/overview')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'B2B_AUTH_FAILED');
    }

    public function testOperatorPortalValidatesLimitAndPeriodFilters()
    {
        $this->signedGet(
            'op_portal_a',
            'key_portal_a',
            $this->secretA,
            '/api/b2b/v1/portal/overview?limit=51',
            'portal-invalid-limit'
        )
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->signedGet(
            'op_portal_a',
            'key_portal_a',
            $this->secretA,
            '/api/b2b/v1/portal/overview?from=not-a-date',
            'portal-invalid-date'
        )
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $badPeriod = '/api/b2b/v1/portal/overview?from=' . now()->addDay()->toDateString() . '&to=' . now()->subDay()->toDateString();
        $this->signedGet(
            'op_portal_a',
            'key_portal_a',
            $this->secretA,
            $badPeriod,
            'portal-invalid-period'
        )
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->signedGet(
            'op_portal_a',
            'key_portal_a',
            $this->secretA,
            '/api/b2b/v1/portal/transactions?limit=0',
            'portal-section-invalid-limit'
        )
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function testSignedOperatorPortalPageIsTenantScopedAndRedacted()
    {
        $response = $this->signedGet('op_portal_a', 'key_portal_a', $this->secretA, '/api/b2b/v1/portal?limit=10', 'portal-page-a');

        $response->assertStatus(200);

        $content = $response->getContent();

        $this->assertStringContainsString('<html', $content);
        $this->assertStringContainsString('Portal Operator A', $content);
        $this->assertStringContainsString('tx_portal_a_bet', $content);
        $this->assertStringContainsString('/api/b2b/v1/portal/games/book_portal_a', $content);
        $this->assertStringContainsString('/api/b2b/v1/portal/diagnostics/diag_portal_a_launch', $content);
        $this->assertStringContainsString('Provider Health', $content);
        $this->assertStringContainsString('goldsvet_internal', $content);
        $this->assertStringContainsString('supported 13', $content);
        $this->assertStringContainsString('settlement_portal_a', $content);
        $this->assertStringContainsString('/api/b2b/v1/portal/settlements/settlement_portal_a', $content);
        $this->assertStringContainsString('sup_portal_a', $content);
        $this->assertStringContainsString('api_key.used', $content);
        $this->assertStringContainsString('/api/b2b/v1/portal/support/cases/tx_portal_a_win', $content);
        $this->assertStringContainsString('/api/b2b/v1/portal/support/cases/tx_portal_a_win/thread', $content);
        $this->assertStringContainsString('/api/b2b/v1/portal/support/cases/tx_portal_a_win/comments', $content);
        $this->assertStringContainsString('/api/b2b/v1/portal/support/tickets/sup_portal_a', $content);
        $this->assertStringContainsString('/api/b2b/v1/portal/support/tickets/sup_portal_a/thread', $content);
        $this->assertStringContainsString('/api/b2b/v1/portal/support/tickets/sup_portal_a/comments', $content);
        $this->assertStringContainsString('/api/b2b/v1/portal/support/tickets/sup_portal_a/close', $content);
        $this->assertStringContainsString('/api/b2b/v1/portal/support/tickets/sup_portal_a_closed/reopen', $content);
        $this->assertStringContainsString('web_backoffice', $content);
        $this->assertStringContainsString('portal.read', $content);
        $this->assertStringNotContainsString('Portal Operator B', $content);
        $this->assertStringNotContainsString('tx_portal_b_bet', $content);
        $this->assertStringNotContainsString('sess_portal_b', $content);
        $this->assertStringNotContainsString('secret_encrypted', $content);
        $this->assertStringNotContainsString('super-secret-value', $content);
        $this->assertStringNotContainsString('provider-request-secret', $content);
        $this->assertStringNotContainsString('provider-response-secret', $content);
        $this->assertStringNotContainsString('provider-error-secret', $content);
        $this->assertStringNotContainsString('provider-foreign-secret', $content);
        $this->assertStringNotContainsString('launch-failure-secret', $content);
        $this->assertStringNotContainsString('diag_portal_b_launch', $content);
        $this->assertStringNotContainsString('audit-secret-value', $content);
        $this->assertStringNotContainsString('audit-foreign-secret', $content);
        $this->assertStringNotContainsString('base-url-secret', $content);
        $this->assertStringNotContainsString('operator-callback-secret', $content);
    }

    public function testSignedOperatorPortalWorkflowPagesAreTenantScopedAndRedacted()
    {
        foreach ([
            'credentials' => 'key_portal_a',
            'games' => 'book_portal_a',
            'sessions' => 'sess_portal_a',
            'transactions' => 'tx_portal_a_bet',
            'settlements' => 'settlement_portal_a',
            'cases' => 'tx_portal_a_win',
            'callbacks' => 'server_error',
            'diagnostics' => 'diag_portal_a_launch',
            'reports' => '/api/b2b/v1/reports/ggr',
            'support' => 'wallet_degraded',
            'logs' => 'api_key.used',
            'docs' => '/api/b2b/v1/reports/transactions',
        ] as $section => $expected) {
            $response = $this->signedGet('op_portal_a', 'key_portal_a', $this->secretA, '/api/b2b/v1/portal/' . $section . '?limit=10', 'portal-section-' . $section);
            $response->assertStatus(200);

            $content = $response->getContent();
            $this->assertStringContainsString('Portal Operator A', $content);
            $this->assertStringContainsString($expected, $content);
            if ($section === 'credentials') {
                $this->assertStringContainsString('portal.read', $content);
            }
            if ($section === 'diagnostics') {
                $this->assertStringContainsString('Provider Health', $content);
                $this->assertStringContainsString('goldsvet_internal', $content);
            }
            if ($section === 'games') {
                $this->assertStringContainsString('/api/b2b/v1/portal/games/book_portal_a', $content);
            }
            if ($section === 'sessions') {
                $this->assertStringContainsString('/api/b2b/v1/portal/sessions/sess_portal_a', $content);
            }
            if ($section === 'transactions') {
                $this->assertStringContainsString('/api/b2b/v1/portal/transactions/tx_portal_a_bet', $content);
            }
            if ($section === 'settlements') {
                $this->assertStringContainsString('/api/b2b/v1/portal/settlements/settlement_portal_a', $content);
            }
            if ($section === 'support') {
                $this->assertStringContainsString('sup_portal_a', $content);
                $this->assertStringContainsString('web_backoffice', $content);
                $this->assertStringContainsString('[REDACTED]', $content);
                $this->assertStringContainsString('/api/b2b/v1/portal/support/tickets/sup_portal_a', $content);
                $this->assertStringContainsString('/api/b2b/v1/portal/support/tickets/sup_portal_a/thread', $content);
                $this->assertStringContainsString('/api/b2b/v1/portal/support/tickets/sup_portal_a/comments', $content);
                $this->assertStringContainsString('/api/b2b/v1/portal/support/tickets/sup_portal_a/close', $content);
                $this->assertStringContainsString('/api/b2b/v1/portal/support/tickets/sup_portal_a_closed/reopen', $content);
                $this->assertStringContainsString('/api/b2b/v1/portal/support/cases/tx_portal_a_win', $content);
                $this->assertStringContainsString('/api/b2b/v1/portal/support/cases/tx_portal_a_win/thread', $content);
                $this->assertStringContainsString('/api/b2b/v1/portal/support/cases/tx_portal_a_win/comments', $content);
                $this->assertStringContainsString('tx_portal_a_case_thread', $content);
                $this->assertStringContainsString('/api/b2b/v1/portal/support/cases/tx_portal_a_case_thread', $content);
                $this->assertStringContainsString('/api/b2b/v1/portal/support/cases/tx_portal_a_case_thread/thread', $content);
            }
            if ($section === 'cases') {
                $this->assertStringContainsString('/api/b2b/v1/portal/support/cases/tx_portal_a_win', $content);
                $this->assertStringContainsString('/api/b2b/v1/portal/support/cases/tx_portal_a_win/thread', $content);
                $this->assertStringContainsString('/api/b2b/v1/portal/support/cases/tx_portal_a_win/comments', $content);
                $this->assertStringContainsString('tx_portal_a_case_thread', $content);
                $this->assertStringContainsString('/api/b2b/v1/portal/support/cases/tx_portal_a_case_thread', $content);
                $this->assertStringContainsString('/api/b2b/v1/portal/support/cases/tx_portal_a_case_thread/thread', $content);
            }
            if ($section === 'logs') {
                $this->assertStringContainsString('API Logs', $content);
                $this->assertStringContainsString('api:op_portal_a', $content);
            }
            if ($section === 'diagnostics') {
                $this->assertStringContainsString('/api/b2b/v1/portal/diagnostics/diag_portal_a_launch', $content);
                $this->assertStringContainsString('/api/b2b/v1/portal/sessions/sess_portal_a', $content);
                $this->assertStringContainsString('Failed Launch Sessions', $content);
                $this->assertStringContainsString('[REDACTED]', $content);
            }
            if ($section === 'docs') {
                $this->assertStringContainsString('Downloadable Artifacts', $content);
                $this->assertStringContainsString('OpenAPI JSON', $content);
                $this->assertStringContainsString('Postman Collection', $content);
                $this->assertStringContainsString('/api/b2b/v1/portal/docs/openapi.json', $content);
                $this->assertStringContainsString('/api/b2b/v1/portal/docs/postman_collection.json', $content);
            }
            $this->assertStringNotContainsString('Portal Operator B', $content);
            $this->assertStringNotContainsString('tx_portal_b_bet', $content);
            $this->assertStringNotContainsString('sess_portal_b', $content);
            $this->assertStringNotContainsString('secret_encrypted', $content);
            $this->assertStringNotContainsString('raw_request', $content);
            $this->assertStringNotContainsString('raw_response', $content);
            $this->assertStringNotContainsString('super-secret-value', $content);
            $this->assertStringNotContainsString('callback-secret-value', $content);
            $this->assertStringNotContainsString('attempt-secret-value', $content);
            $this->assertStringNotContainsString('provider-request-secret', $content);
            $this->assertStringNotContainsString('provider-response-secret', $content);
            $this->assertStringNotContainsString('provider-error-secret', $content);
            $this->assertStringNotContainsString('provider-foreign-secret', $content);
            $this->assertStringNotContainsString('launch-failure-secret', $content);
            $this->assertStringNotContainsString('diag_portal_b_launch', $content);
            $this->assertStringNotContainsString('support-secret-value', $content);
            $this->assertStringNotContainsString('support-ticket-secret', $content);
            $this->assertStringNotContainsString('audit-secret-value', $content);
            $this->assertStringNotContainsString('audit-foreign-secret', $content);
            $this->assertStringNotContainsString('case-thread-secret', $content);
            $this->assertStringNotContainsString('case-thread-latest-secret', $content);
            $this->assertStringNotContainsString('case-internal-secret', $content);
            $this->assertStringNotContainsString('base-url-secret', $content);
            $this->assertStringNotContainsString('operator-callback-secret', $content);
            $this->assertStringNotContainsString('sup_portal_b', $content);
            $this->assertStringNotContainsString('api:op_portal_b', $content);
            $this->assertStringNotContainsString('wallet-b.example', $content);
            $this->assertStringNotContainsString('wallet_restored', $content);
        }
    }

    public function testSignedOperatorPortalDocsDownloadsStaticArtifacts()
    {
        $openApi = $this->signedGet(
            'op_portal_a',
            'key_portal_a',
            $this->secretA,
            '/api/b2b/v1/portal/docs/openapi.json',
            'portal-docs-openapi'
        );

        $openApi->assertStatus(200)
            ->assertHeader('Content-Disposition', 'attachment; filename="bbb-b2b-openapi.json"');
        $this->assertStringStartsWith('application/json', $openApi->headers->get('Content-Type'));

        $openApiPayload = json_decode($openApi->getContent(), true);
        $this->assertSame(JSON_ERROR_NONE, json_last_error(), json_last_error_msg());
        $this->assertSame('3.1.0', $openApiPayload['openapi']);
        $this->assertArrayHasKey('/portal/docs/openapi.json', $openApiPayload['paths']);
        $this->assertArrayHasKey('/portal/docs/postman_collection.json', $openApiPayload['paths']);
        $this->assertStringNotContainsString($this->secretA, $openApi->getContent());
        $this->assertStringNotContainsString($this->secretB, $openApi->getContent());
        $this->assertStringNotContainsString('base-url-secret', $openApi->getContent());

        $postman = $this->signedGet(
            'op_portal_a',
            'key_portal_a',
            $this->secretA,
            '/api/b2b/v1/portal/docs/postman_collection.json',
            'portal-docs-postman'
        );

        $postman->assertStatus(200)
            ->assertHeader('Content-Disposition', 'attachment; filename="bbb-b2b-postman_collection.json"');
        $this->assertStringStartsWith('application/json', $postman->headers->get('Content-Type'));

        $postmanPayload = json_decode($postman->getContent(), true);
        $this->assertSame(JSON_ERROR_NONE, json_last_error(), json_last_error_msg());
        $this->assertSame('BBB B2B Aggregator API', $postmanPayload['info']['name']);
        $this->assertIsArray($postmanPayload['item']);
        $this->assertStringContainsString('/api/b2b/v1/portal/docs/openapi.json', $postman->getContent());
        $this->assertStringContainsString('/api/b2b/v1/portal/docs/postman_collection.json', $postman->getContent());
        $this->assertStringNotContainsString($this->secretA, $postman->getContent());
        $this->assertStringNotContainsString($this->secretB, $postman->getContent());
        $this->assertStringNotContainsString('base-url-secret', $postman->getContent());
    }

    public function testOperatorPortalWorkflowPagesRequireSignature()
    {
        $this->get('/api/b2b/v1/portal/transactions')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'B2B_AUTH_FAILED');

        $this->get('/api/b2b/v1/portal/diagnostics')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'B2B_AUTH_FAILED');

        $this->get('/api/b2b/v1/portal/docs/openapi.json')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'B2B_AUTH_FAILED');

        $this->get('/api/b2b/v1/portal/docs/postman_collection.json')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'B2B_AUTH_FAILED');
    }

    public function testOperatorPortalDiagnosticDetailPageIsScopedRedactedAndBounded()
    {
        $response = $this->signedGet(
            'op_portal_a',
            'key_portal_a',
            $this->secretA,
            '/api/b2b/v1/portal/diagnostics/diag_portal_a_launch',
            'portal-diagnostic-detail-a'
        );

        $response->assertStatus(200);

        $content = $response->getContent();
        $this->assertStringContainsString('<html', $content);
        $this->assertStringContainsString('Launch Diagnostic Detail', $content);
        $this->assertStringContainsString('Provider Request Summary', $content);
        $this->assertStringContainsString('Request Summary', $content);
        $this->assertStringContainsString('Response Summary', $content);
        $this->assertStringContainsString('diag_portal_a_launch', $content);
        $this->assertStringContainsString('goldsvet_internal', $content);
        $this->assertStringContainsString('launch', $content);
        $this->assertStringContainsString('failed', $content);
        $this->assertStringContainsString('book_portal_a', $content);
        $this->assertStringContainsString('sess_portal_a', $content);
        $this->assertStringContainsString('/api/b2b/v1/portal/sessions/sess_portal_a', $content);
        $this->assertStringContainsString('[REDACTED]', $content);
        $this->assertStringNotContainsString('diag_portal_b_launch', $content);
        $this->assertStringNotContainsString('provider-request-secret', $content);
        $this->assertStringNotContainsString('provider-response-secret', $content);
        $this->assertStringNotContainsString('provider-error-secret', $content);
        $this->assertStringNotContainsString('provider-foreign-secret', $content);
        $this->assertStringNotContainsString('request_payload', $content);
        $this->assertStringNotContainsString('response_payload', $content);
        $this->assertStringNotContainsString('raw_request', $content);
        $this->assertStringNotContainsString('raw_response', $content);
        $this->assertStringNotContainsString('launch_url', $content);
        $this->assertStringNotContainsString('legacy_launch_token', $content);
        $this->assertStringNotContainsString('token_hash', $content);

        $this->signedGet(
            'op_portal_b',
            'key_portal_b',
            $this->secretB,
            '/api/b2b/v1/portal/diagnostics/diag_portal_a_launch',
            'portal-diagnostic-detail-foreign'
        )
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'PROVIDER_REQUEST_NOT_FOUND');

        $longRequestUid = str_repeat('x', 192);
        $this->signedGet(
            'op_portal_a',
            'key_portal_a',
            $this->secretA,
            '/api/b2b/v1/portal/diagnostics/' . $longRequestUid,
            'portal-diagnostic-detail-invalid-uid'
        )
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->get('/api/b2b/v1/portal/diagnostics/diag_portal_a_launch')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'B2B_AUTH_FAILED');
    }

    public function testOperatorPortalGameDetailPageIsScopedRedactedAndBounded()
    {
        $response = $this->signedGet(
            'op_portal_a',
            'key_portal_a',
            $this->secretA,
            '/api/b2b/v1/portal/games/book_portal_a?limit=1',
            'portal-game-detail-a'
        );

        $response->assertStatus(200);

        $content = $response->getContent();
        $this->assertStringContainsString('<html', $content);
        $this->assertStringContainsString('Game Detail', $content);
        $this->assertStringContainsString('Game Summary', $content);
        $this->assertStringContainsString('Assignment', $content);
        $this->assertStringContainsString('Availability', $content);
        $this->assertStringContainsString('Successful Amounts', $content);
        $this->assertStringContainsString('Recent Sessions', $content);
        $this->assertStringContainsString('Recent Transactions', $content);
        $this->assertStringContainsString('book_portal_a', $content);
        $this->assertStringContainsString('Portal Book A', $content);
        $this->assertStringContainsString('Portal Operator A', $content);
        $this->assertStringContainsString('/api/b2b/v1/games/book_portal_a', $content);
        $this->assertStringContainsString('/api/b2b/v1/portal/sessions/sess_portal_a', $content);
        $this->assertStringContainsString('/api/b2b/v1/portal/transactions/tx_portal_a_win', $content);
        $this->assertStringContainsString('https://cdn.example/games/book_portal_a.png', $content);
        $this->assertStringContainsString('[REDACTED]', $content);
        $this->assertStringNotContainsString('book_portal_b', $content);
        $this->assertStringNotContainsString('tx_portal_b_bet', $content);
        $this->assertStringNotContainsString('sess_portal_b', $content);
        $this->assertStringNotContainsString('raw_request', $content);
        $this->assertStringNotContainsString('raw_response', $content);
        $this->assertStringNotContainsString('request_body', $content);
        $this->assertStringNotContainsString('response_body', $content);
        $this->assertStringNotContainsString('launch_url', $content);
        $this->assertStringNotContainsString('legacy_launch_token', $content);
        $this->assertStringNotContainsString('token_hash', $content);
        $this->assertStringNotContainsString('game-catalog-secret', $content);
        $this->assertStringNotContainsString('game-assignment-secret', $content);
        $this->assertStringNotContainsString('game-thumbnail-secret', $content);
        $this->assertStringNotContainsString('super-secret-value', $content);

        $this->signedGet(
            'op_portal_b',
            'key_portal_b',
            $this->secretB,
            '/api/b2b/v1/portal/games/book_portal_a',
            'portal-game-detail-foreign'
        )
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'GAME_NOT_AVAILABLE');

        $longGameUid = str_repeat('x', 192);
        $this->signedGet(
            'op_portal_a',
            'key_portal_a',
            $this->secretA,
            '/api/b2b/v1/portal/games/' . $longGameUid,
            'portal-game-detail-invalid-uid'
        )
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->signedGet(
            'op_portal_a',
            'key_portal_a',
            $this->secretA,
            '/api/b2b/v1/portal/games/book_portal_a?limit=51',
            'portal-game-detail-invalid-limit'
        )
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->get('/api/b2b/v1/portal/games/book_portal_a')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'B2B_AUTH_FAILED');
    }

    public function testOperatorPortalSessionDetailPageIsScopedRedactedAndBounded()
    {
        $response = $this->signedGet(
            'op_portal_a',
            'key_portal_a',
            $this->secretA,
            '/api/b2b/v1/portal/sessions/sess_portal_a?limit=1',
            'portal-session-detail-a'
        );

        $response->assertStatus(200);

        $content = $response->getContent();
        $this->assertStringContainsString('<html', $content);
        $this->assertStringContainsString('Session Detail', $content);
        $this->assertStringContainsString('Session Summary', $content);
        $this->assertStringContainsString('Session Transactions', $content);
        $this->assertStringContainsString('sess_portal_a', $content);
        $this->assertStringContainsString('player_portal_a', $content);
        $this->assertStringContainsString('tx_portal_a_win', $content);
        $this->assertStringContainsString('/api/b2b/v1/sessions/sess_portal_a', $content);
        $this->assertStringContainsString('/api/b2b/v1/portal/transactions/tx_portal_a_win', $content);
        $this->assertStringNotContainsString('raw_request', $content);
        $this->assertStringNotContainsString('raw_response', $content);
        $this->assertStringNotContainsString('request_body', $content);
        $this->assertStringNotContainsString('response_body', $content);
        $this->assertStringNotContainsString('launch_url', $content);
        $this->assertStringNotContainsString('legacy_launch_token', $content);
        $this->assertStringNotContainsString('token_hash', $content);
        $this->assertStringNotContainsString('super-secret-value', $content);
        $this->assertStringNotContainsString('callback-secret-value', $content);
        $this->assertStringNotContainsString('tx_portal_b_bet', $content);
        $this->assertStringNotContainsString('sess_portal_b', $content);

        $this->signedGet(
            'op_portal_b',
            'key_portal_b',
            $this->secretB,
            '/api/b2b/v1/portal/sessions/sess_portal_a',
            'portal-session-detail-foreign'
        )
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'SESSION_NOT_FOUND');

        $longSessionUid = str_repeat('x', 192);
        $this->signedGet(
            'op_portal_a',
            'key_portal_a',
            $this->secretA,
            '/api/b2b/v1/portal/sessions/' . $longSessionUid,
            'portal-session-detail-invalid-uid'
        )
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->signedGet(
            'op_portal_a',
            'key_portal_a',
            $this->secretA,
            '/api/b2b/v1/portal/sessions/sess_portal_a?limit=51',
            'portal-session-detail-invalid-limit'
        )
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->get('/api/b2b/v1/portal/sessions/sess_portal_a')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'B2B_AUTH_FAILED');
    }

    public function testOperatorPortalTransactionDetailPageIsScopedRedactedAndBounded()
    {
        $response = $this->signedGet(
            'op_portal_a',
            'key_portal_a',
            $this->secretA,
            '/api/b2b/v1/portal/transactions/tx_portal_a_bet?limit=1',
            'portal-transaction-detail-a'
        );

        $response->assertStatus(200);

        $content = $response->getContent();
        $this->assertStringContainsString('<html', $content);
        $this->assertStringContainsString('Transaction Detail', $content);
        $this->assertStringContainsString('Transaction Summary', $content);
        $this->assertStringContainsString('Callback Attempts', $content);
        $this->assertStringContainsString('Callback Logs', $content);
        $this->assertStringContainsString('tx_portal_a_bet', $content);
        $this->assertStringContainsString('operator_tx_portal_a_bet', $content);
        $this->assertStringContainsString('/api/b2b/v1/reports/transactions/tx_portal_a_bet', $content);
        $this->assertStringContainsString('https://wallet-a.example/callback', $content);
        $this->assertStringContainsString('timeout', $content);
        $this->assertStringContainsString('server_error', $content);
        $this->assertStringContainsString('[REDACTED]', $content);
        $this->assertStringNotContainsString('raw_request', $content);
        $this->assertStringNotContainsString('raw_response', $content);
        $this->assertStringNotContainsString('request_body', $content);
        $this->assertStringNotContainsString('response_body', $content);
        $this->assertStringNotContainsString('super-secret-value', $content);
        $this->assertStringNotContainsString('callback-secret-value', $content);
        $this->assertStringNotContainsString('attempt-secret-value', $content);
        $this->assertStringNotContainsString('tx_portal_b_bet', $content);
        $this->assertStringNotContainsString('wallet-b.example', $content);

        $this->signedGet(
            'op_portal_b',
            'key_portal_b',
            $this->secretB,
            '/api/b2b/v1/portal/transactions/tx_portal_a_bet',
            'portal-transaction-detail-foreign'
        )
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'TRANSACTION_NOT_FOUND');

        $longTransactionUid = str_repeat('x', 192);
        $this->signedGet(
            'op_portal_a',
            'key_portal_a',
            $this->secretA,
            '/api/b2b/v1/portal/transactions/' . $longTransactionUid,
            'portal-transaction-detail-invalid-uid'
        )
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->signedGet(
            'op_portal_a',
            'key_portal_a',
            $this->secretA,
            '/api/b2b/v1/portal/transactions/tx_portal_a_bet?limit=51',
            'portal-transaction-detail-invalid-limit'
        )
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->get('/api/b2b/v1/portal/transactions/tx_portal_a_bet')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'B2B_AUTH_FAILED');
    }

    public function testOperatorPortalSettlementDetailPageIsScopedRedactedAndBounded()
    {
        $response = $this->signedGet(
            'op_portal_a',
            'key_portal_a',
            $this->secretA,
            '/api/b2b/v1/portal/settlements/settlement_portal_a',
            'portal-settlement-detail-a'
        );

        $response->assertStatus(200);

        $content = $response->getContent();
        $this->assertStringContainsString('<html', $content);
        $this->assertStringContainsString('Settlement Detail', $content);
        $this->assertStringContainsString('Settlement Summary', $content);
        $this->assertStringContainsString('Settlement Totals', $content);
        $this->assertStringContainsString('Transaction Breakdown', $content);
        $this->assertStringContainsString('Approval Trail', $content);
        $this->assertStringContainsString('Export Metadata', $content);
        $this->assertStringContainsString('settlement_portal_a', $content);
        $this->assertStringContainsString('6.00000000', $content);
        $this->assertStringContainsString('finance_user', $content);
        $this->assertStringContainsString('settlement-export-hash-a', $content);
        $this->assertStringContainsString('/api/b2b/v1/reports/settlements/settlement_portal_a', $content);
        $this->assertStringContainsString('[REDACTED]', $content);
        $this->assertStringNotContainsString('raw_request', $content);
        $this->assertStringNotContainsString('raw_response', $content);
        $this->assertStringNotContainsString('request_body', $content);
        $this->assertStringNotContainsString('response_body', $content);
        $this->assertStringNotContainsString('export_content', $content);
        $this->assertStringNotContainsString('settlement-secret-value', $content);
        $this->assertStringNotContainsString('settlement_portal_b', $content);

        $this->signedGet(
            'op_portal_b',
            'key_portal_b',
            $this->secretB,
            '/api/b2b/v1/portal/settlements/settlement_portal_a',
            'portal-settlement-detail-foreign'
        )
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'SETTLEMENT_NOT_FOUND');

        $longSettlementUid = str_repeat('x', 81);
        $this->signedGet(
            'op_portal_a',
            'key_portal_a',
            $this->secretA,
            '/api/b2b/v1/portal/settlements/' . $longSettlementUid,
            'portal-settlement-detail-invalid-uid'
        )
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->get('/api/b2b/v1/portal/settlements/settlement_portal_a')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'B2B_AUTH_FAILED');
    }

    public function testOperatorPortalSupportCaseCommentIsScopedRedactedAndAudited()
    {
        $body = json_encode([
            'message' => 'Operator confirms provider case token=operator-comment-secret needs follow-up.',
            'external_reference' => 'OP-CASE-123',
        ]);

        $response = $this->signedPost(
            'op_portal_a',
            'key_portal_a',
            $this->secretA,
            '/api/b2b/v1/portal/support/cases/tx_portal_a_win/comments',
            $body,
            'portal-case-comment-a'
        );

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.transaction_uid', 'tx_portal_a_win')
            ->assertJsonPath('data.comment_count', 1);

        $context = json_decode(DB::table('b2b_wallet_reconciliation_items')
            ->where('operator_id', $this->operatorA->id)
            ->where('transaction_uid', 'tx_portal_a_win')
            ->value('context'), true);

        $this->assertSame('[REDACTED]', $this->commentTokenValue($context['operator_comments'][0]['message']));
        $this->assertSame('OP-CASE-123', $context['operator_comments'][0]['external_reference']);
        $this->assertSame('operator:op_portal_a', $context['operator_comments'][0]['actor']);
        $this->assertSame(1, $context['operator_follow_up']['comment_count']);

        $event = DB::table('b2b_operator_audit_events')
            ->where('operator_id', $this->operatorA->id)
            ->where('event_type', 'case.operator_commented')
            ->where('subject_type', 'reconciliation_item')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame('operator:op_portal_a', $event->actor);
        $this->assertStringContainsString('[REDACTED]', $event->reason);
        $this->assertStringNotContainsString('operator-comment-secret', $event->reason);

        $metadata = json_decode($event->metadata, true);
        $this->assertSame('tx_portal_a_win', $metadata['transaction_uid']);
        $this->assertSame('operator_portal', $metadata['source']);
        $this->assertSame('OP-CASE-123', $metadata['external_reference']);

        $this->signedPost(
            'op_portal_a',
            'key_portal_a',
            $this->secretA,
            '/api/b2b/v1/portal/support/cases/tx_portal_b_bet/comments',
            json_encode(['message' => 'Trying to touch another operator case.']),
            'portal-case-comment-foreign'
        )
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'CASE_NOT_FOUND');

        $longTransactionUid = str_repeat('x', 192);
        $this->signedPost(
            'op_portal_a',
            'key_portal_a',
            $this->secretA,
            '/api/b2b/v1/portal/support/cases/' . $longTransactionUid . '/comments',
            json_encode(['message' => 'Overlong case UID should fail validation.']),
            'portal-case-comment-invalid-uid'
        )
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function testOperatorPortalSupportCaseDetailIsScopedRedactedAndBounded()
    {
        $response = $this->signedGet(
            'op_portal_a',
            'key_portal_a',
            $this->secretA,
            '/api/b2b/v1/portal/support/cases/tx_portal_a_case_thread?limit=1',
            'portal-case-detail-a'
        );

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.transaction_uid', 'tx_portal_a_case_thread')
            ->assertJsonPath('data.state', 'resolved')
            ->assertJsonPath('data.comment_count', 2)
            ->assertJsonPath('data.detail_endpoint', '/api/b2b/v1/portal/support/cases/tx_portal_a_case_thread')
            ->assertJsonPath('data.thread_endpoint', '/api/b2b/v1/portal/support/cases/tx_portal_a_case_thread/thread')
            ->assertJsonPath('data.comment_endpoint', null)
            ->assertJsonPath('data.comments.0.actor', 'operator:op_portal_a')
            ->assertJsonPath('data.comments.0.source', 'operator_portal')
            ->assertJsonPath('data.comments.0.external_reference', 'OP-CASE-A1')
            ->assertJsonPath('data.latest_comment.actor', 'operator:op_portal_a')
            ->assertJsonPath('data.latest_comment.external_reference', 'OP-CASE-A2');

        $this->assertCount(1, $response->json('data.comments'));
        $content = $response->getContent();
        $this->assertStringContainsString('[REDACTED]', $content);
        $this->assertStringNotContainsString('case-thread-secret', $content);
        $this->assertStringNotContainsString('case-thread-latest-secret', $content);
        $this->assertStringNotContainsString('case-internal-secret', $content);
        $this->assertStringNotContainsString('tx_portal_b_bet', $content);

        $open = $this->signedGet(
            'op_portal_a',
            'key_portal_a',
            $this->secretA,
            '/api/b2b/v1/portal/support/cases/tx_portal_a_win?limit=1',
            'portal-case-detail-open-a'
        );

        $open->assertStatus(200)
            ->assertJsonPath('data.transaction_uid', 'tx_portal_a_win')
            ->assertJsonPath('data.state', 'open')
            ->assertJsonPath('data.comment_endpoint', '/api/b2b/v1/portal/support/cases/tx_portal_a_win/comments');

        $this->signedGet(
            'op_portal_b',
            'key_portal_b',
            $this->secretB,
            '/api/b2b/v1/portal/support/cases/tx_portal_a_case_thread',
            'portal-case-detail-foreign'
        )
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'CASE_NOT_FOUND');

        $longTransactionUid = str_repeat('x', 192);
        $this->signedGet(
            'op_portal_a',
            'key_portal_a',
            $this->secretA,
            '/api/b2b/v1/portal/support/cases/' . $longTransactionUid,
            'portal-case-detail-invalid-uid'
        )
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->signedGet(
            'op_portal_a',
            'key_portal_a',
            $this->secretA,
            '/api/b2b/v1/portal/support/cases/tx_portal_a_case_thread?limit=101',
            'portal-case-detail-invalid-limit'
        )
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function testOperatorPortalSupportCaseCommentsRequireSignature()
    {
        $this->get('/api/b2b/v1/portal/support/cases/tx_portal_a_case_thread')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'B2B_AUTH_FAILED');

        $this->get('/api/b2b/v1/portal/support/cases/tx_portal_a_case_thread/thread')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'B2B_AUTH_FAILED');

        $this->postJson('/api/b2b/v1/portal/support/cases/tx_portal_a_win/comments', [
            'message' => 'Unsigned support update.',
        ])
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'B2B_AUTH_FAILED');
    }

    public function testOperatorPortalSupportTicketLifecycleIsScopedRedactedAndAudited()
    {
        $create = $this->signedPost(
            'op_portal_a',
            'key_portal_a',
            $this->secretA,
            '/api/b2b/v1/portal/support/tickets',
            json_encode([
                'subject' => 'Wallet callback secret=operator-ticket-secret',
                'message' => 'Please investigate token=operator-ticket-secret urgently.',
                'priority' => 'high',
                'category' => 'wallet',
                'external_reference' => 'OP-TICKET-1',
            ]),
            'portal-ticket-create-a'
        );

        $create->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.category', 'wallet')
            ->assertJsonPath('data.external_reference', 'OP-TICKET-1')
            ->assertJsonPath('data.message_count', 1);

        $ticketUid = $create->json('data.ticket_uid');
        $this->assertNotEmpty($ticketUid);

        $ticket = DB::table('b2b_operator_support_tickets')
            ->where('operator_id', $this->operatorA->id)
            ->where('ticket_uid', $ticketUid)
            ->first();
        $this->assertNotNull($ticket);
        $this->assertStringContainsString('[REDACTED]', $ticket->subject);
        $this->assertStringNotContainsString('operator-ticket-secret', $ticket->subject);

        $message = DB::table('b2b_operator_support_ticket_messages')
            ->where('ticket_id', $ticket->id)
            ->first();
        $this->assertNotNull($message);
        $this->assertStringContainsString('[REDACTED]', $message->message);
        $this->assertStringNotContainsString('operator-ticket-secret', $message->message);

        $this->signedPost(
            'op_portal_a',
            'key_portal_a',
            $this->secretA,
            '/api/b2b/v1/portal/support/tickets/' . $ticketUid . '/comments',
            json_encode([
                'message' => 'Operator added signature=operator-comment-secret.',
                'external_reference' => 'OP-TICKET-1B',
            ]),
            'portal-ticket-comment-a'
        )
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.message_count', 2);

        $this->signedPost(
            'op_portal_a',
            'key_portal_a',
            $this->secretA,
            '/api/b2b/v1/portal/support/tickets/' . $ticketUid . '/close',
            json_encode([
                'reason' => 'Operator confirms resolved password=operator-close-secret.',
            ]),
            'portal-ticket-close-a'
        )
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.message_count', 3)
            ->assertJsonPath('data.reopen_endpoint', '/api/b2b/v1/portal/support/tickets/' . $ticketUid . '/reopen');

        $this->assertNotNull(DB::table('b2b_operator_support_tickets')->where('id', $ticket->id)->value('closed_at'));

        $this->signedPost(
            'op_portal_a',
            'key_portal_a',
            $this->secretA,
            '/api/b2b/v1/portal/support/tickets/' . $ticketUid . '/reopen',
            json_encode([
                'reason' => 'Operator found new evidence token=operator-reopen-secret.',
            ]),
            'portal-ticket-reopen-a'
        )
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.message_count', 4)
            ->assertJsonPath('data.comment_endpoint', '/api/b2b/v1/portal/support/tickets/' . $ticketUid . '/comments')
            ->assertJsonPath('data.close_endpoint', '/api/b2b/v1/portal/support/tickets/' . $ticketUid . '/close');

        $reopenedTicket = DB::table('b2b_operator_support_tickets')->where('id', $ticket->id)->first();
        $this->assertSame('open', $reopenedTicket->status);
        $this->assertNull($reopenedTicket->closed_at);
        $this->assertSame(4, DB::table('b2b_operator_support_ticket_messages')->where('ticket_id', $ticket->id)->count());

        foreach (['support_ticket.created', 'support_ticket.operator_commented', 'support_ticket.closed', 'support_ticket.reopened'] as $eventType) {
            $event = DB::table('b2b_operator_audit_events')
                ->where('operator_id', $this->operatorA->id)
                ->where('event_type', $eventType)
                ->where('subject_type', 'support_ticket')
                ->where('subject_id', $ticketUid)
                ->first();

            $this->assertNotNull($event, $eventType . ' audit event is missing');
            $this->assertStringNotContainsString('operator-ticket-secret', (string) $event->reason);
            $this->assertStringNotContainsString('operator-comment-secret', (string) $event->reason);
            $this->assertStringNotContainsString('operator-close-secret', (string) $event->reason);
            $this->assertStringNotContainsString('operator-reopen-secret', (string) $event->reason);
        }

        $this->signedPost(
            'op_portal_b',
            'key_portal_b',
            $this->secretB,
            '/api/b2b/v1/portal/support/tickets/' . $ticketUid . '/comments',
            json_encode(['message' => 'Trying to touch another operator ticket.']),
            'portal-ticket-foreign'
        )
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'SUPPORT_TICKET_NOT_FOUND');

        $longTicketUid = str_repeat('x', 81);
        $this->signedPost(
            'op_portal_a',
            'key_portal_a',
            $this->secretA,
            '/api/b2b/v1/portal/support/tickets/' . $longTicketUid . '/comments',
            json_encode(['message' => 'Overlong ticket UID should fail validation.']),
            'portal-ticket-invalid-comment-uid'
        )
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->signedPost(
            'op_portal_a',
            'key_portal_a',
            $this->secretA,
            '/api/b2b/v1/portal/support/tickets/' . $longTicketUid . '/close',
            json_encode(['reason' => 'Overlong ticket UID should fail validation.']),
            'portal-ticket-invalid-close-uid'
        )
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->signedPost(
            'op_portal_a',
            'key_portal_a',
            $this->secretA,
            '/api/b2b/v1/portal/support/tickets/' . $longTicketUid . '/reopen',
            json_encode(['reason' => 'Overlong ticket UID should fail validation.']),
            'portal-ticket-invalid-reopen-uid'
        )
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function testOperatorPortalSupportTicketDetailIsScopedRedactedAndBounded()
    {
        $response = $this->signedGet(
            'op_portal_a',
            'key_portal_a',
            $this->secretA,
            '/api/b2b/v1/portal/support/tickets/sup_portal_a?limit=2',
            'portal-ticket-detail-a'
        );

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.ticket_uid', 'sup_portal_a')
            ->assertJsonPath('data.message_count', 2)
            ->assertJsonPath('data.messages.0.actor', 'operator:op_portal_a')
            ->assertJsonPath('data.messages.0.source', 'operator_portal')
            ->assertJsonPath('data.messages.0.metadata.token', '[REDACTED]')
            ->assertJsonPath('data.messages.1.actor', 'web:support_admin')
            ->assertJsonPath('data.messages.1.source', 'web_backoffice')
            ->assertJsonPath('data.messages.1.metadata.token', '[REDACTED]')
            ->assertJsonPath('data.latest_message.actor', 'web:support_admin')
            ->assertJsonPath('data.latest_message.source', 'web_backoffice')
            ->assertJsonPath('data.latest_message.metadata.token', '[REDACTED]');

        $content = $response->getContent();
        $this->assertStringContainsString('[REDACTED]', $content);
        $this->assertStringNotContainsString('support-ticket-secret', $content);
        $this->assertStringNotContainsString('support-ticket-latest-secret', $content);
        $this->assertStringNotContainsString('sup_portal_b', $content);
        $this->assertStringNotContainsString('Foreign operator ticket', $content);

        $limited = $this->signedGet(
            'op_portal_a',
            'key_portal_a',
            $this->secretA,
            '/api/b2b/v1/portal/support/tickets/sup_portal_a?limit=1',
            'portal-ticket-detail-limit'
        );
        $limited->assertStatus(200);
        $this->assertCount(1, $limited->json('data.messages'));
        $this->assertSame('web:support_admin', $limited->json('data.latest_message.actor'));

        $this->signedGet(
            'op_portal_b',
            'key_portal_b',
            $this->secretB,
            '/api/b2b/v1/portal/support/tickets/sup_portal_a',
            'portal-ticket-detail-foreign'
        )
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'SUPPORT_TICKET_NOT_FOUND');

        $longTicketUid = str_repeat('x', 81);
        $this->signedGet(
            'op_portal_a',
            'key_portal_a',
            $this->secretA,
            '/api/b2b/v1/portal/support/tickets/' . $longTicketUid,
            'portal-ticket-detail-invalid-uid'
        )
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->signedGet(
            'op_portal_a',
            'key_portal_a',
            $this->secretA,
            '/api/b2b/v1/portal/support/tickets/sup_portal_a?limit=101',
            'portal-ticket-detail-invalid-limit'
        )
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function testOperatorPortalSupportThreadPagesAreScopedRedactedAndBounded()
    {
        $case = $this->signedGet(
            'op_portal_a',
            'key_portal_a',
            $this->secretA,
            '/api/b2b/v1/portal/support/cases/tx_portal_a_case_thread/thread?limit=1',
            'portal-case-thread-page'
        );

        $case->assertStatus(200);
        $caseContent = $case->getContent();
        $this->assertStringContainsString('<html', $caseContent);
        $this->assertStringContainsString('Support Case Thread', $caseContent);
        $this->assertStringContainsString('tx_portal_a_case_thread', $caseContent);
        $this->assertStringContainsString('OP-CASE-A1', $caseContent);
        $this->assertStringContainsString('[REDACTED]', $caseContent);
        $this->assertStringContainsString('/api/b2b/v1/portal/support/cases/tx_portal_a_case_thread/thread', $caseContent);
        $this->assertStringNotContainsString('case-thread-secret', $caseContent);
        $this->assertStringNotContainsString('case-thread-latest-secret', $caseContent);
        $this->assertStringNotContainsString('case-internal-secret', $caseContent);
        $this->assertStringNotContainsString('tx_portal_b_bet', $caseContent);

        $openCase = $this->signedGet(
            'op_portal_a',
            'key_portal_a',
            $this->secretA,
            '/api/b2b/v1/portal/support/cases/tx_portal_a_win/thread?limit=1',
            'portal-case-open-thread-page'
        );

        $openCase->assertStatus(200);
        $openCaseContent = $openCase->getContent();
        $this->assertStringContainsString('tx_portal_a_win', $openCaseContent);
        $this->assertStringContainsString('/api/b2b/v1/portal/support/cases/tx_portal_a_win/comments', $openCaseContent);

        $ticket = $this->signedGet(
            'op_portal_a',
            'key_portal_a',
            $this->secretA,
            '/api/b2b/v1/portal/support/tickets/sup_portal_a/thread?limit=2',
            'portal-ticket-thread-page'
        );

        $ticket->assertStatus(200);
        $ticketContent = $ticket->getContent();
        $this->assertStringContainsString('<html', $ticketContent);
        $this->assertStringContainsString('Support Ticket Thread', $ticketContent);
        $this->assertStringContainsString('sup_portal_a', $ticketContent);
        $this->assertStringContainsString('/api/b2b/v1/portal/support/tickets/sup_portal_a/comments', $ticketContent);
        $this->assertStringContainsString('/api/b2b/v1/portal/support/tickets/sup_portal_a/close', $ticketContent);
        $this->assertStringContainsString('web_backoffice', $ticketContent);
        $this->assertStringContainsString('[REDACTED]', $ticketContent);
        $this->assertStringNotContainsString('support-ticket-secret', $ticketContent);
        $this->assertStringNotContainsString('support-ticket-latest-secret', $ticketContent);
        $this->assertStringNotContainsString('sup_portal_b', $ticketContent);
        $this->assertStringNotContainsString('Foreign operator ticket', $ticketContent);

        $closedTicket = $this->signedGet(
            'op_portal_a',
            'key_portal_a',
            $this->secretA,
            '/api/b2b/v1/portal/support/tickets/sup_portal_a_closed/thread?limit=1',
            'portal-ticket-closed-thread-page'
        );

        $closedTicket->assertStatus(200);
        $closedTicketContent = $closedTicket->getContent();
        $this->assertStringContainsString('sup_portal_a_closed', $closedTicketContent);
        $this->assertStringContainsString('/api/b2b/v1/portal/support/tickets/sup_portal_a_closed/reopen', $closedTicketContent);
        $this->assertStringNotContainsString('closed-ticket-secret', $closedTicketContent);

        $this->signedGet(
            'op_portal_b',
            'key_portal_b',
            $this->secretB,
            '/api/b2b/v1/portal/support/cases/tx_portal_a_case_thread/thread',
            'portal-case-thread-foreign'
        )
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'CASE_NOT_FOUND');

        $this->signedGet(
            'op_portal_b',
            'key_portal_b',
            $this->secretB,
            '/api/b2b/v1/portal/support/tickets/sup_portal_a/thread',
            'portal-ticket-thread-foreign'
        )
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'SUPPORT_TICKET_NOT_FOUND');
    }

    public function testOperatorPortalSupportTicketsRequireSignature()
    {
        $this->get('/api/b2b/v1/portal/support/tickets/sup_portal_a')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'B2B_AUTH_FAILED');

        $this->get('/api/b2b/v1/portal/support/tickets/sup_portal_a/thread')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'B2B_AUTH_FAILED');

        $this->postJson('/api/b2b/v1/portal/support/tickets', [
            'subject' => 'Unsigned support ticket',
            'message' => 'Unsigned ticket body.',
        ])
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'B2B_AUTH_FAILED');
    }

    private function signedGet($operatorUid, $keyId, $secret, $uri, $nonce)
    {
        $headers = $this->signedB2BHeaders($operatorUid, $keyId, $secret, 'GET', $uri, '', $nonce);

        return $this->signedB2BRequest('GET', $uri, '', $headers);
    }

    private function signedPost($operatorUid, $keyId, $secret, $uri, $body, $nonce)
    {
        $headers = $this->signedB2BHeaders($operatorUid, $keyId, $secret, 'POST', $uri, $body, $nonce);

        return $this->signedB2BRequest('POST', $uri, $body, $headers);
    }

    private function commentTokenValue($message)
    {
        if (preg_match('/token=([^\\s]+)/', $message, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function seedPortalData()
    {
        $now = now();
        $playerA = DB::table('b2b_operator_players')->insertGetId([
            'operator_id' => $this->operatorA->id,
            'external_player_id' => 'player_portal_a',
            'currency' => 'USD',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $playerB = DB::table('b2b_operator_players')->insertGetId([
            'operator_id' => $this->operatorB->id,
            'external_player_id' => 'player_portal_b',
            'currency' => 'USD',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('b2b_game_sessions')->insert([
            [
                'operator_id' => $this->operatorA->id,
                'operator_player_id' => $playerA,
                'session_uid' => 'sess_portal_a',
                'game_uid' => 'book_portal_a',
                'provider' => 'goldsvet_internal',
                'mode' => 'real',
                'currency' => 'USD',
                'status' => 'active',
                'failure_code' => null,
                'failure_message' => null,
                'expires_at' => $now->copy()->addHour(),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'operator_id' => $this->operatorA->id,
                'operator_player_id' => $playerA,
                'session_uid' => 'sess_portal_failed',
                'game_uid' => 'book_portal_a',
                'provider' => 'goldsvet_internal',
                'mode' => 'real',
                'currency' => 'USD',
                'status' => 'failed',
                'failure_code' => 'SHADOW_USER_FAILED',
                'failure_message' => 'Shadow user failed token=launch-failure-secret',
                'expires_at' => $now->copy()->addHour(),
                'created_at' => $now->copy()->subHour(),
                'updated_at' => $now->copy()->subHour(),
            ],
            [
                'operator_id' => $this->operatorB->id,
                'operator_player_id' => $playerB,
                'session_uid' => 'sess_portal_b',
                'game_uid' => 'book_portal_b',
                'provider' => 'goldsvet_internal',
                'mode' => 'real',
                'currency' => 'USD',
                'status' => 'active',
                'failure_code' => null,
                'failure_message' => null,
                'expires_at' => $now->copy()->addHour(),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $walletA = [
            [
                'operator_id' => $this->operatorA->id,
                'operator_player_id' => $playerA,
                'session_id' => 'sess_portal_a',
                'game_uid' => 'book_portal_a',
                'round_id' => 'round_portal_a',
                'transaction_uid' => 'tx_portal_a_bet',
                'transaction_id' => 'operator_tx_portal_a_bet',
                'type' => 'bet',
                'amount' => '10.00000000',
                'currency' => 'USD',
                'status' => 'success',
                'raw_request' => json_encode(['token' => 'super-secret-value']),
                'raw_response' => json_encode(['secret' => 'super-secret-value']),
                'attempts' => 1,
                'created_at' => $now->copy()->subMinute(),
                'updated_at' => $now->copy()->subMinute(),
            ],
            [
                'operator_id' => $this->operatorA->id,
                'operator_player_id' => $playerA,
                'session_id' => 'sess_portal_a',
                'game_uid' => 'book_portal_a',
                'round_id' => 'round_portal_a',
                'transaction_uid' => 'tx_portal_a_win',
                'transaction_id' => 'operator_tx_portal_a_win',
                'type' => 'win',
                'amount' => '4.00000000',
                'currency' => 'USD',
                'status' => 'pending',
                'raw_request' => null,
                'raw_response' => null,
                'attempts' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('b2b_wallet_transactions')->insert(array_merge($walletA, [[
            'operator_id' => $this->operatorB->id,
            'operator_player_id' => $playerB,
            'session_id' => 'sess_portal_b',
            'game_uid' => 'book_portal_b',
            'round_id' => 'round_portal_b',
            'transaction_uid' => 'tx_portal_b_bet',
            'transaction_id' => 'operator_tx_portal_b_bet',
            'type' => 'bet',
            'amount' => '99.00000000',
            'currency' => 'USD',
            'status' => 'success',
            'raw_request' => null,
            'raw_response' => null,
            'attempts' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]]));

        $walletTransactionA = DB::table('b2b_wallet_transactions')
            ->where('operator_id', $this->operatorA->id)
            ->where('transaction_uid', 'tx_portal_a_bet')
            ->first();
        $walletTransactionB = DB::table('b2b_wallet_transactions')
            ->where('operator_id', $this->operatorB->id)
            ->where('transaction_uid', 'tx_portal_b_bet')
            ->first();

        DB::table('b2b_wallet_callback_logs')->insert([
            [
                'operator_id' => $this->operatorA->id,
                'wallet_transaction_id' => $walletTransactionA->id,
                'direction' => 'outbound',
                'endpoint' => 'https://wallet-a.example/callback?token=callback-secret-value',
                'http_status' => 500,
                'request_body' => json_encode(['access_token' => 'callback-secret-value']),
                'response_body' => json_encode(['signature' => 'callback-secret-value']),
                'duration_ms' => 850,
                'error_message' => 'callback failed token=callback-secret-value',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'operator_id' => $this->operatorB->id,
                'wallet_transaction_id' => $walletTransactionB->id,
                'direction' => 'outbound',
                'endpoint' => 'https://wallet-b.example/callback?token=callback-secret-value-b',
                'http_status' => 200,
                'request_body' => null,
                'response_body' => null,
                'duration_ms' => 90,
                'error_message' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('b2b_wallet_transaction_attempts')->insert([
            [
                'wallet_transaction_id' => $walletTransactionA->id,
                'operator_id' => $this->operatorA->id,
                'transaction_uid' => 'tx_portal_a_bet',
                'type' => 'bet',
                'attempt_no' => 1,
                'url' => 'https://wallet-a.example/callback?signature=attempt-secret-value',
                'timeout_ms' => 5000,
                'http_status' => 504,
                'result' => 'timeout',
                'duration_ms' => 5000,
                'request_body' => json_encode(['access_token' => 'attempt-secret-value']),
                'response_body' => json_encode(['api_key' => 'attempt-secret-value']),
                'error' => 'timeout signature=attempt-secret-value',
                'started_at' => $now->copy()->subSeconds(5),
                'finished_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'wallet_transaction_id' => $walletTransactionB->id,
                'operator_id' => $this->operatorB->id,
                'transaction_uid' => 'tx_portal_b_bet',
                'type' => 'bet',
                'attempt_no' => 1,
                'url' => 'https://wallet-b.example/callback?signature=attempt-secret-value-b',
                'timeout_ms' => 5000,
                'http_status' => 200,
                'result' => 'success',
                'duration_ms' => 90,
                'request_body' => null,
                'response_body' => null,
                'error' => null,
                'started_at' => $now->copy()->subSeconds(1),
                'finished_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('b2b_provider_requests')->insert([
            [
                'operator_id' => $this->operatorA->id,
                'provider' => 'goldsvet_internal',
                'game_uid' => 'book_portal_a',
                'session_id' => 'sess_portal_a',
                'request_uid' => 'diag_portal_a_launch',
                'action' => 'launch',
                'status' => 'failed',
                'request_payload' => json_encode([
                    'session_uid' => 'sess_portal_a',
                    'game_uid' => 'book_portal_a',
                    'token' => 'provider-request-secret',
                ]),
                'response_payload' => json_encode([
                    'ok' => false,
                    'error_code' => 'SHADOW_USER_FAILED',
                    'api_secret' => 'provider-response-secret',
                ]),
                'error_message' => 'Provider launch failed token=provider-error-secret',
                'duration_ms' => 345,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'operator_id' => $this->operatorB->id,
                'provider' => 'goldsvet_internal',
                'game_uid' => 'book_portal_b',
                'session_id' => 'sess_portal_b',
                'request_uid' => 'diag_portal_b_launch',
                'action' => 'launch',
                'status' => 'success',
                'request_payload' => json_encode([
                    'session_uid' => 'sess_portal_b',
                    'token' => 'provider-foreign-secret',
                ]),
                'response_payload' => json_encode(['ok' => true]),
                'error_message' => null,
                'duration_ms' => 40,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('b2b_game_catalog')->insert([
            'game_uid' => 'book_portal_a',
            'provider' => 'goldsvet_internal',
            'title' => 'Portal Book A',
            'category' => 'slots',
            'rtp' => '96.50',
            'volatility' => 'medium',
            'thumbnail_url' => 'https://cdn.example/games/book_portal_a.png?token=game-thumbnail-secret',
            'demo_supported' => true,
            'real_supported' => true,
            'supported_currencies' => json_encode(['USD', 'EUR']),
            'supported_countries' => json_encode(['US', 'CA']),
            'status' => 'active',
            'metadata' => json_encode([
                'certification' => 'lab-a',
                'token' => 'game-catalog-secret',
            ]),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('b2b_operator_game_assignments')->insert([
            'operator_id' => $this->operatorA->id,
            'game_uid' => 'book_portal_a',
            'provider' => 'goldsvet_internal',
            'status' => 'allowed',
            'demo_enabled' => true,
            'real_enabled' => true,
            'allowed_currencies' => json_encode(['USD']),
            'allowed_countries' => json_encode(['US']),
            'metadata' => json_encode([
                'rollout' => 'portal',
                'token' => 'game-assignment-secret',
            ]),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('b2b_settlements')->insert([
            [
                'operator_id' => $this->operatorA->id,
                'settlement_uid' => 'settlement_portal_a',
                'period_start' => $now->copy()->subDay(),
                'period_end' => $now,
                'currency' => 'USD',
                'bets_amount' => '10.00000000',
                'wins_amount' => '4.00000000',
                'refunds_amount' => '0.00000000',
                'ggr_amount' => '6.00000000',
                'aggregator_fee_amount' => '0.00000000',
                'provider_fee_amount' => '0.00000000',
                'net_amount' => '6.00000000',
                'status' => 'submitted',
                'export_format' => 'json',
                'export_hash' => 'settlement-export-hash-a',
                'exported_at' => $now->copy()->subMinutes(10),
                'submitted_at' => $now->copy()->subMinutes(5),
                'submitted_by' => 'finance_user',
                'approved_at' => null,
                'approved_by' => null,
                'rejected_at' => null,
                'rejected_by' => null,
                'metadata' => json_encode([
                    'snapshot' => [
                        'operator_uid' => 'op_portal_a',
                        'period_start' => $now->copy()->subDay()->toIso8601String(),
                        'period_end' => $now->toIso8601String(),
                        'currency' => 'USD',
                        'token' => 'settlement-secret-value',
                    ],
                    'totals' => [
                        'transactions' => 2,
                        'bets' => '10.00000000',
                        'wins' => '4.00000000',
                        'refunds' => '0.00000000',
                        'rollbacks' => '0.00000000',
                        'ggr' => '6.00000000',
                        'aggregator_fee' => '0.00000000',
                        'provider_fee' => '0.00000000',
                        'net' => '6.00000000',
                    ],
                    'by_type' => [
                        'bet' => ['count' => 1, 'amount' => '10.00000000'],
                        'win' => ['count' => 1, 'amount' => '4.00000000'],
                    ],
                    'approval' => [
                        'decision' => 'submitted',
                        'actor' => 'finance_user',
                        'reason' => 'operator approved token=settlement-secret-value',
                        'decided_at' => $now->copy()->subMinutes(5)->toIso8601String(),
                    ],
                    'export' => [
                        'format' => 'json',
                        'sha256' => 'settlement-export-hash-a',
                        'generated_at' => $now->copy()->subMinutes(10)->toIso8601String(),
                    ],
                    'export_content' => 'token=settlement-secret-value',
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'operator_id' => $this->operatorB->id,
                'settlement_uid' => 'settlement_portal_b',
                'period_start' => $now->copy()->subDay(),
                'period_end' => $now,
                'currency' => 'USD',
                'bets_amount' => '99.00000000',
                'wins_amount' => '0.00000000',
                'refunds_amount' => '0.00000000',
                'ggr_amount' => '99.00000000',
                'aggregator_fee_amount' => '0.00000000',
                'provider_fee_amount' => '0.00000000',
                'net_amount' => '99.00000000',
                'status' => 'submitted',
                'export_format' => null,
                'export_hash' => null,
                'exported_at' => null,
                'submitted_at' => null,
                'submitted_by' => null,
                'approved_at' => null,
                'approved_by' => null,
                'rejected_at' => null,
                'rejected_by' => null,
                'metadata' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('b2b_wallet_reconciliation_items')->insert([
            'operator_id' => $this->operatorA->id,
            'wallet_transaction_id' => null,
            'transaction_uid' => 'tx_portal_a_win',
            'status' => 'pending',
            'reason' => 'provider_pending',
            'priority' => 'high',
            'state' => 'open',
            'detected_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('b2b_wallet_reconciliation_items')->insert([
            'operator_id' => $this->operatorA->id,
            'wallet_transaction_id' => null,
            'transaction_uid' => 'tx_portal_a_case_thread',
            'status' => 'pending',
            'reason' => 'provider_pending',
            'priority' => 'medium',
            'state' => 'resolved',
            'context' => json_encode([
                'operator_comments' => [
                    [
                        'message' => 'First case detail token=case-thread-secret',
                        'external_reference' => 'OP-CASE-A1',
                        'actor' => 'operator:op_portal_a',
                        'source' => 'operator_portal',
                        'request_id' => 'case-detail-a1',
                        'at' => $now->copy()->subMinutes(2)->toIso8601String(),
                    ],
                    [
                        'message' => 'Latest case detail password=case-thread-latest-secret',
                        'external_reference' => 'OP-CASE-A2',
                        'actor' => 'operator:op_portal_a',
                        'source' => 'operator_portal',
                        'request_id' => 'case-detail-a2',
                        'at' => $now->copy()->subMinute()->toIso8601String(),
                    ],
                ],
                'case_events' => [
                    [
                        'action' => 'resolve',
                        'reason' => 'Internal support note token=case-internal-secret',
                        'source' => 'web_backoffice',
                    ],
                ],
            ]),
            'detected_at' => $now->copy()->subHour(),
            'resolved_at' => $now,
            'created_at' => $now->copy()->subHour(),
            'updated_at' => $now,
        ]);

        DB::table('b2b_operator_health_events')->insert([
            [
                'operator_id' => $this->operatorA->id,
                'event_type' => 'wallet_degraded',
                'status' => 'degraded',
                'failure_count' => 3,
                'message' => 'Wallet callback failing token=support-secret-value',
                'context' => json_encode(['token' => 'support-secret-value']),
                'created_at' => $now,
            ],
            [
                'operator_id' => $this->operatorB->id,
                'event_type' => 'wallet_restored',
                'status' => 'healthy',
                'failure_count' => 0,
                'message' => 'Other operator restored.',
                'context' => null,
                'created_at' => $now,
            ],
        ]);

        $ticketA = DB::table('b2b_operator_support_tickets')->insertGetId([
            'operator_id' => $this->operatorA->id,
            'ticket_uid' => 'sup_portal_a',
            'subject' => 'Wallet issue token=support-ticket-secret',
            'status' => 'open',
            'priority' => 'high',
            'category' => 'wallet',
            'external_reference' => 'OP-SUP-A',
            'context' => json_encode(['token' => 'support-ticket-secret']),
            'last_message_at' => $now,
            'closed_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $ticketAClosed = DB::table('b2b_operator_support_tickets')->insertGetId([
            'operator_id' => $this->operatorA->id,
            'ticket_uid' => 'sup_portal_a_closed',
            'subject' => 'Closed wallet ticket token=closed-ticket-secret',
            'status' => 'closed',
            'priority' => 'normal',
            'category' => 'wallet',
            'external_reference' => 'OP-SUP-CLOSED',
            'context' => json_encode(['token' => 'closed-ticket-secret']),
            'last_message_at' => $now->copy()->subMinutes(3),
            'closed_at' => $now->copy()->subMinutes(3),
            'created_at' => $now->copy()->subMinutes(10),
            'updated_at' => $now->copy()->subMinutes(3),
        ]);
        $ticketB = DB::table('b2b_operator_support_tickets')->insertGetId([
            'operator_id' => $this->operatorB->id,
            'ticket_uid' => 'sup_portal_b',
            'subject' => 'Foreign ticket',
            'status' => 'open',
            'priority' => 'normal',
            'category' => 'wallet',
            'external_reference' => 'OP-SUP-B',
            'context' => null,
            'last_message_at' => $now,
            'closed_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('b2b_operator_support_ticket_messages')->insert([
            [
                'ticket_id' => $ticketA,
                'operator_id' => $this->operatorA->id,
                'actor' => 'operator:op_portal_a',
                'source' => 'operator_portal',
                'message' => 'Wallet issue token=support-ticket-secret',
                'metadata' => json_encode(['token' => 'support-ticket-secret']),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'ticket_id' => $ticketA,
                'operator_id' => $this->operatorA->id,
                'actor' => 'web:support_admin',
                'source' => 'web_backoffice',
                'message' => 'Staff follow-up password=support-ticket-latest-secret',
                'metadata' => json_encode(['token' => 'support-ticket-latest-secret']),
                'created_at' => $now->copy()->addSecond(),
                'updated_at' => $now->copy()->addSecond(),
            ],
            [
                'ticket_id' => $ticketAClosed,
                'operator_id' => $this->operatorA->id,
                'actor' => 'operator:op_portal_a',
                'source' => 'operator_portal',
                'message' => 'Closed ticket token=closed-ticket-secret',
                'metadata' => json_encode(['token' => 'closed-ticket-secret']),
                'created_at' => $now->copy()->subMinutes(3),
                'updated_at' => $now->copy()->subMinutes(3),
            ],
            [
                'ticket_id' => $ticketB,
                'operator_id' => $this->operatorB->id,
                'actor' => 'operator:op_portal_b',
                'source' => 'operator_portal',
                'message' => 'Foreign operator ticket.',
                'metadata' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('b2b_operator_api_keys')->insert([
            'operator_id' => $this->operatorA->id,
            'key_id' => 'key_portal_legacy',
            'secret_encrypted' => 'legacy-placeholder-not-used',
            'status' => 'disabled',
            'max_rps' => 10,
            'scopes' => 'portal.read,reports.export',
            'last_used_at' => null,
            'expires_at' => null,
            'created_at' => $now->copy()->subDay(),
            'updated_at' => $now->copy()->subDay(),
        ]);

        DB::table('b2b_operator_audit_events')->insert([
            [
                'operator_id' => $this->operatorA->id,
                'event_type' => 'api_key.used',
                'subject_type' => 'api_key',
                'subject_id' => 'key_portal_a',
                'actor' => 'api:op_portal_a',
                'reason' => 'Signed portal request token=audit-secret-value.',
                'metadata' => json_encode([
                    'path' => '/api/b2b/v1/portal/overview',
                    'api_key' => 'audit-secret-value',
                    'request_id' => 'audit-portal-a',
                ]),
                'created_at' => $now->copy()->addSeconds(2),
                'updated_at' => $now->copy()->addSeconds(2),
            ],
            [
                'operator_id' => $this->operatorB->id,
                'event_type' => 'api_key.used',
                'subject_type' => 'api_key',
                'subject_id' => 'key_portal_b',
                'actor' => 'api:op_portal_b',
                'reason' => 'Foreign portal request token=audit-foreign-secret.',
                'metadata' => json_encode([
                    'path' => '/api/b2b/v1/portal/overview',
                    'api_key' => 'audit-foreign-secret',
                ]),
                'created_at' => $now->copy()->addSeconds(3),
                'updated_at' => $now->copy()->addSeconds(3),
            ],
        ]);
    }
}
