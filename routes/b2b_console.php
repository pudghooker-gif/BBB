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
use VanguardLTE\B2B\Services\B2BOperatorAuditLogger;
use VanguardLTE\B2B\Services\B2BReleaseGate;
use VanguardLTE\B2B\Services\B2BSignature;

Artisan::command('b2b:make-operator {name} {--shop_id=} {--base_url=} {--wallet_callback_url=} {--currency=USD} {--max_rps=50} {--wallet_timeout_ms=3000}', function (B2BOperatorAuditLogger $audit) {
    if (!Schema::hasTable('b2b_operators') || !Schema::hasTable('b2b_operator_api_keys')) {
        $this->error('B2B tables are missing. Run: php artisan migrate');
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

    $apiKey = B2BOperatorApiKey::create([
        'operator_id' => $operator->id,
        'key_id' => $keyId,
        'secret_encrypted' => Crypt::encryptString($secret),
        'status' => B2BOperatorApiKey::STATUS_ACTIVE,
    ]);

    $audit->record($operator, 'operator.created', 'operator', $operatorUid, 'b2b:make-operator', 'Initial B2B operator provisioning.', [
        'operator_uid' => $operatorUid,
        'shop_id' => $operator->shop_id,
        'currency' => $currency,
    ]);
    $audit->record($operator, 'api_key.created', 'api_key', $apiKey->key_id, 'b2b:make-operator', 'Initial B2B API key provisioning.', [
        'key_id' => $apiKey->key_id,
    ]);

    $this->info('B2B operator created. Save this secret now; it is not stored in plaintext.');
    $this->line('');
    $this->line('X-Operator-Id: ' . $operatorUid);
    $this->line('X-Api-Key:     ' . $keyId);
    $this->line('Secret:        ' . $secret);
    $this->line('');
    $this->line('Next: php artisan b2b:show-hmac ' . $operatorUid . ' ' . $keyId . ' ' . $secret . ' GET /api/b2b/v1/operator/me');

    return 0;
});

Artisan::command('b2b:rotate-api-key {operator_uid} {--key-id=} {--actor=} {--reason=} {--revoke-existing}', function (B2BOperatorAuditLogger $audit) {
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

    $keyId = trim((string) $this->option('key-id'));
    if ($keyId === '') {
        $keyId = 'key_' . Str::lower(Str::random(16));
    }

    if (B2BOperatorApiKey::where('key_id', $keyId)->exists()) {
        $this->error('B2B API key id already exists.');
        return 1;
    }

    $secret = Str::random(64);
    $apiKey = B2BOperatorApiKey::create([
        'operator_id' => $operator->id,
        'key_id' => $keyId,
        'secret_encrypted' => Crypt::encryptString($secret),
        'status' => B2BOperatorApiKey::STATUS_ACTIVE,
    ]);

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
                'previous_status' => B2BOperatorApiKey::STATUS_ACTIVE,
                'new_status' => B2BOperatorApiKey::STATUS_DISABLED,
            ]);
        }
    }

    $audit->record($operator, 'api_key.rotated', 'api_key', $apiKey->key_id, $actor, $reason, [
        'key_id' => $apiKey->key_id,
        'revoke_existing' => (bool) $this->option('revoke-existing'),
        'disabled_existing' => $disabledExisting,
    ]);

    $this->info('B2B API key rotated. Save this secret now; it is not stored in plaintext.');
    $this->line('');
    $this->line('X-Operator-Id: ' . $operator->operator_uid);
    $this->line('X-Api-Key:     ' . $apiKey->key_id);
    $this->line('Secret:        ' . $secret);
    $this->line('Disabled existing keys: ' . $disabledExisting);

    return 0;
})->describe('Rotate a B2B operator API key and write an audit event.');

