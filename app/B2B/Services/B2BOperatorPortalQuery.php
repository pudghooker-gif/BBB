<?php

namespace VanguardLTE\B2B\Services;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class B2BOperatorPortalQuery
{
    private $reports;
    private $redactor;
    private $walletLookup;

    public function __construct(
        B2BReportQuery $reports,
        B2BPayloadRedactor $redactor,
        WalletTransactionLookupService $walletLookup
    )
    {
        $this->reports = $reports;
        $this->redactor = $redactor;
        $this->walletLookup = $walletLookup;
    }

    public function overview(Request $request)
    {
        $operator = $request->attributes->get('b2b_operator');
        if (!$operator || !isset($operator->id)) {
            return null;
        }

        $operatorId = (int) $operator->id;
        list($fromDate, $toDate) = $this->reports->dateRange($request);
        $limit = $this->reports->safeLimit($request, 10, 50);

        return [
            'operator' => $this->operatorProfile($operator),
            'api_key' => $this->apiKeyProfile($request->attributes->get('b2b_api_key')),
            'period' => $this->period($fromDate, $toDate),
            'summary' => $this->summary($operatorId),
            'wallet' => $this->walletSummary($operatorId, $fromDate, $toDate),
            'sessions' => $this->sessionSummary($operatorId),
            'credentials' => $this->credentialSummary($operatorId, $limit),
            'game_assignments' => $this->gameAssignmentSummary($operatorId, $limit),
            'settlements' => $this->settlementSummary($operatorId, $limit),
            'reconciliation' => $this->reconciliationSummary($operatorId, $limit),
            'callbacks' => $this->callbackSummary($operatorId, $fromDate, $toDate, $limit),
            'support' => $this->supportSummary($operatorId, $limit),
            'audit' => $this->auditSummary($operatorId, $limit),
            'recent_sessions' => $this->recentSessions($operatorId, $limit),
            'recent_transactions' => $this->recentTransactions($operatorId, $fromDate, $toDate, $limit),
            'links' => [
                'portal_overview' => '/api/b2b/v1/portal',
                'portal_credentials' => '/api/b2b/v1/portal/credentials',
                'portal_games' => '/api/b2b/v1/portal/games',
                'portal_sessions' => '/api/b2b/v1/portal/sessions',
                'portal_transactions' => '/api/b2b/v1/portal/transactions',
                'portal_settlements' => '/api/b2b/v1/portal/settlements',
                'portal_cases' => '/api/b2b/v1/portal/cases',
                'portal_callbacks' => '/api/b2b/v1/portal/callbacks',
                'portal_reports' => '/api/b2b/v1/portal/reports',
                'portal_support' => '/api/b2b/v1/portal/support',
                'portal_logs' => '/api/b2b/v1/portal/logs',
                'portal_docs' => '/api/b2b/v1/portal/docs',
                'portal_transaction_detail_template' => '/api/b2b/v1/portal/transactions/{transaction_uid}',
                'support_case_detail_template' => '/api/b2b/v1/portal/support/cases/{transaction_uid}',
                'support_case_thread_template' => '/api/b2b/v1/portal/support/cases/{transaction_uid}/thread',
                'support_ticket_detail_template' => '/api/b2b/v1/portal/support/tickets/{ticket_uid}',
                'support_ticket_thread_template' => '/api/b2b/v1/portal/support/tickets/{ticket_uid}/thread',
                'operator_profile' => '/api/b2b/v1/operator/me',
                'games' => '/api/b2b/v1/games',
                'sessions' => '/api/b2b/v1/sessions',
                'reports_summary' => '/api/b2b/v1/reports/summary',
                'transactions' => '/api/b2b/v1/reports/transactions',
                'reports_ggr' => '/api/b2b/v1/reports/ggr',
                'settlements' => '/api/b2b/v1/reports/settlements',
                'reconciliation' => '/api/b2b/v1/reports/reconciliation',
            ],
        ];
    }

    private function operatorProfile($operator)
    {
        return [
            'id' => $operator->operator_uid,
            'name' => $operator->name,
            'status' => $operator->status,
            'default_currency' => $operator->default_currency,
            'allowed_currencies' => $this->arrayValue($operator->allowed_currencies),
            'allowed_countries' => $this->arrayValue($operator->allowed_countries),
            'base_url' => $this->safeEndpoint($operator->base_url),
            'wallet_callback_url' => $this->safeEndpoint($operator->wallet_callback_url),
            'wallet_callback_configured' => !empty($operator->wallet_callback_url),
            'max_rps' => isset($operator->max_rps) ? (int) $operator->max_rps : null,
            'wallet_timeout_ms' => isset($operator->wallet_timeout_ms) ? (int) $operator->wallet_timeout_ms : null,
            'connect_timeout_ms' => isset($operator->connect_timeout_ms) ? (int) $operator->connect_timeout_ms : null,
            'failure_count' => isset($operator->failure_count) ? (int) $operator->failure_count : null,
            'circuit_open_until' => $this->isoTime($operator->circuit_open_until),
        ];
    }

    private function apiKeyProfile($apiKey)
    {
        if (!$apiKey) {
            return null;
        }

        $scopes = $this->scopeList(isset($apiKey->scopes) ? $apiKey->scopes : null);

        return [
            'key_id' => $apiKey->key_id,
            'status' => $apiKey->status,
            'scopes' => $scopes,
            'scopes_count' => count($scopes),
            'max_rps' => isset($apiKey->max_rps) ? (int) $apiKey->max_rps : null,
            'last_used_at' => $this->isoTime($apiKey->last_used_at),
            'expires_at' => $this->isoTime($apiKey->expires_at),
        ];
    }

