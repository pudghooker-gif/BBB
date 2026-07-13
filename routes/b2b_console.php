<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use VanguardLTE\B2B\Models\B2BGameCatalog;
use VanguardLTE\B2B\Models\B2BOperator;
use VanguardLTE\B2B\Models\B2BOperatorApiKey;
use VanguardLTE\B2B\Models\B2BGameSession;
use VanguardLTE\B2B\Models\B2BWalletTransaction;
use VanguardLTE\B2B\Providers\GoldsvetInternalProvider;
use VanguardLTE\B2B\Services\B2BApiCredentialLifecycleService;
use VanguardLTE\B2B\Services\B2BGameCatalogCache;
use VanguardLTE\B2B\Services\B2BOperatorAuditLogger;
use VanguardLTE\B2B\Services\B2BPayloadRedactionAuditor;
use VanguardLTE\B2B\Services\B2BPrivilegedActionGuard;
use VanguardLTE\B2B\Services\B2BReleaseEvidenceChecker;
use VanguardLTE\B2B\Services\B2BReleaseGate;
use VanguardLTE\B2B\Services\B2BSchedulerHeartbeat;
use VanguardLTE\B2B\Services\B2BSettlementWorkflowService;
use VanguardLTE\B2B\Services\B2BSignature;
use VanguardLTE\B2B\Services\B2BStructuredEventLogger;

Artisan::command('b2b:make-operator {name} {--shop_id=} {--base_url=} {--wallet_callback_url=} {--currency=USD} {--max_rps=50} {--api_key_max_rps=} {--scopes=} {--wallet_timeout_ms=3000} {--actor=} {--reason=} {--permission=} {--confirm=}', function (B2BOperatorAuditLogger $audit, B2BPrivilegedActionGuard $guard) {
    if (!Schema::hasTable('b2b_operators') || !Schema::hasTable('b2b_operator_api_keys')) {
        $this->error('B2B tables are missing. Run: php artisan migrate');
        return 1;
    }

    $privilege = $guard->authorize(null, 'operator.create', $this->option('actor'), $this->option('reason'), $this->option('permission'), $this->option('confirm'));
    if (!$privilege['ok']) {
        $this->error($privilege['message']);
        return 1;
    }

    $name = trim($this->argument('name'));
    $currency = strtoupper((string) $this->option('currency'));
    $operatorUid = 'op_' . Str::lower(Str::random(10));
    $keyId = 'key_' . Str::lower(Str::random(16));
    $secret = Str::random(64);

    $data = [
        'operator_uid' => $operatorUid,
        'name' => $name,
        'shop_id' => $this->option('shop_id') !== null && $this->option('shop_id') !== '' ? (int) $this->option('shop_id') : null,
        'status' => B2BOperator::STATUS_ACTIVE,
        'base_url' => $this->option('base_url') ?: null,
        'wallet_callback_url' => $this->option('wallet_callback_url') ?: null,
        'default_currency' => $currency,
        'allowed_currencies' => [$currency],
        'settings' => [
            'created_by' => 'b2b:make-operator',
            'created_at' => now()->toIso8601String(),
        ],
    ];

    if (Schema::hasColumn('b2b_operators', 'max_rps')) {
        $data['max_rps'] = (int) $this->option('max_rps');
    }
    if (Schema::hasColumn('b2b_operators', 'wallet_timeout_ms')) {
        $data['wallet_timeout_ms'] = (int) $this->option('wallet_timeout_ms');
    }
    if (Schema::hasColumn('b2b_operators', 'connect_timeout_ms')) {
        $data['connect_timeout_ms'] = 1500;
    }

    $operator = B2BOperator::create($data);

    $apiKeyData = [
        'operator_id' => $operator->id,
        'key_id' => $keyId,
        'secret_encrypted' => Crypt::encryptString($secret),
        'status' => B2BOperatorApiKey::STATUS_ACTIVE,
    ];
    if (Schema::hasColumn('b2b_operator_api_keys', 'max_rps') && $this->option('api_key_max_rps') !== null && $this->option('api_key_max_rps') !== '') {
        $apiKeyData['max_rps'] = (int) $this->option('api_key_max_rps');
    }
    if (Schema::hasColumn('b2b_operator_api_keys', 'scopes')) {
        $apiKeyData['scopes'] = app(B2BApiCredentialLifecycleService::class)->normalizeScopes($this->option('scopes'));
    }

    $apiKey = B2BOperatorApiKey::create($apiKeyData);

    $audit->record($operator, 'operator.created', 'operator', $operatorUid, $this->option('actor') ?: 'b2b:make-operator', $this->option('reason') ?: 'Initial B2B operator provisioning.', [
        'operator_uid' => $operatorUid,
        'shop_id' => $operator->shop_id,
        'currency' => $currency,
        'permission' => isset($privilege['permission']) ? $privilege['permission'] : null,
        'step_up' => !empty($privilege['step_up']),
    ]);
    $audit->record($operator, 'api_key.created', 'api_key', $apiKey->key_id, $this->option('actor') ?: 'b2b:make-operator', $this->option('reason') ?: 'Initial B2B API key provisioning.', [
        'key_id' => $apiKey->key_id,
        'max_rps' => isset($apiKeyData['max_rps']) ? $apiKeyData['max_rps'] : null,
        'scopes' => isset($apiKeyData['scopes']) ? $apiKeyData['scopes'] : null,
        'permission' => isset($privilege['permission']) ? $privilege['permission'] : null,
        'step_up' => !empty($privilege['step_up']),
    ]);

    $this->info('B2B operator created. Save this secret now; it is not stored in plaintext.');
    $this->line('');
    $this->line('X-Operator-Id: ' . $operatorUid);
    $this->line('X-Api-Key:     ' . $keyId);
    $this->line('Secret:        ' . $secret);
    $this->line('Scopes:        ' . (isset($apiKeyData['scopes']) ? implode(', ', $apiKeyData['scopes']) : 'none'));
    $this->line('');
    $this->line('Next: php artisan b2b:show-hmac ' . $operatorUid . ' ' . $keyId . ' ' . $secret . ' GET /api/b2b/v1/operator/me');

    return 0;
});