Artisan::command('b2b:revoke-api-key {operator_uid} {key_id} {--actor=} {--reason=}', function (B2BOperatorAuditLogger $audit) {
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

    $previousStatus = $apiKey->status;
    if ($apiKey->status !== B2BOperatorApiKey::STATUS_DISABLED) {
        $apiKey->forceFill(['status' => B2BOperatorApiKey::STATUS_DISABLED])->save();
    }

    $audit->record($operator, $previousStatus === B2BOperatorApiKey::STATUS_DISABLED ? 'api_key.revoke_noop' : 'api_key.revoked', 'api_key', $apiKey->key_id, $actor, $reason, [
        'previous_status' => $previousStatus,
        'new_status' => B2BOperatorApiKey::STATUS_DISABLED,
    ]);

    $this->info($previousStatus === B2BOperatorApiKey::STATUS_DISABLED ? 'B2B API key was already revoked.' : 'B2B API key revoked.');
    $this->line('operator_uid: ' . $operator->operator_uid);
    $this->line('key_id: ' . $apiKey->key_id);

    return 0;
})->describe('Revoke a B2B operator API key and write an audit event.');

Artisan::command('b2b:sync-games {--shop_id=} {--limit=0}', function () {
    if (!Schema::hasTable('b2b_game_catalog')) {
        $this->error('b2b_game_catalog table is missing. Run: php artisan migrate');
        return 1;
    }

    if (!Schema::hasTable('games')) {
        $this->error('games table was not found. Nothing to sync.');
        return 1;
    }

    $columns = Schema::getColumnListing('games');
    $query = DB::table('games');

    if ($this->option('shop_id') !== null && $this->option('shop_id') !== '' && in_array('shop_id', $columns, true)) {
        $query->where('shop_id', (int) $this->option('shop_id'));
    }

    if (in_array('view', $columns, true)) {
        $query->where('view', 1);
    }

    $limit = (int) $this->option('limit');
    if ($limit > 0) {
        $query->limit($limit);
    }

    $rows = $query->get();
    $created = 0;
    $updated = 0;

    foreach ($rows as $row) {
        $gameUid = null;
        foreach (['name', 'game_uid', 'id'] as $candidate) {
            if (isset($row->{$candidate}) && $row->{$candidate} !== '') {
                $gameUid = (string) $row->{$candidate};
                break;
            }
        }

        if (!$gameUid) {
            continue;
        }

        $title = isset($row->title) && $row->title ? $row->title : $gameUid;
        $category = isset($row->category) && $row->category ? $row->category : 'slots';

        $model = B2BGameCatalog::firstOrNew(['game_uid' => $gameUid]);
        $model->fill([
            'provider' => 'goldsvet_internal',
            'title' => $title,
            'category' => $category,
            'demo_supported' => true,
            'real_supported' => true,
            'status' => 'active',
            'metadata' => [
                'synced_from' => 'games',
                'source_id' => isset($row->id) ? $row->id : null,
                'shop_id' => isset($row->shop_id) ? $row->shop_id : null,
                'synced_at' => now()->toIso8601String(),
            ],
        ]);

        $model->exists ? $updated++ : $created++;
        $model->save();
    }

    $this->info('B2B game catalog synced. Created: ' . $created . ', updated: ' . $updated . ', scanned: ' . count($rows));
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

Artisan::command('b2b:health', function () {
    $this->line('B2B health summary');
    $this->line('operators: ' . (Schema::hasTable('b2b_operators') ? B2BOperator::count() : 'missing table'));
    $this->line('games: ' . (Schema::hasTable('b2b_game_catalog') ? B2BGameCatalog::count() : 'missing table'));
    $this->line('active sessions: ' . (Schema::hasTable('b2b_game_sessions') ? B2BGameSession::where('status', B2BGameSession::STATUS_ACTIVE)->count() : 'missing table'));
    $this->line('wallet transactions: ' . (Schema::hasTable('b2b_wallet_transactions') ? B2BWalletTransaction::count() : 'missing table'));
    return 0;
});

Artisan::command('b2b:release-check {--production : Enforce production release gates}', function (B2BReleaseGate $gate) {
    $result = $gate->run((bool) $this->option('production'));

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