    private function summary($operatorId)
    {
        return [
            'players' => $this->countWhere('b2b_operator_players', $operatorId),
            'active_sessions' => $this->countWhere('b2b_game_sessions', $operatorId, function ($query) {
                $query->where('status', 'active');
            }),
            'wallet_transactions' => $this->countWhere('b2b_wallet_transactions', $operatorId),
            'open_reconciliation_items' => $this->countWhere('b2b_wallet_reconciliation_items', $operatorId, function ($query) {
                $query->whereIn('state', ['open', 'in_progress']);
            }),
            'pending_settlements' => $this->countWhere('b2b_settlements', $operatorId, function ($query) {
                $query->whereIn('status', ['draft', 'exported', 'submitted']);
            }),
        ];
    }

    private function walletSummary($operatorId, Carbon $fromDate, Carbon $toDate)
    {
        if (!Schema::hasTable('b2b_wallet_transactions')) {
            return [
                'by_status' => [],
                'by_type' => [],
                'success_amounts' => [],
            ];
        }

        $amountRows = DB::table('b2b_wallet_transactions')
            ->where('operator_id', $operatorId)
            ->where('status', 'success')
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->select('type', 'currency', DB::raw('COALESCE(SUM(amount), 0) as amount'), DB::raw('COUNT(*) as count'))
            ->groupBy('type', 'currency')
            ->orderBy('type')
            ->orderBy('currency')
            ->get();

        $amounts = [];
        foreach ($amountRows as $row) {
            $type = $row->type ?: 'unknown';
            $currency = $row->currency ?: 'UNK';
            if (!isset($amounts[$type])) {
                $amounts[$type] = [];
            }

            $amounts[$type][$currency] = [
                'amount' => $this->decimalNormalize($row->amount),
                'count' => (int) $row->count,
            ];
        }

        return [
            'by_status' => $this->groupCounts('b2b_wallet_transactions', $operatorId, 'status'),
            'by_type' => $this->groupCounts('b2b_wallet_transactions', $operatorId, 'type'),
            'success_amounts' => $amounts,
        ];
    }

    private function sessionSummary($operatorId)
    {
        return [
            'by_status' => $this->groupCounts('b2b_game_sessions', $operatorId, 'status'),
            'by_provider' => $this->groupCounts('b2b_game_sessions', $operatorId, 'provider'),
        ];
    }