Artisan::command('b2b:rotate-api-key {operator_uid} {--key-id=} {--max-rps=} {--scopes=} {--actor=} {--reason=} {--permission=} {--confirm=} {--revoke-existing}', function (B2BOperatorAuditLogger $audit, B2BPrivilegedActionGuard $guard) {
    if (!Schema::hasTable('b2b_operators') || !Schema::hasTable('b2b_operator_api_keys') || !Schema::hasTable('b2b_operator_audit_events')) {
        $this->error('B2B credential/audit tables are missing. Run: php artisan migrate');
        return 1;
    }

    $actor = trim((string) $this->option('actor'));
    $reason = trim((string) $this->option('reason'));

    if ($actor === '' || $reason === '') {
        $this->error('API key rotation requires --actor and --reason.');
        return 1;
    }

    $operator = B2BOperator::where('operator_uid', $this->argument('operator_uid'))->first();
    if (!$operator) {
        $this->error('B2B operator was not found.');
        return 1;
    }

    $privilege = $guard->authorize($operator, 'api_key.rotate', $actor, $reason, $this->option('permission'), $this->option('confirm'));
    if (!$privilege['ok']) {
        $this->error($privilege['message']);
        return 1;
    }

    $keyId = trim((string) $this->option('key-id'));
    if ($keyId === '') {
        $keyId = 'key_' . Str::lower(Str::random(16));
    }

    if (B2BOperatorApiKey::where('key_id', $keyId)->exists()) {
        $this->error('B2B API key id already exists.');
        return 1;
    }

    $secret = Str::random(64);
    $apiKeyData = [
        'operator_id' => $operator->id,
        'key_id' => $keyId,
        'secret_encrypted' => Crypt::encryptString($secret),
        'status' => B2BOperatorApiKey::STATUS_ACTIVE,
    ];
    if (Schema::hasColumn('b2b_operator_api_keys', 'max_rps') && $this->option('max-rps') !== null && $this->option('max-rps') !== '') {
        $apiKeyData['max_rps'] = (int) $this->option('max-rps');
    }
    if (Schema::hasColumn('b2b_operator_api_keys', 'scopes')) {
        $apiKeyData['scopes'] = app(B2BApiCredentialLifecycleService::class)->normalizeScopes($this->option('scopes'));
    }

    $apiKey = B2BOperatorApiKey::create($apiKeyData);

    $disabledExisting = 0;
    if ((bool) $this->option('revoke-existing')) {
        $existingKeys = B2BOperatorApiKey::where('operator_id', $operator->id)
            ->where('id', '<>', $apiKey->id)
            ->where('status', B2BOperatorApiKey::STATUS_ACTIVE)
            ->get();

        foreach ($existingKeys as $existingKey) {
            $existingKey->forceFill(['status' => B2BOperatorApiKey::STATUS_DISABLED])->save();
            $disabledExisting++;

            $audit->record($operator, 'api_key.revoked', 'api_key', $existingKey->key_id, $actor, $reason, [
                'replacement_key_id' => $apiKey->key_id,
                'replacement_scopes' => isset($apiKeyData['scopes']) ? $apiKeyData['scopes'] : null,
                'previous_status' => B2BOperatorApiKey::STATUS_ACTIVE,
                'new_status' => B2BOperatorApiKey::STATUS_DISABLED,
                'permission' => isset($privilege['permission']) ? $privilege['permission'] : null,
                'step_up' => !empty($privilege['step_up']),
            ]);
        }
    }

    $audit->record($operator, 'api_key.rotated', 'api_key', $apiKey->key_id, $actor, $reason, [
        'key_id' => $apiKey->key_id,
        'max_rps' => isset($apiKeyData['max_rps']) ? $apiKeyData['max_rps'] : null,
        'scopes' => isset($apiKeyData['scopes']) ? $apiKeyData['scopes'] : null,
        'revoke_existing' => (bool) $this->option('revoke-existing'),
        'disabled_existing' => $disabledExisting,
        'permission' => isset($privilege['permission']) ? $privilege['permission'] : null,
        'step_up' => !empty($privilege['step_up']),
    ]);

    $this->info('B2B API key rotated. Save this secret now; it is not stored in plaintext.');
    $this->line('');
    $this->line('X-Operator-Id: ' . $operator->operator_uid);
    $this->line('X-Api-Key:     ' . $apiKey->key_id);
    $this->line('Secret:        ' . $secret);
    $this->line('Scopes:        ' . (isset($apiKeyData['scopes']) ? implode(', ', $apiKeyData['scopes']) : 'none'));
    $this->line('Disabled existing keys: ' . $disabledExisting);

    return 0;
})->describe('Rotate a B2B operator API key and write an audit event.');

Artisan::command('b2b:revoke-api-key {operator_uid} {key_id} {--actor=} {--reason=} {--permission=} {--confirm=}', function (B2BOperatorAuditLogger $audit, B2BPrivilegedActionGuard $guard) {
    if (!Schema::hasTable('b2b_operators') || !Schema::hasTable('b2b_operator_api_keys') || !Schema::hasTable('b2b_operator_audit_events')) {
        $this->error('B2B credential/audit tables are missing. Run: php artisan migrate');
        return 1;
    }

    $actor = trim((string) $this->option('actor'));
    $reason = trim((string) $this->option('reason'));

    if ($actor === '' || $reason === '') {
        $this->error('API key revocation requires --actor and --reason.');
        return 1;
    }

    $operator = B2BOperator::where('operator_uid', $this->argument('operator_uid'))->first();
    if (!$operator) {
        $this->error('B2B operator was not found.');
        return 1;
    }

    $apiKey = B2BOperatorApiKey::where('operator_id', $operator->id)
        ->where('key_id', $this->argument('key_id'))
        ->first();

    if (!$apiKey) {
        $this->error('B2B API key was not found for this operator.');
        return 1;
    }

    $privilege = $guard->authorize($operator, 'api_key.revoke', $actor, $reason, $this->option('permission'), $this->option('confirm'));
    if (!$privilege['ok']) {
        $this->error($privilege['message']);
        return 1;
    }

    $previousStatus = $apiKey->status;
    if ($apiKey->status !== B2BOperatorApiKey::STATUS_DISABLED) {
        $apiKey->forceFill(['status' => B2BOperatorApiKey::STATUS_DISABLED])->save();
    }

    $audit->record($operator, $previousStatus === B2BOperatorApiKey::STATUS_DISABLED ? 'api_key.revoke_noop' : 'api_key.revoked', 'api_key', $apiKey->key_id, $actor, $reason, [
        'previous_status' => $previousStatus,
        'new_status' => B2BOperatorApiKey::STATUS_DISABLED,
        'permission' => isset($privilege['permission']) ? $privilege['permission'] : null,
        'step_up' => !empty($privilege['step_up']),
    ]);

    $this->info($previousStatus === B2BOperatorApiKey::STATUS_DISABLED ? 'B2B API key was already revoked.' : 'B2B API key revoked.');
    $this->line('operator_uid: ' . $operator->operator_uid);
    $this->line('key_id: ' . $apiKey->key_id);

    return 0;
})->describe('Revoke a B2B operator API key and write an audit event.');

