<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use VanguardLTE\B2B\Models\B2BOperator;
use VanguardLTE\B2B\Models\B2BOperatorApiKey;
use VanguardLTE\B2B\Services\B2BSignature;

trait B2BApiTestHelpers
{
    protected function signedB2BRequest($method, $uri, $body, array $headers, $remoteAddr = '127.0.0.1')
    {
        $server = [
            'REMOTE_ADDR' => $remoteAddr,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ];

        foreach ($headers as $name => $value) {
            $server['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $this->call($method, $uri, [], [], [], $server, $body);
    }

    protected function signedB2BHeaders($operatorUid, $keyId, $secret, $method, $path, $body, $nonce)
    {
        $timestamp = (string) time();
        $bodyHash = B2BSignature::bodyHash($body);
        $pathOnly = parse_url($path, PHP_URL_PATH) ?: '/';
        $queryString = parse_url($path, PHP_URL_QUERY) ?: '';
        $canonical = B2BSignature::canonicalFromParts($method, $pathOnly, $queryString, $bodyHash, $timestamp, $nonce);

        return [
            'X-Operator-Id' => $operatorUid,
            'X-Api-Key' => $keyId,
            'X-Timestamp' => $timestamp,
            'X-Nonce' => $nonce,
            'X-Body-Hash' => $bodyHash,
            'X-Signature' => hash_hmac('sha256', $canonical, $secret),
        ];
    }

    protected function createB2BOperator($operatorUid, $keyId, $secret, array $overrides = [])
    {
        $operator = B2BOperator::create(array_merge([
            'operator_uid' => $operatorUid,
            'name' => 'Demo Operator ' . $operatorUid,
            'status' => B2BOperator::STATUS_ACTIVE,
            'default_currency' => 'USD',
            'allowed_currencies' => ['USD'],
            'ip_whitelist' => ['127.0.0.0/8'],
            'failure_count' => 0,
            'max_rps' => 50,
            'wallet_timeout_ms' => 5000,
            'connect_timeout_ms' => 1500,
        ], $overrides));

        B2BOperatorApiKey::create([
            'operator_id' => $operator->id,
            'key_id' => $keyId,
            'secret_encrypted' => Crypt::encryptString($secret),
            'status' => B2BOperatorApiKey::STATUS_ACTIVE,
        ]);

        return $operator;
    }

    protected function resetB2BTables()
    {
        foreach ([
            'b2b_wallet_manual_actions',
            'b2b_wallet_reconciliation_items',
            'b2b_wallet_transaction_transitions',
            'b2b_wallet_transaction_attempts',
            'b2b_wallet_callback_logs',
            'b2b_settlements',
            'b2b_wallet_transactions',
            'b2b_game_sessions',
            'b2b_operator_players',
            'b2b_operator_api_keys',
            'b2b_operators',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('b2b_operators', function (Blueprint $table) {
            $table->increments('id');
            $table->string('operator_uid')->unique();
            $table->string('name');
            $table->integer('shop_id')->nullable();
            $table->string('status')->default('active');
            $table->string('base_url')->nullable();
            $table->string('wallet_callback_url')->nullable();
            $table->string('default_currency', 3)->default('USD');
            $table->json('allowed_currencies')->nullable();
            $table->json('allowed_countries')->nullable();
            $table->json('ip_whitelist')->nullable();
            $table->json('settings')->nullable();
            $table->integer('failure_count')->default(0);
            $table->timestamp('last_failure_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->integer('max_rps')->default(50);
            $table->integer('wallet_timeout_ms')->default(5000);
            $table->integer('connect_timeout_ms')->default(1500);
            $table->integer('circuit_breaker_threshold')->default(5);
            $table->integer('circuit_breaker_cooldown_seconds')->default(30);
            $table->timestamp('circuit_open_until')->nullable();
            $table->timestamps();
        });

        Schema::create('b2b_operator_api_keys', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('operator_id');
            $table->string('key_id');
            $table->text('secret_encrypted');
            $table->string('status')->default('active');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('b2b_operator_players', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('operator_id');
            $table->string('external_player_id');
            $table->string('currency', 3)->default('USD');
            $table->string('country', 2)->nullable();
            $table->string('language', 8)->default('en');
            $table->string('status')->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('b2b_game_sessions', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('operator_id');
            $table->integer('operator_player_id');
            $table->string('session_uid')->unique();
            $table->string('token_hash', 64)->nullable();
            $table->string('game_uid')->nullable();
            $table->string('provider')->default('goldsvet_internal');
            $table->string('mode')->default('real');
            $table->string('currency', 3)->default('USD');
            $table->string('language', 8)->default('en');
            $table->string('country', 2)->nullable();
            $table->string('return_url')->nullable();
            $table->string('launch_url')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->integer('heartbeat_timeout_seconds')->default(120);
            $table->string('failure_code')->nullable();
            $table->text('failure_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('b2b_wallet_transactions', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('operator_id');
            $table->integer('operator_player_id')->nullable();
            $table->string('session_id')->nullable();
            $table->string('game_uid')->nullable();
            $table->string('round_id')->nullable();
            $table->string('transaction_uid')->nullable();
            $table->string('transaction_id')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->string('request_hash', 64)->nullable();
            $table->string('type')->nullable();
            $table->decimal('amount', 20, 8)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('pending');
            $table->json('raw_request')->nullable();
            $table->json('raw_response')->nullable();
            $table->integer('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->integer('operator_response_code')->nullable();
            $table->longText('operator_response_body')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('b2b_wallet_callback_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('operator_id');
            $table->integer('wallet_transaction_id')->nullable();
            $table->string('direction');
            $table->string('endpoint')->nullable();
            $table->integer('http_status')->nullable();
            $table->json('request_body')->nullable();
            $table->json('response_body')->nullable();
            $table->timestamps();
        });

        Schema::create('b2b_settlements', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('operator_id');
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->string('currency', 3)->default('USD');
            $table->decimal('bets_amount', 20, 8)->default(0);
            $table->decimal('wins_amount', 20, 8)->default(0);
            $table->decimal('refunds_amount', 20, 8)->default(0);
            $table->decimal('ggr_amount', 20, 8)->default(0);
            $table->decimal('aggregator_fee_amount', 20, 8)->default(0);
            $table->decimal('provider_fee_amount', 20, 8)->default(0);
            $table->decimal('net_amount', 20, 8)->default(0);
            $table->string('status')->default('draft');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('b2b_wallet_transaction_attempts', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('wallet_transaction_id')->nullable();
            $table->integer('operator_id')->nullable();
            $table->string('transaction_uid')->nullable();
            $table->string('type', 50)->nullable();
            $table->integer('attempt_no')->default(1);
            $table->string('url', 500)->nullable();
            $table->integer('timeout_ms')->default(5000);
            $table->integer('http_status')->nullable();
            $table->string('result', 50)->default('pending');
            $table->integer('duration_ms')->nullable();
            $table->longText('request_body')->nullable();
            $table->longText('response_body')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('b2b_wallet_transaction_transitions', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('wallet_transaction_id')->nullable();
            $table->integer('operator_id')->nullable();
            $table->string('transaction_uid')->nullable();
            $table->string('from_status', 50)->nullable();
            $table->string('to_status', 50);
            $table->string('reason', 100)->nullable();
            $table->string('actor', 100)->default('system');
            $table->json('context')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('b2b_wallet_reconciliation_items', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('wallet_transaction_id')->nullable();
            $table->integer('operator_id')->nullable();
            $table->string('transaction_uid')->nullable();
            $table->string('status', 50);
            $table->string('reason', 100);
            $table->string('priority', 20)->default('normal');
            $table->string('state', 30)->default('open');
            $table->json('context')->nullable();
            $table->timestamp('detected_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('b2b_wallet_manual_actions', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('wallet_transaction_id')->nullable();
            $table->integer('operator_id')->nullable();
            $table->string('transaction_uid')->nullable();
            $table->string('action', 80);
            $table->string('from_status', 50)->nullable();
            $table->string('to_status', 50);
            $table->string('actor');
            $table->text('reason');
            $table->json('context')->nullable();
            $table->timestamps();
        });
    }
}