    private function credentialSummary($operatorId, $limit)
    {
        if (!Schema::hasTable('b2b_operator_api_keys')) {
            return [
                'by_status' => [],
                'recent_keys' => [],
            ];
        }

        $rows = DB::table('b2b_operator_api_keys')
            ->where('operator_id', $operatorId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get($this->selectExisting('b2b_operator_api_keys', [
                'key_id',
                'status',
                'scopes',
                'max_rps',
                'last_used_at',
                'expires_at',
                'created_at',
            ]));

        return [
            'by_status' => $this->groupCounts('b2b_operator_api_keys', $operatorId, 'status'),
            'recent_keys' => $rows->map(function ($row) {
                $scopes = $this->scopeList(isset($row->scopes) ? $row->scopes : null);

                return [
                    'key_id' => $row->key_id,
                    'status' => $row->status,
                    'scopes' => $scopes,
                    'scopes_count' => count($scopes),
                    'max_rps' => isset($row->max_rps) ? (int) $row->max_rps : null,
                    'last_used_at' => $this->isoTime($row->last_used_at),
                    'expires_at' => $this->isoTime($row->expires_at),
                    'created_at' => $this->isoTime($row->created_at),
                ];
            })->values(),
        ];
    }

    private function gameAssignmentSummary($operatorId, $limit)
    {
        if (!Schema::hasTable('b2b_operator_game_assignments')) {
            return [
                'by_status' => [],
                'by_provider' => [],
                'recent_assignments' => [],
            ];
        }

        $rows = DB::table('b2b_operator_game_assignments')
            ->where('operator_id', $operatorId)
            ->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get(['game_uid', 'provider', 'status', 'demo_enabled', 'real_enabled', 'updated_at']);

        return [
            'by_status' => $this->groupCounts('b2b_operator_game_assignments', $operatorId, 'status'),
            'by_provider' => $this->groupCounts('b2b_operator_game_assignments', $operatorId, 'provider'),
            'recent_assignments' => $rows->map(function ($row) {
                return [
                    'game_uid' => $row->game_uid,
                    'provider' => $row->provider,
                    'status' => $row->status,
                    'demo_enabled' => (bool) $row->demo_enabled,
                    'real_enabled' => (bool) $row->real_enabled,
                    'updated_at' => $this->isoTime($row->updated_at),
                ];
            })->values(),
        ];
    }

    private function settlementSummary($operatorId, $limit)
    {
        if (!Schema::hasTable('b2b_settlements')) {
            return [
                'by_status' => [],
                'recent_settlements' => [],
            ];
        }

        $rows = DB::table('b2b_settlements')
            ->where('operator_id', $operatorId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get($this->selectExisting('b2b_settlements', [
                'settlement_uid',
                'currency',
                'status',
                'ggr_amount',
                'net_amount',
                'period_start',
                'period_end',
                'created_at',
            ]));

        return [
            'by_status' => $this->groupCounts('b2b_settlements', $operatorId, 'status'),
            'recent_settlements' => $rows->map(function ($row) {
                return [
                    'settlement_uid' => isset($row->settlement_uid) ? $row->settlement_uid : null,
                    'currency' => isset($row->currency) ? $row->currency : null,
                    'status' => isset($row->status) ? $row->status : null,
                    'ggr_amount' => isset($row->ggr_amount) ? $this->decimalNormalize($row->ggr_amount) : null,
                    'net_amount' => isset($row->net_amount) ? $this->decimalNormalize($row->net_amount) : null,
                    'period_start' => isset($row->period_start) ? $this->isoTime($row->period_start) : null,
                    'period_end' => isset($row->period_end) ? $this->isoTime($row->period_end) : null,
                    'created_at' => isset($row->created_at) ? $this->isoTime($row->created_at) : null,
                ];
            })->values(),
        ];
    }

    private function reconciliationSummary($operatorId, $limit)
    {
        if (!Schema::hasTable('b2b_wallet_reconciliation_items')) {
            return [
                'by_state' => [],
                'by_priority' => [],
                'open_items' => [],
                'recent_cases' => [],
            ];
        }

        $rows = DB::table('b2b_wallet_reconciliation_items')
            ->where('operator_id', $operatorId)
            ->whereIn('state', ['open', 'in_progress'])
            ->orderBy('detected_at')
            ->limit($limit)
            ->get($this->selectExisting('b2b_wallet_reconciliation_items', [
                'transaction_uid',
                'status',
                'reason',
                'priority',
                'state',
                'detected_at',
                'resolved_at',
                'updated_at',
            ]));

        $recentOrderColumn = Schema::hasColumn('b2b_wallet_reconciliation_items', 'updated_at')
            ? 'updated_at'
            : 'detected_at';

        $recentRows = DB::table('b2b_wallet_reconciliation_items')
            ->where('operator_id', $operatorId)
            ->orderBy($recentOrderColumn, 'desc')
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get($this->selectExisting('b2b_wallet_reconciliation_items', [
                'transaction_uid',
                'status',
                'reason',
                'priority',
                'state',
                'detected_at',
                'resolved_at',
                'updated_at',
            ]));

        return [
            'by_state' => $this->groupCounts('b2b_wallet_reconciliation_items', $operatorId, 'state'),
            'by_priority' => $this->groupCounts('b2b_wallet_reconciliation_items', $operatorId, 'priority'),
            'open_items' => $rows->map(function ($row) {
                return $this->caseSummaryPayload($row);
            })->values(),
            'recent_cases' => $recentRows->map(function ($row) {
                return $this->caseSummaryPayload($row);
            })->values(),
        ];
    }

    private function recentSessions($operatorId, $limit)
    {
        if (!Schema::hasTable('b2b_game_sessions')) {
            return [];
        }

        $query = DB::table('b2b_game_sessions as s')
            ->where('s.operator_id', $operatorId)
            ->orderBy('s.created_at', 'desc')
            ->limit($limit);

        if (Schema::hasTable('b2b_operator_players')) {
            $query->leftJoin('b2b_operator_players as p', 'p.id', '=', 's.operator_player_id');
            $select = [
                's.session_uid',
                's.game_uid',
                's.provider',
                's.mode',
                's.status',
                's.currency',
                's.created_at',
                's.expires_at',
                's.closed_at',
                'p.external_player_id',
            ];
        } else {
            $select = [
                's.session_uid',
                's.game_uid',
                's.provider',
                's.mode',
                's.status',
                's.currency',
                's.created_at',
                's.expires_at',
                's.closed_at',
                DB::raw('NULL as external_player_id'),
            ];
        }

        return $query->get($select)->map(function ($row) {
            return [
                'session_uid' => $row->session_uid,
                'external_player_id' => $row->external_player_id,
                'game_uid' => $row->game_uid,
                'provider' => $row->provider,
                'mode' => $row->mode,
                'status' => $row->status,
                'currency' => $row->currency,
                'created_at' => $this->isoTime($row->created_at),
                'expires_at' => $this->isoTime($row->expires_at),
                'closed_at' => $this->isoTime($row->closed_at),
            ];
        })->values();
    }

    private function callbackSummary($operatorId, Carbon $fromDate, Carbon $toDate, $limit)
    {
        return [
            'by_direction' => $this->groupCountsBetween('b2b_wallet_callback_logs', $operatorId, 'direction', $fromDate, $toDate),
            'by_result' => $this->callbackResultCounts($operatorId, $fromDate, $toDate),
            'attempts_by_result' => $this->groupCountsBetween('b2b_wallet_transaction_attempts', $operatorId, 'result', $fromDate, $toDate),
            'recent_logs' => $this->recentCallbackLogs($operatorId, $fromDate, $toDate, $limit),
            'recent_attempts' => $this->recentCallbackAttempts($operatorId, $fromDate, $toDate, $limit),
        ];
    }

    private function callbackResultCounts($operatorId, Carbon $fromDate, Carbon $toDate)
    {
        if (!Schema::hasTable('b2b_wallet_callback_logs') || !Schema::hasColumn('b2b_wallet_callback_logs', 'http_status')) {
            return [];
        }

        $rows = DB::table('b2b_wallet_callback_logs')
            ->where('operator_id', $operatorId)
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->select('http_status', DB::raw('COUNT(*) as count'))
            ->groupBy('http_status')
            ->orderBy('http_status')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $bucket = $this->callbackStatusBucket($row->http_status);
            if (!isset($result[$bucket])) {
                $result[$bucket] = ['count' => 0];
            }

            $result[$bucket]['count'] += (int) $row->count;
        }

        return $result;
    }

    private function recentCallbackLogs($operatorId, Carbon $fromDate, Carbon $toDate, $limit)
    {
        if (!Schema::hasTable('b2b_wallet_callback_logs')) {
            return [];
        }

        $query = DB::table('b2b_wallet_callback_logs as l')
            ->where('l.operator_id', $operatorId)
            ->whereBetween('l.created_at', [$fromDate, $toDate])
            ->orderBy('l.created_at', 'desc')
            ->limit($limit);

        $select = [
            'l.direction',
            'l.endpoint',
            'l.http_status',
            'l.duration_ms',
            'l.created_at',
            DB::raw('NULL as transaction_uid'),
            DB::raw('NULL as error_message'),
        ];

        if (Schema::hasColumn('b2b_wallet_callback_logs', 'error_message')) {
            $select[count($select) - 1] = 'l.error_message';
        }

        if (Schema::hasTable('b2b_wallet_transactions') && Schema::hasColumn('b2b_wallet_transactions', 'transaction_uid')) {
            $query->leftJoin('b2b_wallet_transactions as tx', function ($join) {
                $join->on('tx.id', '=', 'l.wallet_transaction_id')
                    ->on('tx.operator_id', '=', 'l.operator_id');
            });
            $select[count($select) - 2] = 'tx.transaction_uid';
        }

        return $query->get($select)->map(function ($row) {
            return [
                'transaction_uid' => isset($row->transaction_uid) ? $row->transaction_uid : null,
                'direction' => isset($row->direction) ? $row->direction : null,
                'endpoint' => isset($row->endpoint) ? $this->safeEndpoint($row->endpoint) : null,
                'http_status' => isset($row->http_status) ? (int) $row->http_status : null,
                'result' => isset($row->http_status) ? $this->callbackStatusBucket($row->http_status) : 'unknown',
                'duration_ms' => isset($row->duration_ms) ? (int) $row->duration_ms : null,
                'error_summary' => isset($row->error_message) ? $this->safeErrorSummary($row->error_message) : null,
                'created_at' => isset($row->created_at) ? $this->isoTime($row->created_at) : null,
            ];
        })->values();
    }

    private function recentCallbackAttempts($operatorId, Carbon $fromDate, Carbon $toDate, $limit)
    {
        if (!Schema::hasTable('b2b_wallet_transaction_attempts')) {
            return [];
        }

        return DB::table('b2b_wallet_transaction_attempts')
            ->where('operator_id', $operatorId)
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get($this->selectExisting('b2b_wallet_transaction_attempts', [
                'transaction_uid',
                'type',
                'attempt_no',
                'url',
                'http_status',
                'result',
                'duration_ms',
                'error',
                'started_at',
                'finished_at',
                'created_at',
            ]))
            ->map(function ($row) {
                return [
                    'transaction_uid' => isset($row->transaction_uid) ? $row->transaction_uid : null,
                    'type' => isset($row->type) ? $row->type : null,
                    'attempt_no' => isset($row->attempt_no) ? (int) $row->attempt_no : null,
                    'endpoint' => isset($row->url) ? $this->safeEndpoint($row->url) : null,
                    'http_status' => isset($row->http_status) ? (int) $row->http_status : null,
                    'result' => isset($row->result) ? $row->result : null,
                    'duration_ms' => isset($row->duration_ms) ? (int) $row->duration_ms : null,
                    'error_summary' => isset($row->error) ? $this->safeErrorSummary($row->error) : null,
                    'started_at' => isset($row->started_at) ? $this->isoTime($row->started_at) : null,
                    'finished_at' => isset($row->finished_at) ? $this->isoTime($row->finished_at) : null,
                    'created_at' => isset($row->created_at) ? $this->isoTime($row->created_at) : null,
                ];
            })->values();
    }

    private function recentTransactions($operatorId, Carbon $fromDate, Carbon $toDate, $limit)
    {
        if (!Schema::hasTable('b2b_wallet_transactions')) {
            return [];
        }

        $rows = DB::table('b2b_wallet_transactions')
            ->where('operator_id', $operatorId)
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get($this->selectExisting('b2b_wallet_transactions', [
                'transaction_uid',
                'transaction_id',
                'type',
                'status',
                'amount',
                'currency',
                'session_id',
                'game_uid',
                'round_id',
                'attempts',
                'created_at',
            ]));

        return $rows->map(function ($row) {
            return [
                'transaction_uid' => isset($row->transaction_uid) ? $row->transaction_uid : null,
                'transaction_id' => isset($row->transaction_id) ? $row->transaction_id : null,
                'type' => isset($row->type) ? $row->type : null,
                'status' => isset($row->status) ? $row->status : null,
                'amount' => isset($row->amount) ? $this->decimalNormalize($row->amount) : null,
                'currency' => isset($row->currency) ? $row->currency : null,
                'session_id' => isset($row->session_id) ? $row->session_id : null,
                'game_uid' => isset($row->game_uid) ? $row->game_uid : null,
                'round_id' => isset($row->round_id) ? $row->round_id : null,
                'attempts' => isset($row->attempts) ? (int) $row->attempts : null,
                'detail_endpoint' => isset($row->transaction_uid) ? $this->transactionDetailEndpoint($row->transaction_uid) : null,
                'created_at' => isset($row->created_at) ? $this->isoTime($row->created_at) : null,
            ];
        })->values();
    }

    public function transactionDetail(Request $request, $transactionUid, $limit = 20)
    {
        $operator = $request->attributes->get('b2b_operator');
        if (!$operator || !isset($operator->id)) {
            return null;
        }

        $transaction = $this->walletLookup->findForOperator((int) $operator->id, $transactionUid);
        if (!$transaction) {
            return null;
        }

        $limit = max(1, min(50, (int) $limit));
        $summary = $this->portalTransactionSummary($transaction);
        $canonicalUid = $summary['transaction_uid'] ?: (string) $transactionUid;

        return [
            'transaction' => $summary,
            'next_actions' => $this->portalNextActions(isset($transaction->status) ? $transaction->status : null),
            'transitions' => $this->portalTransitionSummaries($this->walletLookup->transitions($transaction, $limit)),
            'attempts' => $this->portalAttemptSummaries($this->walletLookup->attempts($transaction, $limit)),
            'callback_logs' => $this->portalCallbackLogSummaries($this->walletLookup->callbackLogs($transaction, $limit)),
            'reconciliation_items' => $this->portalReconciliationSummaries($this->walletLookup->reconciliationItems($transaction, $limit)),
            'manual_actions' => $this->portalManualActionSummaries($this->walletLookup->manualActions($transaction, $limit)),
            'detail_endpoint' => $this->transactionDetailEndpoint($canonicalUid),
            'report_detail_endpoint' => '/api/b2b/v1/reports/transactions/' . rawurlencode($canonicalUid),
            'limit' => $limit,
        ];
    }

    private function supportSummary($operatorId, $limit)
    {
        $summary = [
            'by_status' => [],
            'by_event_type' => [],
            'recent_events' => [],
            'tickets_by_status' => [],
            'tickets_by_priority' => [],
            'recent_tickets' => [],
        ];

        if (Schema::hasTable('b2b_operator_health_events')) {
            $rows = DB::table('b2b_operator_health_events')
                ->where('operator_id', $operatorId)
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get($this->selectExisting('b2b_operator_health_events', [
                    'event_type',
                    'status',
                    'failure_count',
                    'message',
                    'created_at',
                ]));

            $summary['by_status'] = $this->groupCounts('b2b_operator_health_events', $operatorId, 'status');
            $summary['by_event_type'] = $this->groupCounts('b2b_operator_health_events', $operatorId, 'event_type');
            $summary['recent_events'] = $rows->map(function ($row) {
                return [
                    'event_type' => isset($row->event_type) ? $row->event_type : null,
                    'status' => isset($row->status) ? $row->status : null,
                    'failure_count' => isset($row->failure_count) ? (int) $row->failure_count : null,
                    'message' => isset($row->message) ? $this->safeErrorSummary($row->message) : null,
                    'created_at' => isset($row->created_at) ? $this->isoTime($row->created_at) : null,
                ];
            })->values();
        }

        if (Schema::hasTable('b2b_operator_support_tickets')) {
            $ticketRows = DB::table('b2b_operator_support_tickets')
                ->where('operator_id', $operatorId)
                ->orderBy('last_message_at', 'desc')
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get($this->selectExisting('b2b_operator_support_tickets', [
                    'id',
                    'ticket_uid',
                    'subject',
                    'status',
                    'priority',
                    'category',
                    'external_reference',
                    'last_message_at',
                    'closed_at',
                    'created_at',
                ]));

            $ticketIds = $ticketRows->pluck('id')->filter()->map(function ($id) {
                return (int) $id;
            })->values()->all();
            $messageCounts = $this->supportTicketMessageCounts($operatorId, $ticketIds);
            $latestMessages = $this->latestSupportTicketMessages($operatorId, $ticketIds);

            $summary['tickets_by_status'] = $this->groupCounts('b2b_operator_support_tickets', $operatorId, 'status');
            $summary['tickets_by_priority'] = $this->groupCounts('b2b_operator_support_tickets', $operatorId, 'priority');
            $summary['recent_tickets'] = $ticketRows->map(function ($row) use ($messageCounts, $latestMessages) {
                $ticketId = isset($row->id) ? (int) $row->id : 0;

                return [
                    'ticket_uid' => isset($row->ticket_uid) ? $row->ticket_uid : null,
                    'subject' => isset($row->subject) ? $this->safeErrorSummary($row->subject) : null,
                    'status' => isset($row->status) ? $row->status : null,
                    'priority' => isset($row->priority) ? $row->priority : null,
                    'category' => isset($row->category) ? $row->category : null,
                    'external_reference' => isset($row->external_reference) ? $this->safeErrorSummary($row->external_reference) : null,
                    'message_count' => isset($messageCounts[$ticketId]) ? (int) $messageCounts[$ticketId] : 0,
                    'latest_message' => isset($latestMessages[$ticketId]) ? $latestMessages[$ticketId] : null,
                    'detail_endpoint' => isset($row->ticket_uid) ? $this->supportTicketDetailEndpoint($row->ticket_uid) : null,
                    'thread_endpoint' => isset($row->ticket_uid) ? $this->supportTicketThreadEndpoint($row->ticket_uid) : null,
                    'last_message_at' => isset($row->last_message_at) ? $this->isoTime($row->last_message_at) : null,
                    'closed_at' => isset($row->closed_at) ? $this->isoTime($row->closed_at) : null,
                    'created_at' => isset($row->created_at) ? $this->isoTime($row->created_at) : null,
                ];
            })->values();
        }

        return $summary;
    }

    private function auditSummary($operatorId, $limit)
    {
        if (!Schema::hasTable('b2b_operator_audit_events')) {
            return [
                'by_event_type' => [],
                'recent_events' => [],
            ];
        }

        $rows = DB::table('b2b_operator_audit_events')
            ->where('operator_id', $operatorId)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get($this->selectExisting('b2b_operator_audit_events', [
                'event_type',
                'subject_type',
                'subject_id',
                'actor',
                'reason',
                'metadata',
                'created_at',
            ]));

        return [
            'by_event_type' => $this->groupCounts('b2b_operator_audit_events', $operatorId, 'event_type'),
            'recent_events' => $rows->map(function ($row) {
                return [
                    'event_type' => isset($row->event_type) ? $row->event_type : null,
                    'subject_type' => isset($row->subject_type) ? $row->subject_type : null,
                    'subject_id' => isset($row->subject_id) ? $this->safeErrorSummary($row->subject_id) : null,
                    'actor' => isset($row->actor) ? $this->safeErrorSummary($row->actor) : null,
                    'reason' => isset($row->reason) ? $this->safeErrorSummary($row->reason) : null,
                    'metadata_summary' => isset($row->metadata) ? $this->safeMetadataSummary($row->metadata) : null,
                    'created_at' => isset($row->created_at) ? $this->isoTime($row->created_at) : null,
                ];
            })->values(),
        ];
    }

    private function supportTicketMessageCounts($operatorId, array $ticketIds)
    {
        $ticketIds = array_values(array_filter(array_map('intval', $ticketIds)));
        if (!$ticketIds || !Schema::hasTable('b2b_operator_support_ticket_messages')) {
            return [];
        }

        foreach (['ticket_id', 'operator_id'] as $column) {
            if (!Schema::hasColumn('b2b_operator_support_ticket_messages', $column)) {
                return [];
            }
        }

        $rows = DB::table('b2b_operator_support_ticket_messages')
            ->where('operator_id', $operatorId)
            ->whereIn('ticket_id', $ticketIds)
            ->select('ticket_id', DB::raw('COUNT(*) as count'))
            ->groupBy('ticket_id')
            ->get();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row->ticket_id] = (int) $row->count;
        }