Artisan::command('b2b:submit-settlement {settlement_uid} {--actor=} {--reason=} {--permission=} {--confirm=}', function (B2BSettlementWorkflowService $service, B2BPrivilegedActionGuard $guard) {
    if (!Schema::hasTable('b2b_settlements')) {
        $this->error('B2B settlements table is missing. Run: php artisan migrate');
        return 1;
    }

    $operatorId = DB::table('b2b_settlements')
        ->where('settlement_uid', $this->argument('settlement_uid'))
        ->value('operator_id');

    $actor = (string) $this->option('actor');
    $reason = (string) $this->option('reason');
    $privilege = $guard->authorize($operatorId, 'settlement.submit', $actor, $reason, $this->option('permission'), $this->option('confirm'));
    if (!$privilege['ok']) {
        $this->error($privilege['message']);
        return 1;
    }

    try {
        $settlement = $service->submit($this->argument('settlement_uid'), $actor, $reason, $privilege);
    } catch (\Exception $e) {
        $this->error($e->getMessage());
        return 1;
    }

    $this->info('B2B settlement submitted for approval.');
    $this->line('settlement_uid: '.$settlement->settlement_uid);
    $this->line('status: '.$settlement->status);
    $this->line('submitted_by: '.$settlement->submitted_by);

    return 0;
})->describe('Submit an exported B2B settlement for finance approval.');

Artisan::command('b2b:approve-settlement {settlement_uid} {decision=approve} {--actor=} {--reason=} {--permission=} {--confirm=}', function (B2BSettlementWorkflowService $service, B2BPrivilegedActionGuard $guard) {
    if (!Schema::hasTable('b2b_settlements')) {
        $this->error('B2B settlements table is missing. Run: php artisan migrate');
        return 1;
    }

    $decision = strtolower((string) $this->argument('decision'));
    if (!in_array($decision, ['approve', 'reject'], true)) {
        $this->error('Settlement decision must be approve or reject.');
        return 1;
    }

    $operatorId = DB::table('b2b_settlements')
        ->where('settlement_uid', $this->argument('settlement_uid'))
        ->value('operator_id');

    $actor = (string) $this->option('actor');
    $reason = (string) $this->option('reason');
    $action = $decision === 'reject' ? 'settlement.reject' : 'settlement.approve';
    $privilege = $guard->authorize($operatorId, $action, $actor, $reason, $this->option('permission'), $this->option('confirm'));
    if (!$privilege['ok']) {
        $this->error($privilege['message']);
        return 1;
    }

    try {
        $settlement = $decision === 'reject'
            ? $service->reject($this->argument('settlement_uid'), $actor, $reason, $privilege)
            : $service->approve($this->argument('settlement_uid'), $actor, $reason, $privilege);
    } catch (\Exception $e) {
        $this->error($e->getMessage());
        return 1;
    }

    $this->info('B2B settlement '.$decision.' decision recorded.');
    $this->line('settlement_uid: '.$settlement->settlement_uid);
    $this->line('status: '.$settlement->status);

    return 0;
})->describe('Approve or reject a submitted B2B settlement with privileged step-up.');

Artisan::command('b2b:sync-games {--shop_id=} {--limit=0} {--soft-disable-missing}', function () {
    if (!Schema::hasTable('b2b_game_catalog')) {
        $this->error('b2b_game_catalog table is missing. Run: php artisan migrate');
        return 1;
    }

    if (!Schema::hasTable('games')) {
        $this->error('games table was not found. Nothing to sync.');
        return 1;
    }

    $columns = Schema::getColumnListing('games');
    $shopId = null;
    $shopIdOption = $this->option('shop_id');
    $softDisableMissing = (bool) $this->option('soft-disable-missing');
    $limit = (int) $this->option('limit');

    if ($softDisableMissing && $limit > 0) {
        $this->error('--soft-disable-missing cannot be combined with --limit because partial syncs cannot prove a game is absent.');
        return 1;
    }

    if ($shopIdOption !== null && $shopIdOption !== '') {
        if (!in_array('shop_id', $columns, true)) {
            $this->error('--shop_id requires games.shop_id to exist.');
            return 1;
        }

        $shopId = (int) $shopIdOption;
    }

    $rows = app(GoldsvetInternalProvider::class)->listGames([
        'shop_id' => $shopId,
        'limit' => $limit,
    ]);
    $created = 0;
    $updated = 0;
    $disabled = 0;
    $seenGameUids = [];

    foreach ($rows as $game) {
        $gameUid = isset($game['game_uid']) ? (string) $game['game_uid'] : null;

        if (!$gameUid) {
            continue;
        }

        $seenGameUids[$gameUid] = true;
        $metadata = isset($game['metadata']) && is_array($game['metadata']) ? $game['metadata'] : [];
        $metadata['synced_at'] = now()->toIso8601String();

        $model = B2BGameCatalog::firstOrNew(['game_uid' => $gameUid]);
        $model->fill([
            'provider_game_id' => isset($game['provider_game_id']) ? $game['provider_game_id'] : $gameUid,
            'canonical_game_id' => isset($game['canonical_game_id']) ? $game['canonical_game_id'] : $gameUid,
            'provider' => isset($game['provider']) ? $game['provider'] : 'goldsvet_internal',
            'slug' => isset($game['slug']) ? $game['slug'] : null,
            'title' => isset($game['title']) ? $game['title'] : $gameUid,
            'category' => isset($game['category']) ? $game['category'] : 'slots',
            'platform' => isset($game['platform']) ? $game['platform'] : null,
            'thumbnail_url' => isset($game['thumbnail_url']) ? $game['thumbnail_url'] : null,
            'launch_config' => isset($game['launch_config']) ? $game['launch_config'] : [],
            'demo_supported' => !empty($game['demo_supported']),
            'real_supported' => !empty($game['real_supported']),
            'supported_currencies' => isset($game['supported_currencies']) ? $game['supported_currencies'] : [],
            'supported_countries' => isset($game['supported_countries']) ? $game['supported_countries'] : [],
            'status' => isset($game['status']) ? $game['status'] : 'active',
            'metadata' => $metadata,
        ]);

        $model->exists ? $updated++ : $created++;
        $model->save();
    }

    if ($softDisableMissing) {
        B2BGameCatalog::where('provider', 'goldsvet_internal')
            ->where('status', 'active')
            ->get()
            ->each(function ($catalogGame) use (&$disabled, $seenGameUids, $shopId) {
                $metadata = $catalogGame->metadata ?: [];
                $syncedFrom = isset($metadata['synced_from']) ? $metadata['synced_from'] : null;
                $metadataShopId = isset($metadata['shop_id']) ? (string) $metadata['shop_id'] : null;

                if ($syncedFrom !== 'games') {
                    return;
                }

                if ($shopId !== null && $metadataShopId !== (string) $shopId) {
                    return;
                }

                if (isset($seenGameUids[$catalogGame->game_uid])) {
                    return;
                }

                $metadata['disabled_by_sync_at'] = now()->toIso8601String();
                $metadata['disabled_by_sync_reason'] = 'missing_from_games_source';
                $catalogGame->status = 'disabled';
                $catalogGame->metadata = $metadata;
                $catalogGame->save();
                $disabled++;
            });
    }

    $invalidated = app(B2BGameCatalogCache::class)->invalidate();

    $this->info('B2B game catalog synced. Created: ' . $created . ', updated: ' . $updated . ', soft-disabled: ' . $disabled . ', scanned: ' . count($rows));
    $this->line($invalidated ? 'B2B game catalog cache invalidated.' : 'B2B game catalog cache invalidation skipped or failed.');
    return 0;
});

