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
    private $gameAvailability;
    private $providerHealth;

    public function __construct(
        B2BReportQuery $reports,
        B2BPayloadRedactor $redactor,
        WalletTransactionLookupService $walletLookup,
        B2BGameAvailabilityService $gameAvailability,
        B2BProviderHealthService $providerHealth
    )
    {
        $this->reports = $reports;
        $this->redactor = $redactor;
        $this->walletLookup = $walletLookup;
        $this->gameAvailability = $gameAvailability;
        $this->providerHealth = $providerHealth;
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
            'launch_diagnostics' => $this->providerRequestSummary($operatorId, $limit),
            'provider_health' => $this->providerHealth->summary(),
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
                'portal_diagnostics' => '/api/b2b/v1/portal/diagnostics',
                'portal_reports' => '/api/b2b/v1/portal/reports',
                'portal_support' => '/api/b2b/v1/portal/support',
                'portal_logs' => '/api/b2b/v1/portal/logs',
                'portal_docs' => '/api/b2b/v1/portal/docs',
                'portal_openapi_download' => '/api/b2b/v1/portal/docs/openapi.json',
                'portal_postman_download' => '/api/b2b/v1/portal/docs/postman_collection.json',
                'portal_game_detail_template' => '/api/b2b/v1/portal/games/{game_uid}',
                'portal_diagnostic_detail_template' => '/api/b2b/v1/portal/diagnostics/{request_uid}',
                'portal_session_detail_template' => '/api/b2b/v1/portal/sessions/{session_uid}',
                'portal_transaction_detail_template' => '/api/b2b/v1/portal/transactions/{transaction_uid}',
                'portal_settlement_detail_template' => '/api/b2b/v1/portal/settlements/{settlement_uid}',
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
                    'detail_endpoint' => $this->gameDetailEndpoint($row->game_uid),
                    'updated_at' => $this->isoTime($row->updated_at),
                ];
            })->values(),
        ];
    }

    public function gameDetail(Request $request, $gameUid, $limit = 20)
    {
        $operator = $request->attributes->get('b2b_operator');
        if (!$operator || !isset($operator->id)) {
            return null;
        }

        $gameUid = trim((string) $gameUid);
        if ($gameUid === '') {
            return null;
        }

        $operatorId = (int) $operator->id;
        $limit = max(1, min(50, (int) $limit));
        $assignment = $this->operatorGameAssignment($operatorId, $gameUid);
        $catalog = $this->catalogGame($gameUid);
        $legacy = $this->legacyGame($operator, $gameUid);

        if (!$assignment && !$legacy && !$this->operatorHasGameActivity($operatorId, $gameUid)) {
            return null;
        }

        $canonicalUid = $this->canonicalGameUid($gameUid, $catalog, $legacy, $assignment);

        return [
            'game' => $this->portalGameSummary($canonicalUid, $catalog, $legacy, $assignment),
            'assignment' => $this->portalGameAssignmentSummary($assignment),
            'availability' => [
                'real' => $this->portalGameAvailability($operator, $canonicalUid, 'real'),
                'demo' => $this->portalGameAvailability($operator, $canonicalUid, 'demo'),
            ],
            'session_summary' => $this->gameSessionSummary($operatorId, $canonicalUid),
            'transaction_summary' => $this->gameTransactionSummary($operatorId, $canonicalUid),
            'recent_sessions' => $this->gameRecentSessions($operatorId, $canonicalUid, $limit),
            'recent_transactions' => $this->gameRecentTransactions($operatorId, $canonicalUid, $limit),
            'detail_endpoint' => $this->gameDetailEndpoint($canonicalUid),
            'api_detail_endpoint' => '/api/b2b/v1/games/' . rawurlencode($canonicalUid),
            'limit' => $limit,
        ];
    }

    private function operatorGameAssignment($operatorId, $gameUid)
    {
        if (!Schema::hasTable('b2b_operator_game_assignments')) {
            return null;
        }

        return DB::table('b2b_operator_game_assignments')
            ->where('operator_id', $operatorId)
            ->where('game_uid', $gameUid)
            ->orderBy('updated_at', 'desc')
            ->first($this->selectExisting('b2b_operator_game_assignments', [
                'game_uid',
                'provider',
                'status',
                'demo_enabled',
                'real_enabled',
                'allowed_currencies',
                'allowed_countries',
                'metadata',
                'created_at',
                'updated_at',
            ]));
    }

    private function catalogGame($gameUid)
    {
        if (!Schema::hasTable('b2b_game_catalog')) {
            return null;
        }

        return DB::table('b2b_game_catalog')
            ->where('game_uid', $gameUid)
            ->first($this->selectExisting('b2b_game_catalog', [
                'game_uid',
                'provider_game_id',
                'canonical_game_id',
                'provider',
                'slug',
                'title',
                'category',
                'platform',
                'rtp',
                'volatility',
                'thumbnail_url',
                'launch_config',
                'demo_supported',
                'real_supported',
                'supported_currencies',
                'supported_countries',
                'status',
                'metadata',
                'created_at',
                'updated_at',
            ]));
    }

    private function legacyGame($operator, $gameUid)
    {
        if (
            !$operator
            || !isset($operator->shop_id)
            || !$operator->shop_id
            || !Schema::hasTable('games')
            || !Schema::hasColumn('games', 'name')
        ) {
            return null;
        }

        $query = DB::table('games')
            ->where('name', $gameUid);

        if (Schema::hasColumn('games', 'shop_id')) {
            $query->where('shop_id', $operator->shop_id);
        }

        if (Schema::hasColumn('games', 'view')) {
            $query->where('view', 1);
        }

        return $query->first($this->selectExisting('games', [
            'name',
            'title',
            'category',
            'shop_id',
            'view',
            'created_at',
            'updated_at',
        ]));
    }

    private function operatorHasGameActivity($operatorId, $gameUid)
    {
        if (Schema::hasTable('b2b_game_sessions') && Schema::hasColumn('b2b_game_sessions', 'game_uid')) {
            if (DB::table('b2b_game_sessions')->where('operator_id', $operatorId)->where('game_uid', $gameUid)->exists()) {
                return true;
            }
        }

        if (Schema::hasTable('b2b_wallet_transactions') && Schema::hasColumn('b2b_wallet_transactions', 'game_uid')) {
            if (DB::table('b2b_wallet_transactions')->where('operator_id', $operatorId)->where('game_uid', $gameUid)->exists()) {
                return true;
            }
        }

        return false;
    }

    private function canonicalGameUid($gameUid, $catalog, $legacy, $assignment)
    {
        if ($catalog && isset($catalog->game_uid) && $catalog->game_uid) {
            return $catalog->game_uid;
        }

        if ($legacy && isset($legacy->name) && $legacy->name) {
            return $legacy->name;
        }

        if ($assignment && isset($assignment->game_uid) && $assignment->game_uid) {
            return $assignment->game_uid;
        }

        return (string) $gameUid;
    }

    private function portalGameSummary($gameUid, $catalog, $legacy, $assignment)
    {
        $metadata = $catalog && isset($catalog->metadata)
            ? $catalog->metadata
            : ($assignment && isset($assignment->metadata) ? $assignment->metadata : null);

        return [
            'game_uid' => $gameUid,
            'provider_game_id' => $catalog && isset($catalog->provider_game_id) ? $catalog->provider_game_id : null,
            'canonical_game_id' => $catalog && isset($catalog->canonical_game_id) ? $catalog->canonical_game_id : null,
            'title' => $catalog && isset($catalog->title) ? $catalog->title : ($legacy && isset($legacy->title) ? $legacy->title : $gameUid),
            'provider' => $catalog && isset($catalog->provider) ? $catalog->provider : ($assignment && isset($assignment->provider) ? $assignment->provider : 'goldsvet_internal'),
            'slug' => $catalog && isset($catalog->slug) ? $catalog->slug : null,
            'category' => $catalog && isset($catalog->category) ? $catalog->category : ($legacy && isset($legacy->category) ? $legacy->category : null),
            'platform' => $catalog && isset($catalog->platform) ? $catalog->platform : null,
            'rtp' => $catalog && isset($catalog->rtp) ? $this->decimalNormalize($catalog->rtp, 2) : null,
            'volatility' => $catalog && isset($catalog->volatility) ? $catalog->volatility : null,
            'thumbnail_url' => $catalog && isset($catalog->thumbnail_url) ? $this->safeEndpoint($catalog->thumbnail_url) : null,
            'launch_config_summary' => $catalog && isset($catalog->launch_config) ? $this->safeMetadataSummary($catalog->launch_config) : [],
            'demo_supported' => $catalog && isset($catalog->demo_supported) ? (bool) $catalog->demo_supported : ($assignment && isset($assignment->demo_enabled) ? (bool) $assignment->demo_enabled : null),
            'real_supported' => $catalog && isset($catalog->real_supported) ? (bool) $catalog->real_supported : ($assignment && isset($assignment->real_enabled) ? (bool) $assignment->real_enabled : null),
            'supported_currencies' => $catalog && isset($catalog->supported_currencies) ? $this->arrayValue($catalog->supported_currencies) : [],
            'supported_countries' => $catalog && isset($catalog->supported_countries) ? $this->arrayValue($catalog->supported_countries) : [],
            'status' => $catalog && isset($catalog->status) ? $catalog->status : ($assignment && isset($assignment->status) ? $assignment->status : null),
            'source' => $catalog ? 'b2b_game_catalog' : ($legacy ? 'games' : 'b2b_operator_game_assignments'),
            'metadata_summary' => $this->safeMetadataSummary($metadata),
            'created_at' => $catalog && isset($catalog->created_at) ? $this->isoTime($catalog->created_at) : ($legacy && isset($legacy->created_at) ? $this->isoTime($legacy->created_at) : null),
            'updated_at' => $catalog && isset($catalog->updated_at) ? $this->isoTime($catalog->updated_at) : ($legacy && isset($legacy->updated_at) ? $this->isoTime($legacy->updated_at) : null),
        ];
    }

    private function portalGameAssignmentSummary($assignment)
    {
        if (!$assignment) {
            return null;
        }

        return [
            'game_uid' => isset($assignment->game_uid) ? $assignment->game_uid : null,
            'provider' => isset($assignment->provider) ? $assignment->provider : null,
            'status' => isset($assignment->status) ? $assignment->status : null,
            'demo_enabled' => isset($assignment->demo_enabled) ? (bool) $assignment->demo_enabled : null,
            'real_enabled' => isset($assignment->real_enabled) ? (bool) $assignment->real_enabled : null,
            'allowed_currencies' => isset($assignment->allowed_currencies) ? $this->arrayValue($assignment->allowed_currencies) : [],
            'allowed_countries' => isset($assignment->allowed_countries) ? $this->arrayValue($assignment->allowed_countries) : [],
            'metadata_summary' => isset($assignment->metadata) ? $this->safeMetadataSummary($assignment->metadata) : null,
            'created_at' => isset($assignment->created_at) ? $this->isoTime($assignment->created_at) : null,
            'updated_at' => isset($assignment->updated_at) ? $this->isoTime($assignment->updated_at) : null,
        ];
    }

    private function portalGameAvailability($operator, $gameUid, $mode)
    {
        $currency = $operator && isset($operator->default_currency) ? $operator->default_currency : null;
        $result = $this->gameAvailability->availableForLaunch($operator, $gameUid, $currency, null, $mode);

        return [
            'mode' => $mode,
            'currency' => $currency,
            'ok' => isset($result['ok']) ? (bool) $result['ok'] : false,
            'provider' => isset($result['provider']) ? $result['provider'] : null,
            'source' => isset($result['source']) ? $result['source'] : null,
            'code' => isset($result['code']) ? $result['code'] : null,
            'message' => isset($result['message']) ? $this->safeErrorSummary($result['message']) : null,
        ];
    }

    private function gameSessionSummary($operatorId, $gameUid)
    {
        if (!Schema::hasTable('b2b_game_sessions')) {
            return [
                'count' => 0,
                'by_status' => [],
                'by_provider' => [],
            ];
        }

        $base = $this->gameSessionBaseQuery($operatorId, $gameUid);
        $summary = [
            'count' => (clone $base)->count(),
            'by_status' => [],
            'by_provider' => [],
        ];

        if (Schema::hasColumn('b2b_game_sessions', 'status')) {
            $summary['by_status'] = $this->rowsToCountMap(
                (clone $base)->select('status', DB::raw('COUNT(*) as count'))->groupBy('status')->orderBy('status')->get(),
                'status'
            );
        }

        if (Schema::hasColumn('b2b_game_sessions', 'provider')) {
            $summary['by_provider'] = $this->rowsToCountMap(
                (clone $base)->select('provider', DB::raw('COUNT(*) as count'))->groupBy('provider')->orderBy('provider')->get(),
                'provider'
            );
        }

        return $summary;
    }

    private function gameTransactionSummary($operatorId, $gameUid)
    {
        if (!Schema::hasTable('b2b_wallet_transactions')) {
            return [
                'count' => 0,
                'by_status' => [],
                'by_type' => [],
                'success_amounts' => [],
            ];
        }

        $base = $this->gameTransactionBaseQuery($operatorId, $gameUid);
        $summary = [
            'count' => (clone $base)->count(),
            'by_status' => [],
            'by_type' => [],
            'success_amounts' => [],
        ];

        if (Schema::hasColumn('b2b_wallet_transactions', 'status')) {
            $summary['by_status'] = $this->rowsToCountMap(
                (clone $base)->select('status', DB::raw('COUNT(*) as count'))->groupBy('status')->orderBy('status')->get(),
                'status'
            );
        }

        if (Schema::hasColumn('b2b_wallet_transactions', 'type')) {
            $summary['by_type'] = $this->rowsToCountMap(
                (clone $base)->select('type', DB::raw('COUNT(*) as count'))->groupBy('type')->orderBy('type')->get(),
                'type'
            );
        }

        if (Schema::hasColumn('b2b_wallet_transactions', 'amount') && Schema::hasColumn('b2b_wallet_transactions', 'currency')) {
            $summary['success_amounts'] = $this->rowsToAmountMap(
                (clone $base)
                    ->where('status', 'success')
                    ->select('type', 'currency', DB::raw('COALESCE(SUM(amount), 0) as amount'), DB::raw('COUNT(*) as count'))
                    ->groupBy('type', 'currency')
                    ->orderBy('type')
                    ->orderBy('currency')
                    ->get()
            );
        }

        return $summary;
    }

    private function gameRecentSessions($operatorId, $gameUid, $limit)
    {
        if (!Schema::hasTable('b2b_game_sessions')) {
            return collect();
        }

        $query = DB::table('b2b_game_sessions as s')
            ->where('s.operator_id', $operatorId)
            ->where('s.game_uid', $gameUid)
            ->orderBy('s.created_at', 'desc')
            ->orderBy('s.id', 'desc')
            ->limit((int) $limit);

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
                DB::raw('p.external_player_id as external_player_id'),
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
                DB::raw('NULL as external_player_id'),
            ];
        }

        return $query->get($select)->map(function ($row) {
            return [
                'session_uid' => isset($row->session_uid) ? $row->session_uid : null,
                'external_player_id' => isset($row->external_player_id) ? $this->safeErrorSummary($row->external_player_id) : null,
                'game_uid' => isset($row->game_uid) ? $row->game_uid : null,
                'provider' => isset($row->provider) ? $row->provider : null,
                'mode' => isset($row->mode) ? $row->mode : null,
                'status' => isset($row->status) ? $row->status : null,
                'currency' => isset($row->currency) ? $row->currency : null,
                'detail_endpoint' => isset($row->session_uid) ? $this->sessionDetailEndpoint($row->session_uid) : null,
                'created_at' => isset($row->created_at) ? $this->isoTime($row->created_at) : null,
            ];
        })->values();
    }

    private function gameRecentTransactions($operatorId, $gameUid, $limit)
    {
        if (!Schema::hasTable('b2b_wallet_transactions')) {
            return collect();
        }

        $rows = $this->gameTransactionBaseQuery($operatorId, $gameUid)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->limit((int) $limit)
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

    private function gameSessionBaseQuery($operatorId, $gameUid)
    {
        return DB::table('b2b_game_sessions')
            ->where('operator_id', $operatorId)
            ->where('game_uid', $gameUid);
    }

    private function gameTransactionBaseQuery($operatorId, $gameUid)
    {
        return DB::table('b2b_wallet_transactions')
            ->where('operator_id', $operatorId)
            ->where('game_uid', $gameUid);
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
                    'detail_endpoint' => isset($row->settlement_uid) ? $this->settlementDetailEndpoint($row->settlement_uid) : null,
                    'period_start' => isset($row->period_start) ? $this->isoTime($row->period_start) : null,
                    'period_end' => isset($row->period_end) ? $this->isoTime($row->period_end) : null,
                    'created_at' => isset($row->created_at) ? $this->isoTime($row->created_at) : null,
                ];
            })->values(),
        ];
    }

    public function settlementDetail(Request $request, $settlementUid)
    {
        $operator = $request->attributes->get('b2b_operator');
        if (!$operator || !isset($operator->id) || !Schema::hasTable('b2b_settlements')) {
            return null;
        }

        $settlement = DB::table('b2b_settlements')
            ->where('operator_id', (int) $operator->id)
            ->where('settlement_uid', $settlementUid)
            ->first($this->selectExisting('b2b_settlements', [
                'settlement_uid',
                'period_start',
                'period_end',
                'currency',
                'bets_amount',
                'wins_amount',
                'refunds_amount',
                'ggr_amount',
                'aggregator_fee_amount',
                'provider_fee_amount',
                'net_amount',
                'status',
                'export_format',
                'export_hash',
                'exported_at',
                'submitted_at',
                'submitted_by',
                'approved_at',
                'approved_by',
                'rejected_at',
                'rejected_by',
                'metadata',
                'created_at',
                'updated_at',
            ]));

        if (!$settlement) {
            return null;
        }

        $metadata = $this->metadataArray(isset($settlement->metadata) ? $settlement->metadata : null);
        $redactedMetadata = $this->redactor->redact($metadata);
        $canonicalUid = isset($settlement->settlement_uid) ? $settlement->settlement_uid : (string) $settlementUid;

        return [
            'settlement' => $this->portalSettlementSummary($settlement),
            'totals' => $this->settlementTotals($redactedMetadata),
            'by_type' => $this->settlementBreakdown($redactedMetadata),
            'approval' => $this->settlementApproval($redactedMetadata),
            'export' => $this->settlementExport($settlement, $redactedMetadata),
            'metadata_summary' => $this->settlementMetadataSummary($redactedMetadata),
            'detail_endpoint' => $this->settlementDetailEndpoint($canonicalUid),
            'report_detail_endpoint' => '/api/b2b/v1/reports/settlements/' . rawurlencode($canonicalUid),
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
                'detail_endpoint' => isset($row->session_uid) ? $this->sessionDetailEndpoint($row->session_uid) : null,
                'created_at' => $this->isoTime($row->created_at),
                'expires_at' => $this->isoTime($row->expires_at),
                'closed_at' => $this->isoTime($row->closed_at),
            ];
        })->values();
    }

    public function sessionDetail(Request $request, $sessionUid, $limit = 20)
    {
        $operator = $request->attributes->get('b2b_operator');
        if (!$operator || !isset($operator->id) || !Schema::hasTable('b2b_game_sessions')) {
            return null;
        }

        $operatorId = (int) $operator->id;
        $query = DB::table('b2b_game_sessions as s')
            ->where('s.operator_id', $operatorId)
            ->where(function ($q) use ($sessionUid) {
                $q->where('s.session_uid', $sessionUid);

                if (ctype_digit((string) $sessionUid)) {
                    $q->orWhere('s.id', (int) $sessionUid);
                }
            });

        if (Schema::hasTable('b2b_operator_players')) {
            $query->leftJoin('b2b_operator_players as p', 'p.id', '=', 's.operator_player_id');
            $select = array_merge($this->sessionSelectColumns(), ['p.external_player_id']);
        } else {
            $select = array_merge($this->sessionSelectColumns(), [DB::raw('NULL as external_player_id')]);
        }

        $session = $query->first($select);
        if (!$session) {
            return null;
        }

        $limit = max(1, min(50, (int) $limit));
        $summary = $this->portalSessionSummary($session);
        $canonicalUid = $summary['session_uid'] ?: (string) $sessionUid;

        return [
            'session' => $summary,
            'transactions' => $this->sessionTransactions($operatorId, $session, $limit),
            'transaction_summary' => $this->sessionTransactionSummary($operatorId, $session),
            'detail_endpoint' => $this->sessionDetailEndpoint($canonicalUid),
            'api_detail_endpoint' => '/api/b2b/v1/sessions/' . rawurlencode($canonicalUid),
            'limit' => $limit,
        ];
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

    private function providerRequestSummary($operatorId, $limit)
    {
        if (!Schema::hasTable('b2b_provider_requests')) {
            return [
                'by_status' => [],
                'by_action' => [],
                'by_provider' => [],
                'recent_requests' => [],
                'failed_sessions' => [],
            ];
        }

        $rows = DB::table('b2b_provider_requests')
            ->where('operator_id', $operatorId)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get($this->selectExisting('b2b_provider_requests', [
                'request_uid',
                'provider',
                'game_uid',
                'session_id',
                'action',
                'status',
                'error_message',
                'duration_ms',
                'created_at',
            ]));

        return [
            'by_status' => $this->groupCounts('b2b_provider_requests', $operatorId, 'status'),
            'by_action' => $this->groupCounts('b2b_provider_requests', $operatorId, 'action'),
            'by_provider' => $this->groupCounts('b2b_provider_requests', $operatorId, 'provider'),
            'recent_requests' => $rows->map(function ($row) {
                return $this->providerRequestListPayload($row);
            })->values(),
            'failed_sessions' => $this->recentFailedLaunchSessions($operatorId, $limit),
        ];
    }

    public function providerRequestDetail(Request $request, $requestUid)
    {
        $operator = $request->attributes->get('b2b_operator');
        if (!$operator || !isset($operator->id) || !Schema::hasTable('b2b_provider_requests')) {
            return null;
        }

        $row = DB::table('b2b_provider_requests')
            ->where('operator_id', (int) $operator->id)
            ->where('request_uid', (string) $requestUid)
            ->first($this->selectExisting('b2b_provider_requests', [
                'request_uid',
                'provider',
                'game_uid',
                'session_id',
                'action',
                'status',
                'request_payload',
                'response_payload',
                'error_message',
                'duration_ms',
                'created_at',
                'updated_at',
            ]));

        if (!$row) {
            return null;
        }

        $summary = $this->providerRequestListPayload($row);

        return [
            'request' => array_merge($summary, [
                'updated_at' => isset($row->updated_at) ? $this->isoTime($row->updated_at) : null,
            ]),
            'request_summary' => isset($row->request_payload) ? $this->safeMetadataSummary($row->request_payload) : null,
            'response_summary' => isset($row->response_payload) ? $this->safeMetadataSummary($row->response_payload) : null,
            'detail_endpoint' => isset($row->request_uid) ? $this->providerRequestDetailEndpoint($row->request_uid) : null,
            'session_detail_endpoint' => isset($row->session_id) ? $this->sessionDetailEndpoint($row->session_id) : null,
        ];
    }

    private function providerRequestListPayload($row)
    {
        return [
            'request_uid' => isset($row->request_uid) ? $row->request_uid : null,
            'provider' => isset($row->provider) ? $row->provider : null,
            'game_uid' => isset($row->game_uid) ? $row->game_uid : null,
            'session_id' => isset($row->session_id) ? $row->session_id : null,
            'action' => isset($row->action) ? $row->action : null,
            'status' => isset($row->status) ? $row->status : null,
            'error_summary' => isset($row->error_message) ? $this->safeErrorSummary($row->error_message) : null,
            'duration_ms' => isset($row->duration_ms) ? (int) $row->duration_ms : null,
            'detail_endpoint' => isset($row->request_uid) ? $this->providerRequestDetailEndpoint($row->request_uid) : null,
            'session_detail_endpoint' => isset($row->session_id) ? $this->sessionDetailEndpoint($row->session_id) : null,
            'created_at' => isset($row->created_at) ? $this->isoTime($row->created_at) : null,
        ];
    }

    private function recentFailedLaunchSessions($operatorId, $limit)
    {
        if (!Schema::hasTable('b2b_game_sessions')) {
            return collect();
        }

        $query = DB::table('b2b_game_sessions')
            ->where('operator_id', $operatorId)
            ->where(function ($q) {
                $q->where('status', 'failed');
                if (Schema::hasColumn('b2b_game_sessions', 'failure_code')) {
                    $q->orWhereNotNull('failure_code');
                }
            })
            ->orderBy('updated_at', 'desc')
            ->orderBy('id', 'desc')
            ->limit($limit);

        return $query->get($this->selectExisting('b2b_game_sessions', [
            'session_uid',
            'game_uid',
            'provider',
            'status',
            'failure_code',
            'failure_message',
            'launch_attempts',
            'updated_at',
        ]))->map(function ($row) {
            return [
                'session_uid' => isset($row->session_uid) ? $row->session_uid : null,
                'game_uid' => isset($row->game_uid) ? $row->game_uid : null,
                'provider' => isset($row->provider) ? $row->provider : null,
                'status' => isset($row->status) ? $row->status : null,
                'failure_code' => isset($row->failure_code) ? $this->safeErrorSummary($row->failure_code) : null,
                'failure_message' => isset($row->failure_message) ? $this->safeErrorSummary($row->failure_message) : null,
                'launch_attempts' => isset($row->launch_attempts) ? (int) $row->launch_attempts : null,
                'session_detail_endpoint' => isset($row->session_uid) ? $this->sessionDetailEndpoint($row->session_uid) : null,
                'updated_at' => isset($row->updated_at) ? $this->isoTime($row->updated_at) : null,
            ];
        })->values();
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
                $detailEndpoint = isset($row->ticket_uid) ? $this->supportTicketDetailEndpoint($row->ticket_uid) : null;
                $status = isset($row->status) ? $row->status : null;
                $isOpen = in_array($status, ['open', 'in_progress'], true);

                return [
                    'ticket_uid' => isset($row->ticket_uid) ? $row->ticket_uid : null,
                    'subject' => isset($row->subject) ? $this->safeErrorSummary($row->subject) : null,
                    'status' => $status,
                    'priority' => isset($row->priority) ? $row->priority : null,
                    'category' => isset($row->category) ? $row->category : null,
                    'external_reference' => isset($row->external_reference) ? $this->safeErrorSummary($row->external_reference) : null,
                    'message_count' => isset($messageCounts[$ticketId]) ? (int) $messageCounts[$ticketId] : 0,
                    'latest_message' => isset($latestMessages[$ticketId]) ? $latestMessages[$ticketId] : null,
                    'detail_endpoint' => $detailEndpoint,
                    'thread_endpoint' => $detailEndpoint === null ? null : $detailEndpoint . '/thread',
                    'comment_endpoint' => $isOpen && $detailEndpoint !== null ? $detailEndpoint . '/comments' : null,
                    'close_endpoint' => $isOpen && $detailEndpoint !== null ? $detailEndpoint . '/close' : null,
                    'reopen_endpoint' => $status === 'closed' && $detailEndpoint !== null ? $detailEndpoint . '/reopen' : null,
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

    private function metadataArray($value)
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);
            return json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : [];
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
        $detailEndpoint = isset($row->transaction_uid) ? $this->supportCaseDetailEndpoint($row->transaction_uid) : null;
        $state = isset($row->state) ? $row->state : null;

        return [
            'transaction_uid' => isset($row->transaction_uid) ? $row->transaction_uid : null,
            'status' => isset($row->status) ? $row->status : null,
            'reason' => isset($row->reason) ? $this->safeErrorSummary($row->reason) : null,
            'priority' => isset($row->priority) ? $row->priority : null,
            'state' => $state,
            'support_case_detail_endpoint' => $detailEndpoint,
            'support_case_thread_endpoint' => $detailEndpoint === null ? null : $detailEndpoint . '/thread',
            'support_case_comment_endpoint' => in_array($state, ['open', 'in_progress'], true) && $detailEndpoint !== null ? $detailEndpoint . '/comments' : null,
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

    private function sessionSelectColumns()
    {
        return [
            's.id',
            's.session_uid',
            's.game_uid',
            's.provider',
            's.mode',
            's.currency',
            's.language',
            's.country',
            's.status',
            's.expires_at',
            's.last_seen_at',
            's.heartbeat_at',
            's.stale_at',
            's.closed_at',
            's.close_reason',
            's.heartbeat_timeout_seconds',
            's.failure_code',
            's.failure_message',
            's.created_at',
            's.updated_at',
        ];
    }

    private function portalSessionSummary($session)
    {
        return [
            'session_uid' => isset($session->session_uid) ? $session->session_uid : null,
            'external_player_id' => isset($session->external_player_id) ? $this->safeErrorSummary($session->external_player_id) : null,
            'game_uid' => isset($session->game_uid) ? $session->game_uid : null,
            'provider' => isset($session->provider) ? $session->provider : null,
            'mode' => isset($session->mode) ? $session->mode : null,
            'currency' => isset($session->currency) ? $session->currency : null,
            'language' => isset($session->language) ? $session->language : null,
            'country' => isset($session->country) ? $session->country : null,
            'status' => isset($session->status) ? $session->status : null,
            'expires_at' => isset($session->expires_at) ? $this->isoTime($session->expires_at) : null,
            'last_seen_at' => isset($session->last_seen_at) ? $this->isoTime($session->last_seen_at) : null,
            'heartbeat_at' => isset($session->heartbeat_at) ? $this->isoTime($session->heartbeat_at) : null,
            'stale_at' => isset($session->stale_at) ? $this->isoTime($session->stale_at) : null,
            'closed_at' => isset($session->closed_at) ? $this->isoTime($session->closed_at) : null,
            'close_reason' => isset($session->close_reason) ? $this->safeErrorSummary($session->close_reason) : null,
            'heartbeat_timeout_seconds' => isset($session->heartbeat_timeout_seconds) ? (int) $session->heartbeat_timeout_seconds : null,
            'failure_code' => isset($session->failure_code) ? $this->safeErrorSummary($session->failure_code) : null,
            'failure_message' => isset($session->failure_message) ? $this->safeErrorSummary($session->failure_message) : null,
            'created_at' => isset($session->created_at) ? $this->isoTime($session->created_at) : null,
            'updated_at' => isset($session->updated_at) ? $this->isoTime($session->updated_at) : null,
        ];
    }

    private function portalSettlementSummary($settlement)
    {
        return [
            'settlement_uid' => isset($settlement->settlement_uid) ? $settlement->settlement_uid : null,
            'period_start' => isset($settlement->period_start) ? $this->isoTime($settlement->period_start) : null,
            'period_end' => isset($settlement->period_end) ? $this->isoTime($settlement->period_end) : null,
            'currency' => isset($settlement->currency) ? $settlement->currency : null,
            'bets_amount' => isset($settlement->bets_amount) ? $this->decimalNormalize($settlement->bets_amount) : null,
            'wins_amount' => isset($settlement->wins_amount) ? $this->decimalNormalize($settlement->wins_amount) : null,
            'refunds_amount' => isset($settlement->refunds_amount) ? $this->decimalNormalize($settlement->refunds_amount) : null,
            'ggr_amount' => isset($settlement->ggr_amount) ? $this->decimalNormalize($settlement->ggr_amount) : null,
            'aggregator_fee_amount' => isset($settlement->aggregator_fee_amount) ? $this->decimalNormalize($settlement->aggregator_fee_amount) : null,
            'provider_fee_amount' => isset($settlement->provider_fee_amount) ? $this->decimalNormalize($settlement->provider_fee_amount) : null,
            'net_amount' => isset($settlement->net_amount) ? $this->decimalNormalize($settlement->net_amount) : null,
            'status' => isset($settlement->status) ? $settlement->status : null,
            'export_format' => isset($settlement->export_format) ? $settlement->export_format : null,
            'export_hash' => isset($settlement->export_hash) ? $settlement->export_hash : null,
            'exported_at' => isset($settlement->exported_at) ? $this->isoTime($settlement->exported_at) : null,
            'submitted_at' => isset($settlement->submitted_at) ? $this->isoTime($settlement->submitted_at) : null,
            'submitted_by' => isset($settlement->submitted_by) ? $this->safeErrorSummary($settlement->submitted_by) : null,
            'approved_at' => isset($settlement->approved_at) ? $this->isoTime($settlement->approved_at) : null,
            'approved_by' => isset($settlement->approved_by) ? $this->safeErrorSummary($settlement->approved_by) : null,
            'rejected_at' => isset($settlement->rejected_at) ? $this->isoTime($settlement->rejected_at) : null,
            'rejected_by' => isset($settlement->rejected_by) ? $this->safeErrorSummary($settlement->rejected_by) : null,
            'created_at' => isset($settlement->created_at) ? $this->isoTime($settlement->created_at) : null,
            'updated_at' => isset($settlement->updated_at) ? $this->isoTime($settlement->updated_at) : null,
        ];
    }

    private function settlementTotals(array $metadata)
    {
        $totals = isset($metadata['totals']) && is_array($metadata['totals']) ? $metadata['totals'] : [];
        $result = [];
        foreach ($totals as $metric => $amount) {
            $metric = trim((string) $metric);
            if ($metric === '') {
                continue;
            }

            if ($metric === 'transactions') {
                $result[$metric] = (string) (int) $amount;
                continue;
            }

            $result[$metric] = is_numeric($amount) || is_string($amount)
                ? $this->decimalNormalize($amount)
                : $this->safeErrorSummary(json_encode($amount));
        }

        return $result;
    }

    private function settlementBreakdown(array $metadata)
    {
        $byType = isset($metadata['by_type']) && is_array($metadata['by_type']) ? $metadata['by_type'] : [];
        $result = [];
        foreach ($byType as $type => $row) {
            if (!is_array($row)) {
                continue;
            }

            $key = trim((string) $type);
            if ($key === '') {
                $key = 'unknown';
            }

            $result[$key] = [
                'count' => isset($row['count']) ? (int) $row['count'] : 0,
                'amount' => isset($row['amount']) ? $this->decimalNormalize($row['amount']) : '0.00000000',
            ];
        }

        return $result;
    }

    private function settlementApproval(array $metadata)
    {
        $approval = isset($metadata['approval']) && is_array($metadata['approval']) ? $metadata['approval'] : [];

        return [
            'decision' => isset($approval['decision']) ? $this->safeErrorSummary($approval['decision']) : null,
            'actor' => isset($approval['actor']) ? $this->safeErrorSummary($approval['actor']) : null,
            'reason' => isset($approval['reason']) ? $this->safeErrorSummary($approval['reason']) : null,
            'decided_at' => isset($approval['decided_at']) ? $this->isoTime($approval['decided_at']) : null,
        ];
    }

    private function settlementExport($settlement, array $metadata)
    {
        $export = isset($metadata['export']) && is_array($metadata['export']) ? $metadata['export'] : [];

        return [
            'format' => isset($settlement->export_format) ? $settlement->export_format : (isset($export['format']) ? $this->safeErrorSummary($export['format']) : null),
            'sha256' => isset($settlement->export_hash) ? $settlement->export_hash : (isset($export['sha256']) ? $this->safeErrorSummary($export['sha256']) : null),
            'generated_at' => isset($export['generated_at']) ? $this->isoTime($export['generated_at']) : null,
        ];
    }

    private function settlementMetadataSummary(array $metadata)
    {
        unset($metadata['export_content']);
        if (isset($metadata['export']) && is_array($metadata['export'])) {
            unset($metadata['export']['content']);
        }

        return $this->safeMetadataSummary($metadata);
    }

    private function sessionTransactions($operatorId, $session, $limit)
    {
        if (!Schema::hasTable('b2b_wallet_transactions')) {
            return collect();
        }

        $rows = $this->sessionTransactionBaseQuery($operatorId, $session)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->limit((int) $limit)
            ->get($this->selectExisting('b2b_wallet_transactions', [
                'transaction_uid',
                'transaction_id',
                'type',
                'status',
                'amount',
                'currency',
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
                'game_uid' => isset($row->game_uid) ? $row->game_uid : null,
                'round_id' => isset($row->round_id) ? $row->round_id : null,
                'attempts' => isset($row->attempts) ? (int) $row->attempts : null,
                'detail_endpoint' => isset($row->transaction_uid) ? $this->transactionDetailEndpoint($row->transaction_uid) : null,
                'created_at' => isset($row->created_at) ? $this->isoTime($row->created_at) : null,
            ];
        })->values();
    }

    private function sessionTransactionSummary($operatorId, $session)
    {
        if (!Schema::hasTable('b2b_wallet_transactions')) {
            return [
                'by_status' => [],
                'by_type' => [],
                'success_amounts' => [],
            ];
        }

        $base = $this->sessionTransactionBaseQuery($operatorId, $session);

        $statusRows = (clone $base)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->orderBy('status')
            ->get();
        $typeRows = (clone $base)
            ->select('type', DB::raw('COUNT(*) as count'))
            ->groupBy('type')
            ->orderBy('type')
            ->get();
        $amountRows = (clone $base)
            ->where('status', 'success')
            ->select('type', 'currency', DB::raw('COALESCE(SUM(amount), 0) as amount'), DB::raw('COUNT(*) as count'))
            ->groupBy('type', 'currency')
            ->orderBy('type')
            ->orderBy('currency')
            ->get();

        return [
            'by_status' => $this->rowsToCountMap($statusRows, 'status'),
            'by_type' => $this->rowsToCountMap($typeRows, 'type'),
            'success_amounts' => $this->rowsToAmountMap($amountRows),
        ];
    }

    private function sessionTransactionBaseQuery($operatorId, $session)
    {
        return DB::table('b2b_wallet_transactions')
            ->where('operator_id', $operatorId)
            ->where(function ($q) use ($session) {
                if (isset($session->id)) {
                    $q->where('session_id', (string) $session->id);
                }

                if (isset($session->session_uid) && $session->session_uid !== null && $session->session_uid !== '') {
                    $q->orWhere('session_id', $session->session_uid);
                }
            });
    }

    private function rowsToCountMap($rows, $column)
    {
        $result = [];
        foreach ($rows as $row) {
            $key = isset($row->{$column}) && $row->{$column} !== null ? (string) $row->{$column} : 'unknown';
            $result[$key] = ['count' => (int) $row->count];
        }

        return $result;
    }

    private function rowsToAmountMap($rows)
    {
        $result = [];
        foreach ($rows as $row) {
            $type = isset($row->type) && $row->type !== null ? (string) $row->type : 'unknown';
            $currency = isset($row->currency) && $row->currency !== null ? (string) $row->currency : 'UNK';
            if (!isset($result[$type])) {
                $result[$type] = [];
            }

            $result[$type][$currency] = [
                'amount' => $this->decimalNormalize($row->amount),
                'count' => (int) $row->count,
            ];
        }

        return $result;
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

    private function gameDetailEndpoint($gameUid)
    {
        $gameUid = trim((string) $gameUid);

        return $gameUid === ''
            ? null
            : '/api/b2b/v1/portal/games/' . rawurlencode($gameUid);
    }

    private function providerRequestDetailEndpoint($requestUid)
    {
        $requestUid = trim((string) $requestUid);

        return $requestUid === ''
            ? null
            : '/api/b2b/v1/portal/diagnostics/' . rawurlencode($requestUid);
    }

    private function sessionDetailEndpoint($sessionUid)
    {
        $sessionUid = trim((string) $sessionUid);

        return $sessionUid === ''
            ? null
            : '/api/b2b/v1/portal/sessions/' . rawurlencode($sessionUid);
    }

    private function settlementDetailEndpoint($settlementUid)
    {
        $settlementUid = trim((string) $settlementUid);

        return $settlementUid === ''
            ? null
            : '/api/b2b/v1/portal/settlements/' . rawurlencode($settlementUid);
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