        return $counts;
    }

    private function latestSupportTicketMessages($operatorId, array $ticketIds)
    {
        $ticketIds = array_values(array_filter(array_map('intval', $ticketIds)));
        if (!$ticketIds || !Schema::hasTable('b2b_operator_support_ticket_messages')) {
            return [];
        }

        foreach (['id', 'ticket_id', 'operator_id'] as $column) {
            if (!Schema::hasColumn('b2b_operator_support_ticket_messages', $column)) {
                return [];
            }
        }

        $latestIdRows = DB::table('b2b_operator_support_ticket_messages')
            ->where('operator_id', $operatorId)
            ->whereIn('ticket_id', $ticketIds)
            ->select('ticket_id', DB::raw('MAX(id) as latest_id'))
            ->groupBy('ticket_id')
            ->get();

        $latestIds = [];
        foreach ($latestIdRows as $row) {
            if (isset($row->latest_id) && $row->latest_id !== null) {
                $latestIds[] = (int) $row->latest_id;
            }
        }

        if (!$latestIds) {
            return [];
        }

        $rows = DB::table('b2b_operator_support_ticket_messages')
            ->where('operator_id', $operatorId)
            ->whereIn('id', $latestIds)
            ->get($this->selectExisting('b2b_operator_support_ticket_messages', [
                'ticket_id',
                'actor',
                'source',
                'message',
                'created_at',
            ]));

        $messages = [];
        foreach ($rows as $row) {
            $ticketId = isset($row->ticket_id) ? (int) $row->ticket_id : 0;
            if (!$ticketId) {
                continue;
            }

            $messages[$ticketId] = [
                'actor' => isset($row->actor) ? $this->safeErrorSummary($row->actor) : null,
                'source' => isset($row->source) ? $this->safeErrorSummary($row->source) : null,
                'message' => isset($row->message) ? $this->safeErrorSummary($row->message) : null,
                'created_at' => isset($row->created_at) ? $this->isoTime($row->created_at) : null,
            ];
        }

        return $messages;
    }

    private function countWhere($table, $operatorId, callable $callback = null)
    {
        if (!Schema::hasTable($table)) {
            return 0;
        }

        $query = DB::table($table)->where('operator_id', $operatorId);
        if ($callback) {
            $callback($query);
        }

        return (int) $query->count();
    }

    private function groupCounts($table, $operatorId, $column)
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return [];
        }

        $rows = DB::table($table)
            ->where('operator_id', $operatorId)
            ->select($column, DB::raw('COUNT(*) as count'))
            ->groupBy($column)
            ->orderBy($column)
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $key = isset($row->{$column}) && $row->{$column} !== null ? (string) $row->{$column} : 'unknown';
            $result[$key] = ['count' => (int) $row->count];
        }

        return $result;
    }

    private function groupCountsBetween($table, $operatorId, $column, Carbon $fromDate, Carbon $toDate)
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return [];
        }

        $query = DB::table($table)
            ->where('operator_id', $operatorId)
            ->select($column, DB::raw('COUNT(*) as count'))
            ->groupBy($column)
            ->orderBy($column);

        if (Schema::hasColumn($table, 'created_at')) {
            $query->whereBetween('created_at', [$fromDate, $toDate]);
        }

        $rows = $query->get();
        $result = [];
        foreach ($rows as $row) {
            $key = isset($row->{$column}) && $row->{$column} !== null ? (string) $row->{$column} : 'unknown';
            $result[$key] = ['count' => (int) $row->count];
        }

        return $result;
    }

    private function selectExisting($table, array $columns)
    {
        $select = [];
        foreach ($columns as $column) {
            if (Schema::hasColumn($table, $column)) {
                $select[] = $column;
            } else {
                $select[] = DB::raw('NULL as ' . $column);
            }
        }

        return $select;
    }

    private function period(Carbon $fromDate, Carbon $toDate)
    {
        return [
            'from' => $fromDate->toIso8601String(),
            'to' => $toDate->toIso8601String(),
        ];
    }

    private function arrayValue($value)
    {
        if (is_array($value)) {
            return array_values($value);
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? array_values($decoded) : [];
        }

        return [];
    }

    private function scopeList($value)
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $scopes = json_last_error() === JSON_ERROR_NONE && is_array($decoded)
                ? $decoded
                : preg_split('/[\s,]+/', $value);
        } else {
            $scopes = $this->arrayValue($value);
        }

        return array_values(array_filter(array_map(function ($scope) {
            return trim((string) $scope);
        }, $scopes), function ($scope) {
            return $scope !== '';
        }));
    }

    private function isoTime($value)
    {
        if (!$value) {
            return null;
        }

        try {
            return $value instanceof Carbon
                ? $value->toIso8601String()
                : Carbon::parse($value)->toIso8601String();
        } catch (\Exception $e) {
            return null;
        }
    }

    private function decimalNormalize($value, $scale = 8)
    {
        if ($value === null) {
            return null;
        }

        if (function_exists('bcadd')) {
            return bcadd('0', (string) $value, $scale);
        }

        $value = trim((string) $value);
        if ($value === '') {
            $value = '0';
        }

        $negative = strpos($value, '-') === 0;
        $value = ltrim($value, '+-');
        $parts = explode('.', $value, 2);
        $major = preg_replace('/[^0-9]/', '', $parts[0]);
        $minor = isset($parts[1]) ? preg_replace('/[^0-9]/', '', $parts[1]) : '';
        $minor = substr(str_pad($minor, $scale, '0'), 0, $scale);
        $major = ltrim($major, '0') ?: '0';
        $isZero = $major === '0' && trim($minor, '0') === '';

        return ($negative && !$isZero ? '-' : '') . $major . '.' . $minor;
    }

    private function callbackStatusBucket($status)
    {
        if ($status === null) {
            return 'unknown';
        }

        $status = (int) $status;
        if ($status === 0) {
            return 'network_error';
        }
        if ($status >= 200 && $status < 300) {
            return 'success';
        }
        if ($status >= 400 && $status < 500) {
            return 'client_error';
        }
        if ($status >= 500) {
            return 'server_error';
        }

        return 'other';
    }

    private function safeEndpoint($url)
    {
        if (!$url) {
            return null;
        }

        $parts = parse_url((string) $url);
        if ($parts === false) {
            return substr($this->redactor->storageValue((string) $url), 0, 160);
        }

        if (!isset($parts['host'])) {
            return isset($parts['path']) ? $parts['path'] : null;
        }

        $endpoint = (isset($parts['scheme']) ? $parts['scheme'] : 'https') . '://' . $parts['host'];
        if (isset($parts['port'])) {
            $endpoint .= ':' . $parts['port'];
        }

        return $endpoint . (isset($parts['path']) ? $parts['path'] : '/');
    }

    private function safeErrorSummary($value)
    {
        if (!$value) {
            return null;
        }

        $summary = $this->redactor->storageValue((string) $value);

        return strlen($summary) > 160 ? substr($summary, 0, 157) . '...' : $summary;
    }

    private function safeMetadataSummary($value)
    {
        if (!$value) {
            return null;
        }

        $decoded = null;
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                return $this->safeErrorSummary($value);
            }
        } elseif (is_array($value)) {
            $decoded = $value;
        }

        if (!is_array($decoded)) {
            return null;
        }

        $redacted = $this->redactor->redact($decoded);
        $summary = json_encode($redacted, JSON_UNESCAPED_SLASHES);
        if ($summary === false) {
            return null;
        }

        return strlen($summary) > 240 ? substr($summary, 0, 237) . '...' : $summary;
    }

    private function caseSummaryPayload($row)
    {
        return [
            'transaction_uid' => isset($row->transaction_uid) ? $row->transaction_uid : null,
            'status' => isset($row->status) ? $row->status : null,
            'reason' => isset($row->reason) ? $this->safeErrorSummary($row->reason) : null,
            'priority' => isset($row->priority) ? $row->priority : null,
            'state' => isset($row->state) ? $row->state : null,
            'support_case_detail_endpoint' => isset($row->transaction_uid) ? $this->supportCaseDetailEndpoint($row->transaction_uid) : null,
            'support_case_thread_endpoint' => isset($row->transaction_uid) ? $this->supportCaseThreadEndpoint($row->transaction_uid) : null,
            'detected_at' => isset($row->detected_at) ? $this->isoTime($row->detected_at) : null,
            'resolved_at' => isset($row->resolved_at) ? $this->isoTime($row->resolved_at) : null,
            'updated_at' => isset($row->updated_at) ? $this->isoTime($row->updated_at) : null,
        ];
    }

    private function portalTransactionSummary($transaction)
    {
        return [
            'transaction_uid' => isset($transaction->transaction_uid) ? $transaction->transaction_uid : null,
            'transaction_id' => isset($transaction->transaction_id) ? $transaction->transaction_id : null,
            'player_id' => isset($transaction->player_id) ? $this->safeErrorSummary($transaction->player_id) : null,
            'session_id' => isset($transaction->session_id) ? $transaction->session_id : null,
            'game_uid' => isset($transaction->game_uid) ? $transaction->game_uid : (isset($transaction->game_id) ? $transaction->game_id : null),
            'round_id' => isset($transaction->round_id) ? $transaction->round_id : null,
            'type' => isset($transaction->type) ? $transaction->type : null,
            'amount' => isset($transaction->amount) ? $this->decimalNormalize($transaction->amount) : null,
            'currency' => isset($transaction->currency) ? $transaction->currency : null,
            'status' => isset($transaction->status) ? $transaction->status : null,
            'attempts' => isset($transaction->attempts) ? (int) $transaction->attempts : null,
            'last_error_summary' => isset($transaction->last_error) ? $this->safeErrorSummary($transaction->last_error) : null,
            'processed_at' => isset($transaction->processed_at) ? $this->isoTime($transaction->processed_at) : null,
            'created_at' => isset($transaction->created_at) ? $this->isoTime($transaction->created_at) : null,
            'updated_at' => isset($transaction->updated_at) ? $this->isoTime($transaction->updated_at) : null,
        ];
    }

    private function portalNextActions($status)
    {
        if (in_array($status, ['failed', 'timeout', 'unknown'], true)) {
            return ['retry_wallet', 'reconcile_wallet', 'manual_review'];
        }

        if (in_array($status, ['dead_letter', 'manual_review', 'rollback_required'], true)) {
            return ['manual_review', 'reconcile_wallet'];
        }

        if ($status === 'pending') {
            return ['wait_for_callback', 'reconcile_if_stale'];
        }

        if (in_array($status, ['success', 'reversed'], true)) {
            return [];
        }

        return ['inspect_transaction'];
    }

    private function portalTransitionSummaries($rows)
    {
        return collect($rows)->map(function ($row) {
            return [
                'from_status' => isset($row->from_status) ? $row->from_status : null,
                'to_status' => isset($row->to_status) ? $row->to_status : null,
                'reason' => isset($row->reason) ? $this->safeErrorSummary($row->reason) : null,
                'actor' => isset($row->actor) ? $this->safeErrorSummary($row->actor) : null,
                'context_summary' => isset($row->context) ? $this->safeMetadataSummary($row->context) : null,
                'created_at' => isset($row->created_at) ? $this->isoTime($row->created_at) : null,
            ];
        })->values();
    }

    private function portalAttemptSummaries($rows)
    {
        return collect($rows)->map(function ($row) {
            return [
                'attempt_no' => isset($row->attempt_no) ? (int) $row->attempt_no : null,
                'type' => isset($row->type) ? $row->type : null,
                'endpoint' => isset($row->url) ? $this->safeEndpoint($row->url) : null,
                'http_status' => isset($row->http_status) ? (int) $row->http_status : null,
                'result' => isset($row->result) ? $row->result : null,
                'duration_ms' => isset($row->duration_ms) ? (int) $row->duration_ms : null,
                'error_summary' => isset($row->error) ? $this->safeErrorSummary($row->error) : null,
                'started_at' => isset($row->started_at) ? $this->isoTime($row->started_at) : null,
                'finished_at' => isset($row->finished_at) ? $this->isoTime($row->finished_at) : null,
                'created_at' => isset($row->created_at) ? $this->isoTime($row->created_at) : null,
            ];
        })->values();
    }

    private function portalCallbackLogSummaries($rows)
    {
        return collect($rows)->map(function ($row) {
            return [
                'direction' => isset($row->direction) ? $row->direction : null,
                'endpoint' => isset($row->endpoint) ? $this->safeEndpoint($row->endpoint) : null,
                'http_status' => isset($row->http_status) ? (int) $row->http_status : null,
                'result' => isset($row->http_status) ? $this->callbackStatusBucket($row->http_status) : 'unknown',
                'duration_ms' => isset($row->duration_ms) ? (int) $row->duration_ms : null,
                'error_summary' => isset($row->error_message) ? $this->safeErrorSummary($row->error_message) : null,
                'created_at' => isset($row->created_at) ? $this->isoTime($row->created_at) : null,
            ];
        })->values();
    }

    private function portalReconciliationSummaries($rows)
    {
        return collect($rows)->map(function ($row) {
            return [
                'transaction_uid' => isset($row->transaction_uid) ? $row->transaction_uid : null,
                'status' => isset($row->status) ? $row->status : null,
                'reason' => isset($row->reason) ? $this->safeErrorSummary($row->reason) : null,
                'priority' => isset($row->priority) ? $row->priority : null,
                'state' => isset($row->state) ? $row->state : null,
                'detected_at' => isset($row->detected_at) ? $this->isoTime($row->detected_at) : null,
                'resolved_at' => isset($row->resolved_at) ? $this->isoTime($row->resolved_at) : null,
                'updated_at' => isset($row->updated_at) ? $this->isoTime($row->updated_at) : null,
            ];
        })->values();
    }

    private function portalManualActionSummaries($rows)
    {
        return collect($rows)->map(function ($row) {
            return [
                'action' => isset($row->action) ? $row->action : null,
                'from_status' => isset($row->from_status) ? $row->from_status : null,
                'to_status' => isset($row->to_status) ? $row->to_status : null,
                'actor' => isset($row->actor) ? $this->safeErrorSummary($row->actor) : null,
                'reason' => isset($row->reason) ? $this->safeErrorSummary($row->reason) : null,
                'context_summary' => isset($row->context) ? $this->safeMetadataSummary($row->context) : null,
                'created_at' => isset($row->created_at) ? $this->isoTime($row->created_at) : null,
                'updated_at' => isset($row->updated_at) ? $this->isoTime($row->updated_at) : null,
            ];
        })->values();
    }

    private function transactionDetailEndpoint($transactionUid)
    {
        $transactionUid = trim((string) $transactionUid);

        return $transactionUid === ''
            ? null
            : '/api/b2b/v1/portal/transactions/' . rawurlencode($transactionUid);
    }

    private function supportCaseDetailEndpoint($transactionUid)
    {
        $transactionUid = trim((string) $transactionUid);

        return $transactionUid === ''
            ? null
            : '/api/b2b/v1/portal/support/cases/' . rawurlencode($transactionUid);
    }

    private function supportCaseThreadEndpoint($transactionUid)
    {
        $detailEndpoint = $this->supportCaseDetailEndpoint($transactionUid);

        return $detailEndpoint === null ? null : $detailEndpoint . '/thread';
    }

    private function supportTicketDetailEndpoint($ticketUid)
    {
        $ticketUid = trim((string) $ticketUid);

        return $ticketUid === ''
            ? null
            : '/api/b2b/v1/portal/support/tickets/' . rawurlencode($ticketUid);
    }

    private function supportTicketThreadEndpoint($ticketUid)
    {
        $detailEndpoint = $this->supportTicketDetailEndpoint($ticketUid);

        return $detailEndpoint === null ? null : $detailEndpoint . '/thread';
    }

}