Artisan::command('b2b:show-hmac {operator_uid} {key_id} {secret} {method=GET} {path=/api/b2b/v1/operator/me} {--body=}', function () {
    $operatorUid = $this->argument('operator_uid');
    $keyId = $this->argument('key_id');
    $secret = $this->argument('secret');
    $method = strtoupper($this->argument('method'));
    $path = $this->argument('path');
    $body = $this->option('body') ?: '';
    $timestamp = (string) time();
    $nonce = Str::random(24);
    $bodyHash = B2BSignature::bodyHash($body);
    $pathOnly = parse_url($path, PHP_URL_PATH) ?: '/';
    $queryString = parse_url($path, PHP_URL_QUERY) ?: '';
    $canonical = B2BSignature::canonicalFromParts($method, $pathOnly, $queryString, $bodyHash, $timestamp, $nonce);
    $signature = hash_hmac('sha256', $canonical, $secret);

    $this->line('X-Operator-Id: ' . $operatorUid);
    $this->line('X-Api-Key: ' . $keyId);
    $this->line('X-Timestamp: ' . $timestamp);
    $this->line('X-Nonce: ' . $nonce);
    $this->line('X-Body-Hash: ' . $bodyHash);
    $this->line('X-Signature: ' . $signature);
    $this->line('');
    $this->line('canonical request:');
    $this->line($canonical);
    $this->line('');
    $this->line('curl example:');
    $curl = "curl -X {$method} \"" . rtrim(config('app.url'), '/') . $path . "\"" .
        " -H \"X-Operator-Id: {$operatorUid}\"" .
        " -H \"X-Api-Key: {$keyId}\"" .
        " -H \"X-Timestamp: {$timestamp}\"" .
        " -H \"X-Nonce: {$nonce}\"" .
        " -H \"X-Body-Hash: {$bodyHash}\"" .
        " -H \"X-Signature: {$signature}\"";

    if ($body !== '') {
        $curl .= " -H \"Content-Type: application/json\" --data '" . str_replace("'", "'\\''", $body) . "'";
    }

    $this->line($curl);
    return 0;
});

Artisan::command('b2b:scheduler-heartbeat {--source=scheduler : Source label for scheduler evidence}', function (B2BSchedulerHeartbeat $heartbeat) {
    $payload = $heartbeat->record($this->option('source') ?: 'scheduler');

    $this->info('B2B scheduler heartbeat recorded.');
    $this->line('cache_store: ' . $heartbeat->cacheStoreName());
    $this->line('cache_key: ' . $heartbeat->cacheKey());
    $this->line('recorded_at: ' . $payload['recorded_at']);

    return 0;
})->describe('Record B2B scheduler heartbeat for readiness and metrics.');

