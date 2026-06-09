<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use VanguardLTE\B2B\Models\B2BOperator;
use VanguardLTE\B2B\Models\B2BOperatorApiKey;
use VanguardLTE\B2B\Services\SandboxWalletService;

Artisan::command('b2b:sandbox-health', function () {
    $this->line('B2B sandbox health');
    $this->line('enabled: '.(app(SandboxWalletService::class)->isEnabled() ? 'yes' : 'no'));
    $this->line('operators table: '.(Schema::hasTable('b2b_operators') ? 'ok' : 'missing'));
    $this->line('sandbox wallets table: '.(Schema::hasTable('b2b_sandbox_wallets') ? 'ok' : 'missing'));
    $this->line('sandbox entries table: '.(Schema::hasTable('b2b_sandbox_wallet_entries') ? 'ok' : 'missing'));

    if (Schema::hasTable('b2b_sandbox_wallets')) {
        $this->line('sandbox wallets: '.DB::table('b2b_sandbox_wallets')->count());
    }
    if (Schema::hasTable('b2b_sandbox_wallet_entries')) {
        $this->line('sandbox entries: '.DB::table('b2b_sandbox_wallet_entries')->count());
    }

    return 0;
})->describe('Show B2B sandbox wallet health.');

Artisan::command('b2b:sandbox-operator {name=SandboxOperator} {--shop_id=1} {--currency=USD} {--balance=1000} {--player_id=demo_player} {--app_url=}', function (SandboxWalletService $walletService) {
    if (!Schema::hasTable('b2b_operators') || !Schema::hasTable('b2b_operator_api_keys')) {
        $this->error('B2B operator tables are missing. Run: php artisan migrate');
        return 1;
    }

    if (!Schema::hasTable('b2b_sandbox_wallets')) {
        $this->error('Sandbox tables are missing. Run: php artisan migrate');
        return 1;
    }

    $name = trim((string) $this->argument('name')) ?: 'SandboxOperator';
    $currency = strtoupper((string) $this->option('currency'));
    $playerId = (string) $this->option('player_id');
    $balance = (float) $this->option('balance');
    $appUrl = $this->option('app_url') ?: config('app.url', 'http://localhost');
    $appUrl = rtrim($appUrl, '/');

    $operatorUid = 'op_sandbox_' . Str::lower(Str::random(8));
    $keyId = 'key_sandbox_' . Str::lower(Str::random(12));
    $secret = Str::random(64);
    $walletUrl = $appUrl . '/api/b2b/sandbox/wallet?operator_uid=' . $operatorUid;

    $data = [
        'operator_uid' => $operatorUid,
        'name' => $name,
        'shop_id' => $this->option('shop_id') !== null && $this->option('shop_id') !== '' ? (int) $this->option('shop_id') : null,
        'status' => B2BOperator::STATUS_ACTIVE,
        'base_url' => $appUrl,
        'wallet_callback_url' => $walletUrl,
        'default_currency' => $currency,
        'allowed_currencies' => [$currency],
        'settings' => [
            'sandbox' => true,
            'created_by' => 'b2b:sandbox-operator',
            'created_at' => now()->toIso8601String(),
        ],
    ];

    foreach ([
        'max_rps' => 50,
        'wallet_timeout_ms' => 3000,
        'connect_timeout_ms' => 1500,
        'circuit_breaker_threshold' => 10,
        'circuit_breaker_cooldown_seconds' => 30,
    ] as $column => $value) {
        if (Schema::hasColumn('b2b_operators', $column)) {
            $data[$column] = $value;
        }
    }

    $operator = B2BOperator::create($data);

    B2BOperatorApiKey::create([
        'operator_id' => $operator->id,
        'key_id' => $keyId,
        'secret_encrypted' => Crypt::encryptString($secret),
        'status' => B2BOperatorApiKey::STATUS_ACTIVE,
    ]);

    $walletService->ensureWallet($operator->id, $playerId, $currency, $balance, [
        'created_by' => 'b2b:sandbox-operator',
    ]);

    $body = json_encode(['player_id' => $playerId, 'currency' => $currency], JSON_UNESCAPED_SLASHES);
    $timestamp = (string) time();
    $nonce = Str::random(24);
    $signature = hash_hmac('sha256', $timestamp.'.'.$nonce.'.'.$body, $secret);

    $this->info('Sandbox operator created. Save credentials now; the secret is not stored in plaintext.');
    $this->line('');
    $this->line('X-Operator-Id: ' . $operatorUid);
    $this->line('X-Api-Key:     ' . $keyId);
    $this->line('Secret:        ' . $secret);
    $this->line('Wallet URL:    ' . $walletUrl);
    $this->line('Demo player:   ' . $playerId . ' / ' . $currency . ' / balance ' . $balance);
    $this->line('');
    $this->line('Example signed request:');
    $this->line('curl -X POST "'.$appUrl.'/api/b2b/v1/wallet/balance" -H "Content-Type: application/json" -H "X-Operator-Id: '.$operatorUid.'" -H "X-Api-Key: '.$keyId.'" -H "X-Timestamp: '.$timestamp.'" -H "X-Nonce: '.$nonce.'" -H "X-Signature: '.$signature.'" --data \''.$body.'\'');

    return 0;
})->describe('Create a test B2B operator wired to the internal sandbox wallet.');

Artisan::command('b2b:sandbox-wallet {operator_uid} {player_id=demo_player} {--currency=USD} {--balance=1000}', function (SandboxWalletService $walletService) {
    if (!Schema::hasTable('b2b_operators')) {
        $this->error('b2b_operators table missing. Run: php artisan migrate');
        return 1;
    }
    if (!Schema::hasTable('b2b_sandbox_wallets')) {
        $this->error('b2b_sandbox_wallets table missing. Run: php artisan migrate');
        return 1;
    }

    $operator = B2BOperator::where('operator_uid', $this->argument('operator_uid'))->first();
    if (!$operator) {
        $this->error('Operator not found: '.$this->argument('operator_uid'));
        return 1;
    }

    $wallet = $walletService->ensureWallet(
        $operator->id,
        (string) $this->argument('player_id'),
        strtoupper((string) $this->option('currency')),
        (float) $this->option('balance'),
        ['created_by' => 'b2b:sandbox-wallet']
    );

    $wallet->balance = (float) $this->option('balance');
    $wallet->status = 'active';
    $wallet->save();

    $this->info('Sandbox wallet ready.');
    $this->line('operator_uid: '.$operator->operator_uid);
    $this->line('player_id: '.$wallet->external_player_id);
    $this->line('currency: '.$wallet->currency);
    $this->line('balance: '.$wallet->balance);

    return 0;
})->describe('Create or reset a sandbox wallet for a B2B operator player.');
