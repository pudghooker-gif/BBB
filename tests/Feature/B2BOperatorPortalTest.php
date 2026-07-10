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
            ->assertJsonPath('data.settlements.by_status.submitted.count', 1)
            ->assertJsonPath('data.reconciliation.by_state.open.count', 1)
            ->assertJsonPath('data.callbacks.by_result.server_error.count', 1)
            ->assertJsonPath('data.callbacks.recent_logs.0.endpoint', 'https://wallet-a.example/callback')
            ->assertJsonPath('data.callbacks.recent_attempts.0.endpoint', 'https://wallet-a.example/callback')
            ->assertJsonPath('data.support.by_status.degraded.count', 1)
            ->assertJsonPath('data.support.recent_events.0.event_type', 'wallet_degraded')
            ->assertJsonPath('data.support.tickets_by_status.open.count', 1)
            ->assertJsonPath('data.support.recent_tickets.0.ticket_uid', 'sup_portal_a')
            ->assertJsonPath('data.support.recent_tickets.0.message_count', 2)
            ->assertJsonPath('data.support.recent_tickets.0.latest_message.actor', 'web:support_admin')
            ->assertJsonPath('data.support.recent_tickets.0.latest_message.source', 'web_backoffice')
            ->assertJsonPath('data.support.recent_tickets.0.detail_endpoint', '/api/b2b/v1/portal/support/tickets/sup_portal_a')
            ->assertJsonPath('data.support.recent_tickets.0.thread_endpoint', '/api/b2b/v1/portal/support/tickets/sup_portal_a/thread')
            ->assertJsonPath('data.reconciliation.open_items.0.support_case_detail_endpoint', '/api/b2b/v1/portal/support/cases/tx_portal_a_win')
            ->assertJsonPath('data.reconciliation.open_items.0.support_case_thread_endpoint', '/api/b2b/v1/portal/support/cases/tx_portal_a_win/thread')
            ->assertJsonPath('data.reconciliation.recent_cases.0.transaction_uid', 'tx_portal_a_case_thread')
            ->assertJsonPath('data.reconciliation.recent_cases.0.state', 'resolved')
            ->assertJsonPath('data.reconciliation.recent_cases.0.support_case_detail_endpoint', '/api/b2b/v1/portal/support/cases/tx_portal_a_case_thread')
            ->assertJsonPath('data.reconciliation.recent_cases.0.support_case_thread_endpoint', '/api/b2b/v1/portal/support/cases/tx_portal_a_case_thread/thread')
            ->assertJsonPath('data.links.support_case_detail_template', '/api/b2b/v1/portal/support/cases/{transaction_uid}')
            ->assertJsonPath('data.links.support_case_thread_template', '/api/b2b/v1/portal/support/cases/{transaction_uid}/thread')
            ->assertJsonPath('data.links.support_ticket_detail_template', '/api/b2b/v1/portal/support/tickets/{ticket_uid}')
            ->assertJsonPath('data.links.support_ticket_thread_template', '/api/b2b/v1/portal/support/tickets/{ticket_uid}/thread')
            ->assertJsonPath('data.recent_sessions.0.session_uid', 'sess_portal_a')
            ->assertJsonPath('data.recent_transactions.0.transaction_uid', 'tx_portal_a_win');

        $this->assertStringContainsString('[REDACTED]', $response->json('data.support.recent_tickets.0.latest_message.message'));
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
        $this->assertStringNotContainsString('support-secret-value', $content);
        $this->assertStringNotContainsString('support-ticket-secret', $content);
        $this->assertStringNotContainsString('base-url-secret', $content);
        $this->assertStringNotContainsString('operator-callback-secret', $content);
        $this->assertStringNotContainsString('sup_portal_b', $content);
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
        $this->assertStringContainsString('settlement_portal_a', $content);
        $this->assertStringContainsString('sup_portal_a', $content);
        $this->assertStringContainsString('/api/b2b/v1/portal/support/cases/tx_portal_a_win', $content);
        $this->assertStringContainsString('/api/b2b/v1/portal/support/cases/tx_portal_a_win/thread', $content);
        $this->assertStringContainsString('/api/b2b/v1/portal/support/tickets/sup_portal_a', $content);
        $this->assertStringContainsString('/api/b2b/v1/portal/support/tickets/sup_portal_a/thread', $content);
        $this->assertStringContainsString('web_backoffice', $content);
        $this->assertStringContainsString('portal.read', $content);
        $this->assertStringNotContainsString('Portal Operator B', $content);
        $this->assertStringNotContainsString('tx_portal_b_bet', $content);
        $this->assertStringNotContainsString('sess_portal_b', $content);
        $this->assertStringNotContainsString('secret_encrypted', $content);
        $this->assertStringNotContainsString('super-secret-value', $content);
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
            'reports' => '/api/b2b/v1/reports/ggr',
            'support' => 'wallet_degraded',
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
            if ($section === 'support') {
                $this->assertStringContainsString('sup_portal_a', $content);
                $this->assertStringContainsString('web_backoffice', $content);
                $this->assertStringContainsString('[REDACTED]', $content);
                $this->assertStringContainsString('/api/b2b/v1/portal/support/tickets/sup_portal_a', $content);
                $this->assertStringContainsString('/api/b2b/v1/portal/support/tickets/sup_portal_a/thread', $content);
                $this->assertStringContainsString('/api/b2b/v1/portal/support/cases/tx_portal_a_win', $content);
                $this->assertStringContainsString('/api/b2b/v1/portal/support/cases/tx_portal_a_win/thread', $content);
                $this->assertStringContainsString('tx_portal_a_case_thread', $content);
                $this->assertStringContainsString('/api/b2b/v1/portal/support/cases/tx_portal_a_case_thread', $content);
                $this->assertStringContainsString('/api/b2b/v1/portal/support/cases/tx_portal_a_case_thread/thread', $content);
            }
            if ($section === 'cases') {
                $this->assertStringContainsString('/api/b2b/v1/portal/support/cases/tx_portal_a_win', $content);
                $this->assertStringContainsString('/api/b2b/v1/portal/support/cases/tx_portal_a_win/thread', $content);
                $this->assertStringContainsString('tx_portal_a_case_thread', $content);
                $this->assertStringContainsString('/api/b2b/v1/portal/support/cases/tx_portal_a_case_thread', $content);
                $this->assertStringContainsString('/api/b2b/v1/portal/support/cases/tx_portal_a_case_thread/thread', $content);
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
            $this->assertStringNotContainsString('support-secret-value', $content);
            $this->assertStringNotContainsString('support-ticket-secret', $content);
            $this->assertStringNotContainsString('case-thread-secret', $content);
            $this->assertStringNotContainsString('case-thread-latest-secret', $content);
            $this->assertStringNotContainsString('case-internal-secret', $content);
            $this->assertStringNotContainsString('base-url-secret', $content);
            $this->assertStringNotContainsString('operator-callback-secret', $content);
            $this->assertStringNotContainsString('sup_portal_b', $content);
            $this->assertStringNotContainsString('wallet-b.example', $content);
            $this->assertStringNotContainsString('wallet_restored', $content);
        }
    }

    public function testOperatorPortalWorkflowPagesRequireSignature()
    {
        $this->get('/api/b2b/v1/portal/transactions')
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
            ->assertJsonPath('data.message_count', 3);

        $this->assertSame(3, DB::table('b2b_operator_support_ticket_messages')->where('ticket_id', $ticket->id)->count());

        foreach (['support_ticket.created', 'support_ticket.operator_commented', 'support_ticket.closed'] as $eventType) {
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
        $this->assertStringNotContainsString('case-thread-secret', $caseContent);
        $this->assertStringNotContainsString('case-thread-latest-secret', $caseContent);
        $this->assertStringNotContainsString('case-internal-secret', $caseContent);
        $this->assertStringNotContainsString('tx_portal_b_bet', $caseContent);

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
        $this->assertStringContainsString('web_backoffice', $ticketContent);
        $this->assertStringContainsString('[REDACTED]', $ticketContent);
        $this->assertStringNotContainsString('support-ticket-secret', $ticketContent);
        $this->assertStringNotContainsString('support-ticket-latest-secret', $ticketContent);
        $this->assertStringNotContainsString('sup_portal_b', $ticketContent);
        $this->assertStringNotContainsString('Foreign operator ticket', $ticketContent);

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
                'expires_at' => $now->copy()->addHour(),
                'created_at' => $now,
                'updated_at' => $now,
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

        DB::table('b2b_operator_game_assignments')->insert([
            'operator_id' => $this->operatorA->id,
            'game_uid' => 'book_portal_a',
            'provider' => 'goldsvet_internal',
            'status' => 'allowed',
            'demo_enabled' => true,
            'real_enabled' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('b2b_settlements')->insert([
            'operator_id' => $this->operatorA->id,
            'settlement_uid' => 'settlement_portal_a',
            'period_start' => $now->copy()->subDay(),
            'period_end' => $now,
            'currency' => 'USD',
            'ggr_amount' => '6.00000000',
            'net_amount' => '6.00000000',
            'status' => 'submitted',
            'created_at' => $now,
            'updated_at' => $now,
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
    }
}