Artisan::command('b2b:queue-runtime-evidence {--artifact= : Optional JSON artifact path for release evidence} {--supervisor-status-file= : supervisorctl status output for bbb-b2b-* workers} {--production : Enforce production runtime evidence requirements} {--max-failed=0 : Maximum allowed failed B2B jobs} {--allow-missing-supervisor : Do not fail when supervisor status is absent}', function () {
    $production = (bool) $this->option('production');
    $maxFailed = max(0, (int) $this->option('max-failed'));
    $allowMissingSupervisor = (bool) $this->option('allow-missing-supervisor');
    $workers = (array) config('b2b_queues.workers', []);
    $queues = (array) config('b2b_queues.queues', []);
    $scheduled = (array) config('b2b_queues.scheduled_commands', []);
    $supervisorFile = $this->option('supervisor-status-file') ? (string) $this->option('supervisor-status-file') : null;
    $supervisorText = null;
    $failures = [];

    if ($supervisorFile !== null) {
        if (is_readable($supervisorFile)) {
            $supervisorText = (string) file_get_contents($supervisorFile);
        } else {
            $failures[] = 'supervisor_status_file_unreadable';
        }
    } elseif ($production && !$allowMissingSupervisor) {
        $failures[] = 'supervisor_status_file_missing';
    }

    $programName = function ($queueName) {
        return 'bbb-b2b-' . str_replace('_', '-', (string) $queueName);
    };

    $runningCount = function ($program) use ($supervisorText) {
        if ($supervisorText === null) {
            return null;
        }

        $count = 0;
        foreach (preg_split('/\r\n|\r|\n/', $supervisorText) as $line) {
            if (strpos($line, $program) !== false && preg_match('/\bRUNNING\b/', $line)) {
                $count++;
            }
        }

        return $count;
    };

    $workerSummary = [];
    foreach ($workers as $key => $worker) {
        $queue = isset($worker['queue']) ? (string) $worker['queue'] : null;
        $processes = isset($worker['processes']) ? (int) $worker['processes'] : 0;
        $timeout = isset($worker['timeout']) ? (int) $worker['timeout'] : 0;
        $program = $programName($key);
        $running = $runningCount($program);
        $ok = $queue !== null && $processes > 0 && $timeout > 0;

        if ($running !== null && $running < $processes) {
            $ok = false;
            $failures[] = 'worker_running_count:' . $key . ':' . $running . '<' . $processes;
        }

        if (!$ok && ($queue === null || $processes <= 0)) {
            $failures[] = 'worker_config:' . $key;
        }
        if ($timeout <= 0) {
            $failures[] = 'worker_timeout:' . $key;
        }

        $workerSummary[$key] = [
            'program' => $program,
            'queue' => $queue,
            'expected_processes' => $processes,
            'running_processes' => $running,
            'tries' => isset($worker['tries']) ? (int) $worker['tries'] : null,
            'timeout' => $timeout > 0 ? $timeout : null,
            'max_time' => isset($worker['max_time']) ? (int) $worker['max_time'] : null,
            'status' => $ok ? 'passed' : 'failed',
        ];
    }

    $queueSummary = [];
    foreach ($queues as $key => $queue) {
        $queueSummary[$key] = [
            'queue' => (string) $queue,
            'worker_defined' => isset($workers[$key]),
        ];

        if (!isset($workers[$key])) {
            $failures[] = 'queue_without_worker:' . $key;
        }
    }

    $failedDriver = config('queue.failed.driver');
    $failedTable = config('queue.failed.table', 'failed_jobs');
    $failedJobs = [
        'driver' => $failedDriver,
        'table' => $failedTable,
        'table_exists' => Schema::hasTable($failedTable),
        'max_failed' => $maxFailed,
        'total_b2b_failed' => 0,
        'by_queue' => [],
    ];

    if (!in_array($failedDriver, ['database', 'database-uuids'], true)) {
        $failures[] = 'failed_job_driver:' . ($failedDriver ?: 'missing');
    }

    if (!$failedJobs['table_exists']) {
        $failures[] = 'failed_job_table_missing:' . $failedTable;
    } else {
        $configuredQueues = array_values(array_filter(array_map('strval', $queues)));
        foreach ($configuredQueues as $queue) {
            $count = DB::table($failedTable)->where('queue', $queue)->count();
            $failedJobs['by_queue'][$queue] = $count;
            $failedJobs['total_b2b_failed'] += $count;
        }

        if ($failedJobs['total_b2b_failed'] > $maxFailed) {
            $failures[] = 'failed_jobs_exceed_threshold:' . $failedJobs['total_b2b_failed'] . '>' . $maxFailed;
        }
    }

    $kernel = (string) @file_get_contents(base_path('app/Console/Kernel.php'));
    $scheduler = [
        'commands_configured' => count($scheduled),
        'heartbeat_configured' => isset($scheduled['scheduler_heartbeat']['command'])
            && strpos((string) $scheduled['scheduler_heartbeat']['command'], 'b2b:scheduler-heartbeat') === 0,
        'without_overlapping_configured' => strpos($kernel, 'withoutOverlapping()') !== false,
        'scheduled_queues' => [],
    ];

    foreach ($scheduled as $key => $definition) {
        $scheduler['scheduled_queues'][$key] = [
            'command' => isset($definition['command']) ? (string) $definition['command'] : null,
            'queue' => isset($definition['queue']) ? (string) $definition['queue'] : null,
            'frequency' => isset($definition['frequency']) ? (string) $definition['frequency'] : null,
        ];
    }

    if (!$scheduler['heartbeat_configured']) {
        $failures[] = 'scheduler_heartbeat_missing';
    }
    if (!$scheduler['without_overlapping_configured']) {
        $failures[] = 'scheduler_without_overlapping_missing';
    }

    $artifact = [
        'status' => count($failures) === 0 ? 'passed' : 'failed',
        'generated_at' => now()->toIso8601String(),
        'production_mode' => $production,
        'queue_connection' => config('b2b_queues.connection'),
        'laravel_queue_default' => config('queue.default'),
        'supervisor' => [
            'status_file_supplied' => $supervisorFile !== null,
            'status_file_readable' => $supervisorText !== null,
            'status_file_basename' => $supervisorFile ? basename($supervisorFile) : null,
        ],
        'queues' => $queueSummary,
        'workers' => $workerSummary,
        'scheduler' => $scheduler,
        'failed_jobs' => $failedJobs,
        'failures' => array_values(array_unique($failures)),
    ];

    if ($this->option('artifact')) {
        $artifactPath = (string) $this->option('artifact');
        $directory = dirname($artifactPath);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            $this->error('Unable to create artifact directory: ' . $directory);
            return 1;
        }

        file_put_contents($artifactPath, json_encode($artifact, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
        $this->line('artifact: ' . $artifactPath);
    }

    if ($artifact['status'] !== 'passed') {
        $this->error('B2B queue runtime evidence failed.');
        foreach ($artifact['failures'] as $failure) {
            $this->line('- ' . $failure);
        }

        return 1;
    }

    $this->info('B2B queue runtime evidence verified.');
    $this->line('workers: ' . count($workerSummary));
    $this->line('scheduled_commands: ' . $scheduler['commands_configured']);
    $this->line('b2b_failed_jobs: ' . $failedJobs['total_b2b_failed']);

    return 0;
})->describe('Validate B2B queue worker, scheduler, and failed-job runtime evidence.');

Artisan::command('b2b:health', function () {
    $this->line('B2B health summary');
    $this->line('operators: ' . (Schema::hasTable('b2b_operators') ? B2BOperator::count() : 'missing table'));
    $this->line('games: ' . (Schema::hasTable('b2b_game_catalog') ? B2BGameCatalog::count() : 'missing table'));
    $this->line('active sessions: ' . (Schema::hasTable('b2b_game_sessions') ? B2BGameSession::where('status', B2BGameSession::STATUS_ACTIVE)->count() : 'missing table'));
    $this->line('wallet transactions: ' . (Schema::hasTable('b2b_wallet_transactions') ? B2BWalletTransaction::count() : 'missing table'));
    return 0;
});

Artisan::command('b2b:log-shipping-check {--artifact= : Optional JSON artifact path for release evidence} {--marker= : Optional correlation marker} {--log-file= : Explicit B2B JSON log file to scan}', function (B2BStructuredEventLogger $logger) {
    $marker = (string) ($this->option('marker') ?: ('b2b-log-shipping-' . (string) Str::uuid()));
    $marker = preg_replace('/[^A-Za-z0-9_.:-]/', '_', $marker) ?: ('b2b-log-shipping-' . (string) Str::uuid());
    $channel = config('b2b.structured_log_channel') ?: config('logging.default', 'stack');
    $channelConfig = (array) config('logging.channels.' . $channel, []);
    $logFiles = [];

    if ($this->option('log-file')) {
        $logFiles[] = (string) $this->option('log-file');
    }

    if (!empty($channelConfig['path'])) {
        $configuredPath = (string) $channelConfig['path'];
        $logFiles[] = $configuredPath;

        if (($channelConfig['driver'] ?? null) === 'daily') {
            $info = pathinfo($configuredPath);
            $extension = isset($info['extension']) && $info['extension'] !== '' ? '.' . $info['extension'] : '';
            $logFiles[] = ($info['dirname'] ?? storage_path('logs')) . DIRECTORY_SEPARATOR . ($info['filename'] ?? 'b2b') . '-' . date('Y-m-d') . $extension;
        }
    }

    $logFiles = array_values(array_unique(array_filter($logFiles)));

    $logger->info('observability.log_shipping_check', [
        'request_id' => $marker,
        'marker' => $marker,
        'metadata' => [
            'probe' => 'log_shipping_check',
            'token' => 'log-shipping-secret-probe',
            'authorization' => 'Bearer log.shipping.secret',
        ],
    ]);

    usleep(100000);

    $foundLine = null;
    $foundFile = null;
    foreach ($logFiles as $logFile) {
        if (!is_readable($logFile)) {
            continue;
        }

        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            if (strpos($lines[$i], $marker) !== false) {
                $foundLine = $lines[$i];
                $foundFile = $logFile;
                break 2;
            }
        }
    }

    $decoded = $foundLine ? json_decode($foundLine, true) : null;
    $context = is_array($decoded) && isset($decoded['context']) && is_array($decoded['context'])
        ? $decoded['context']
        : [];
    $secretLeak = $foundLine && (
        strpos($foundLine, 'log-shipping-secret-probe') !== false
        || strpos($foundLine, 'Bearer log.shipping.secret') !== false
    );
    $ok = $foundLine
        && is_array($decoded)
        && ($context['component'] ?? null) === 'b2b'
        && ($context['event'] ?? null) === 'observability.log_shipping_check'
        && ($context['marker'] ?? null) === $marker
        && !$secretLeak;

    $artifact = [
        'status' => $ok ? 'passed' : 'failed',
        'generated_at' => now()->toIso8601String(),
        'channel' => $channel,
        'marker' => $marker,
        'log_files_checked' => $logFiles,
        'matched_log_file' => $foundFile,
        'event_found' => (bool) $foundLine,
        'json_parsed' => is_array($decoded),
        'redaction_verified' => !$secretLeak,
        'expected_event' => 'observability.log_shipping_check',
    ];

    if ($this->option('artifact')) {
        $artifactPath = (string) $this->option('artifact');
        $directory = dirname($artifactPath);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            $this->error('Unable to create artifact directory: ' . $directory);
            return 1;
        }

        file_put_contents($artifactPath, json_encode($artifact, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
        $this->line('artifact: ' . $artifactPath);
    }

    if (!$ok) {
        $this->error('B2B structured log shipping marker was not found as redacted JSON.');
        $this->line('marker: ' . $marker);
        $this->line('channel: ' . $channel);
        $this->line('checked: ' . implode(', ', $logFiles));
        return 1;
    }

    $this->info('B2B structured log shipping marker verified.');
    $this->line('marker: ' . $marker);
    $this->line('channel: ' . $channel);
    $this->line('matched_log_file: ' . $foundFile);

    return 0;
})->describe('Write and verify a redacted B2B structured log marker for release evidence.');

Artisan::command('b2b:correlation-evidence {--artifact= : Optional JSON artifact path for release evidence} {--limit=50 : Maximum recent rows per source to inspect} {--from= : Optional created_at lower bound} {--allow-empty : Do not fail when no samples exist}', function () {
    $limit = max(1, min(500, (int) $this->option('limit')));
    $from = $this->option('from') ? (string) $this->option('from') : null;
    $allowEmpty = (bool) $this->option('allow-empty');
    $secretMarkers = [
        'log-shipping-secret-probe',
        'Bearer log.shipping.secret',
        'BEGIN PRIVATE KEY',
        'api_secret=',
        'password=',
        'authorization: Bearer ',
    ];

    $decode = function ($value) {
        if (is_array($value)) {
            return $value;
        }

        if ($value === null || $value === '') {
            return [];
        }

        $decoded = json_decode((string) $value, true);
        return json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : [];
    };

    $containsSecret = function ($value) use (&$containsSecret, $secretMarkers) {
        if (is_array($value)) {
            foreach ($value as $child) {
                if ($containsSecret($child)) {
                    return true;
                }
            }

            return false;
        }

        if (is_object($value)) {
            return $containsSecret((array) $value);
        }

        $text = strtolower((string) $value);
        foreach ($secretMarkers as $marker) {
            if ($marker !== '' && strpos($text, strtolower($marker)) !== false) {
                return true;
            }
        }

        return false;
    };

    $hashValue = function ($value) {
        if ($value === null || $value === '') {
            return null;
        }

        return hash('sha256', (string) $value);
    };

    $headerValue = function (array $headers, $name) {
        $expected = strtolower((string) $name);
        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) !== $expected) {
                continue;
            }

            if (is_array($value)) {
                return isset($value[0]) ? (string) $value[0] : null;
            }

            return $value !== null ? (string) $value : null;
        }

        return null;
    };

    $summary = [
        'status' => 'failed',
        'generated_at' => now()->toIso8601String(),
        'limit' => $limit,
        'from' => $from,
        'allow_empty' => $allowEmpty,
        'wallet' => [
            'transaction_attempts_checked' => 0,
            'transaction_attempts_with_request_id' => 0,
            'transaction_attempts_with_transaction_uid' => 0,
            'transaction_attempts_complete_context' => 0,
            'callback_logs_checked' => 0,
            'callback_logs_with_request_id' => 0,
            'callback_logs_with_transaction_uid' => 0,
            'callback_logs_complete_context' => 0,
        ],
        'provider' => [
            'requests_checked' => 0,
            'requests_with_request_uid' => 0,
            'requests_with_session_id' => 0,
            'requests_complete_context' => 0,
        ],
        'sample_hashes' => [],
        'secret_scan' => [
            'checked' => true,
            'leaks_found' => 0,
            'locations' => [],
        ],
        'failures' => [],
    ];

    $scanValue = function ($source, $id, $field, $value) use (&$summary, $containsSecret) {
        if ($containsSecret($value)) {
            $summary['secret_scan']['locations'][] = $source . ':' . $id . ':' . $field;
        }
    };

    if (Schema::hasTable('b2b_wallet_transaction_attempts')) {
        $query = DB::table('b2b_wallet_transaction_attempts')->orderByDesc('id')->limit($limit);
        if ($from !== null && Schema::hasColumn('b2b_wallet_transaction_attempts', 'created_at')) {
            $query->where('created_at', '>=', $from);
        }

        $rows = $query->get(['id', 'transaction_uid', 'request_body', 'response_body', 'error']);
        foreach ($rows as $row) {
            $summary['wallet']['transaction_attempts_checked']++;
            $requestBody = $decode($row->request_body ?? null);
            $context = isset($requestBody['_context']) && is_array($requestBody['_context']) ? $requestBody['_context'] : [];
            $requestId = isset($context['request_id']) ? (string) $context['request_id'] : null;
            $transactionUid = isset($context['transaction_uid'])
                ? (string) $context['transaction_uid']
                : (isset($row->transaction_uid) ? (string) $row->transaction_uid : null);

            if ($requestId) {
                $summary['wallet']['transaction_attempts_with_request_id']++;
            }
            if ($transactionUid) {
                $summary['wallet']['transaction_attempts_with_transaction_uid']++;
            }
            if ($requestId && $transactionUid) {
                $summary['wallet']['transaction_attempts_complete_context']++;
                if (count($summary['sample_hashes']) < 5) {
                    $summary['sample_hashes'][] = [
                        'source' => 'wallet_transaction_attempts',
                        'request_id_sha256' => $hashValue($requestId),
                        'transaction_uid_sha256' => $hashValue($transactionUid),
                    ];
                }
            }

            $scanValue('wallet_transaction_attempts', $row->id, 'request_body', $requestBody);
            $scanValue('wallet_transaction_attempts', $row->id, 'response_body', $decode($row->response_body ?? null));
            $scanValue('wallet_transaction_attempts', $row->id, 'error', $row->error ?? null);
        }
    }

    if (Schema::hasTable('b2b_wallet_callback_logs')) {
        $columns = ['id', 'request_body', 'response_body'];
        foreach (['request_headers', 'error_message', 'created_at'] as $column) {
            if (Schema::hasColumn('b2b_wallet_callback_logs', $column)) {
                $columns[] = $column;
            }
        }

        $query = DB::table('b2b_wallet_callback_logs')->orderByDesc('id')->limit($limit);
        if ($from !== null && Schema::hasColumn('b2b_wallet_callback_logs', 'created_at')) {
            $query->where('created_at', '>=', $from);
        }

        $rows = $query->get(array_values(array_unique($columns)));
        foreach ($rows as $row) {
            $summary['wallet']['callback_logs_checked']++;
            $requestBody = $decode($row->request_body ?? null);
            $headers = $decode($row->request_headers ?? null);
            $context = isset($requestBody['_context']) && is_array($requestBody['_context']) ? $requestBody['_context'] : [];
            $requestId = $headerValue($headers, 'X-Request-Id') ?: (isset($context['request_id']) ? (string) $context['request_id'] : null);
            $transactionUid = $headerValue($headers, 'X-B2B-Transaction-Uid') ?: (isset($context['transaction_uid']) ? (string) $context['transaction_uid'] : null);

            if ($requestId) {
                $summary['wallet']['callback_logs_with_request_id']++;
            }
            if ($transactionUid) {
                $summary['wallet']['callback_logs_with_transaction_uid']++;
            }
            if ($requestId && $transactionUid) {
                $summary['wallet']['callback_logs_complete_context']++;
                if (count($summary['sample_hashes']) < 5) {
                    $summary['sample_hashes'][] = [
                        'source' => 'wallet_callback_logs',
                        'request_id_sha256' => $hashValue($requestId),
                        'transaction_uid_sha256' => $hashValue($transactionUid),
                    ];
                }
            }

            $scanValue('wallet_callback_logs', $row->id, 'request_headers', $headers);
            $scanValue('wallet_callback_logs', $row->id, 'request_body', $requestBody);
            $scanValue('wallet_callback_logs', $row->id, 'response_body', $decode($row->response_body ?? null));
            $scanValue('wallet_callback_logs', $row->id, 'error_message', $row->error_message ?? null);
        }
    }

    if (Schema::hasTable('b2b_provider_requests')) {
        $query = DB::table('b2b_provider_requests')->orderByDesc('id')->limit($limit);
        if ($from !== null && Schema::hasColumn('b2b_provider_requests', 'created_at')) {
            $query->where('created_at', '>=', $from);
        }

        $rows = $query->get(['id', 'request_uid', 'session_id', 'request_payload', 'response_payload', 'error_message']);
        foreach ($rows as $row) {
            $summary['provider']['requests_checked']++;
            $requestUid = isset($row->request_uid) ? (string) $row->request_uid : null;
            $sessionId = isset($row->session_id) ? (string) $row->session_id : null;

            if ($requestUid) {
                $summary['provider']['requests_with_request_uid']++;
            }
            if ($sessionId) {
                $summary['provider']['requests_with_session_id']++;
            }
            if ($requestUid && $sessionId) {
                $summary['provider']['requests_complete_context']++;
                if (count($summary['sample_hashes']) < 5) {
                    $summary['sample_hashes'][] = [
                        'source' => 'provider_requests',
                        'request_uid_sha256' => $hashValue($requestUid),
                        'session_id_sha256' => $hashValue($sessionId),
                    ];
                }
            }

            $scanValue('provider_requests', $row->id, 'request_payload', $decode($row->request_payload ?? null));
            $scanValue('provider_requests', $row->id, 'response_payload', $decode($row->response_payload ?? null));
            $scanValue('provider_requests', $row->id, 'error_message', $row->error_message ?? null);
        }
    }

    $walletComplete = $summary['wallet']['transaction_attempts_complete_context'] + $summary['wallet']['callback_logs_complete_context'];
    if (!$allowEmpty && $walletComplete < 1) {
        $summary['failures'][] = 'No wallet callback/attempt sample had both request_id and transaction_uid correlation.';
    }

    if (!$allowEmpty && $summary['provider']['requests_complete_context'] < 1) {
        $summary['failures'][] = 'No provider request diagnostic sample had both request_uid and session_id.';
    }

    $summary['secret_scan']['leaks_found'] = count($summary['secret_scan']['locations']);
    if ($summary['secret_scan']['leaks_found'] > 0) {
        $summary['failures'][] = 'Secret-like markers were found in correlation sources.';
    }

    $summary['status'] = count($summary['failures']) === 0 ? 'passed' : 'failed';

    if ($this->option('artifact')) {
        $artifactPath = (string) $this->option('artifact');
        $directory = dirname($artifactPath);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            $this->error('Unable to create artifact directory: ' . $directory);
            return 1;
        }

        file_put_contents($artifactPath, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
        $this->line('artifact: ' . $artifactPath);
    }

    if ($summary['status'] !== 'passed') {
        $this->error('B2B correlation evidence failed.');
        foreach ($summary['failures'] as $failure) {
            $this->line('- ' . $failure);
        }
        return 1;
    }

    $this->info('B2B correlation evidence verified.');
    $this->line('wallet_complete_context: ' . $walletComplete);
    $this->line('provider_complete_context: ' . $summary['provider']['requests_complete_context']);
    $this->line('secret_scan_leaks: ' . $summary['secret_scan']['leaks_found']);

    return 0;
})->describe('Validate redacted request/transaction/provider correlation evidence without storing raw payloads.');

Artisan::command('b2b:payload-redaction-audit {--write : Rewrite legacy payload fields with redacted values} {--limit=0 : Maximum rows per table, 0 scans all rows} {--batch=500 : Rows per query batch} {--artifact= : Optional JSON artifact path for release evidence}', function (B2BPayloadRedactionAuditor $auditor) {
    $report = $auditor->run(
        (bool) $this->option('write'),
        (int) $this->option('limit'),
        (int) $this->option('batch')
    );

    if ($this->option('artifact')) {
        $artifact = (string) $this->option('artifact');
        $directory = dirname($artifact);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            $this->error('Unable to create artifact directory: ' . $directory);
            return 1;
        }

        file_put_contents($artifact, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
        $this->line('artifact: ' . $artifact);
    }

    $this->line('B2B payload redaction audit');
    $this->line('mode: ' . $report['mode']);
    $this->line('scanned_rows: ' . $report['scanned_rows']);
    $this->line('scanned_fields: ' . $report['scanned_fields']);
    $this->line('findings: ' . $report['findings']);
    $this->line('updated_fields: ' . $report['updated_fields']);

    foreach ($report['tables'] as $table => $tableReport) {
        $this->line($table . ': rows=' . $tableReport['scanned_rows'] . ' findings=' . $tableReport['findings'] . ' updated=' . $tableReport['updated_fields']);
    }

    if (!empty($report['missing_targets'])) {
        $this->comment('missing_targets: ' . implode(', ', $report['missing_targets']));
    }

    if (!$this->option('write') && (int) $report['findings'] > 0) {
        $this->error('Unredacted legacy payload fields were found. Rerun with --write after approval, then rerun dry-run for evidence.');
        return 1;
    }

    return 0;
})->describe('Audit and optionally redact legacy B2B wallet payload fields without printing payload values.');

Artisan::command('b2b:release-check {--production : Enforce production release gates}', function (B2BReleaseGate $gate) {
    $result = $gate->run((bool) $this->option('production'), true, true);

    $this->line('B2B release gate checks');
    foreach ($result['checks'] as $check) {
        $line = strtoupper($check['status']) . ' ' . $check['name'] . ': ' . $check['message'];
        if ($check['status'] === 'fail') {
            $this->error($line);
        } elseif ($check['status'] === 'warn') {
            $this->comment($line);
        } else {
            $this->info($line);
        }
    }

    return $result['ok'] ? 0 : 1;
})->describe('Run B2B production release configuration checks.');

Artisan::command('b2b:evidence-check {path : Directory containing release-evidence.json} {--production : Enforce production launch evidence requirements}', function (B2BReleaseEvidenceChecker $checker) {
    $result = $checker->check($this->argument('path'), (bool) $this->option('production'));

    $this->line('B2B release evidence checks');
    foreach ($result['checks'] as $check) {
        $line = strtoupper($check['status']) . ' ' . $check['name'] . ': ' . $check['message'];
        if ($check['status'] === 'fail') {
            $this->error($line);
        } elseif ($check['status'] === 'warn') {
            $this->comment($line);
        } else {
            $this->info($line);
        }
    }

    return $result['ok'] ? 0 : 1;
})->describe('Validate an external B2B production release evidence package.');

Artisan::command('b2b:evidence-hash {path : Directory containing release-evidence.json} {--write : Write calculated hashes back to release-evidence.json}', function (B2BReleaseEvidenceChecker $checker) {
    $result = $checker->hashManifest($this->argument('path'), (bool) $this->option('write'));

    $this->line('B2B release evidence hash calculation');
    foreach ($result['checks'] as $check) {
        $line = strtoupper($check['status']) . ' ' . $check['name'] . ': ' . $check['message'];
        if ($check['status'] === 'fail') {
            $this->error($line);
        } else {
            $this->info($line);
        }
    }

    foreach ($result['hashes'] as $entry => $hashes) {
        foreach ($hashes as $artifact => $sha256) {
            $this->line($entry . ' ' . $artifact . ' ' . $sha256);
        }
    }

    if (!empty($result['written'])) {
        $this->info('Wrote calculated hashes to release-evidence.json.');
    }

    return $result['ok'] ? 0 : 1;
})->describe('Calculate SHA-256 hashes for B2B release evidence artifacts.');

Artisan::command('b2b:evidence-template {path : Directory where release-evidence.json should be created} {--release-id=} {--environment=production-canary} {--commit=} {--generated-at=} {--force : Overwrite an existing release-evidence.json}', function (B2BReleaseEvidenceChecker $checker) {
    $directory = (string) $this->argument('path');
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        $this->error('Unable to create release evidence directory: ' . $directory);
        return 1;
    }

    $manifestPath = rtrim($directory, DIRECTORY_SEPARATOR . '/\\') . DIRECTORY_SEPARATOR . 'release-evidence.json';
    if (file_exists($manifestPath) && !(bool) $this->option('force')) {
        $this->error('release-evidence.json already exists. Use --force to overwrite it.');
        return 1;
    }

    $manifest = $checker->templateManifest(
        $this->option('release-id'),
        $this->option('environment') ?: 'production-canary',
        $this->option('commit'),
        $this->option('generated-at')
    );

    file_put_contents(
        $manifestPath,
        json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
    );

    $this->info('Created B2B release evidence template.');
    $this->line('manifest: ' . $manifestPath);
    $this->line('next: add redacted artifacts, run b2b:evidence-hash --write, then b2b:evidence-check --production');

    return 0;
})->describe('Create a redacted B2B release evidence manifest skeleton.');
