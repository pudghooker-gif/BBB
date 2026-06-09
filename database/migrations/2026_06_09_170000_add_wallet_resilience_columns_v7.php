<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddWalletResilienceColumnsV7 extends Migration
{
    public function up()
    {
        if (Schema::hasTable('b2b_operators')) {
            Schema::table('b2b_operators', function (Blueprint $table) {
                if (!Schema::hasColumn('b2b_operators', 'wallet_callback_url')) {
                    $table->string('wallet_callback_url', 500)->nullable()->after('callback_url');
                }
                if (!Schema::hasColumn('b2b_operators', 'wallet_timeout_ms')) {
                    $table->unsignedInteger('wallet_timeout_ms')->default(5000)->after('wallet_callback_url');
                }
                if (!Schema::hasColumn('b2b_operators', 'wallet_secret')) {
                    $table->string('wallet_secret', 255)->nullable()->after('wallet_timeout_ms');
                }
                if (!Schema::hasColumn('b2b_operators', 'failure_count')) {
                    $table->unsignedInteger('failure_count')->default(0)->after('status');
                }
                if (!Schema::hasColumn('b2b_operators', 'last_failure_at')) {
                    $table->timestamp('last_failure_at')->nullable()->after('failure_count');
                }
                if (!Schema::hasColumn('b2b_operators', 'circuit_open_until')) {
                    $table->timestamp('circuit_open_until')->nullable()->after('last_failure_at');
                }
                if (!Schema::hasColumn('b2b_operators', 'max_rps')) {
                    $table->unsignedInteger('max_rps')->default(100)->after('circuit_open_until');
                }
            });
        }

        if (Schema::hasTable('b2b_wallet_transactions')) {
            Schema::table('b2b_wallet_transactions', function (Blueprint $table) {
                if (!Schema::hasColumn('b2b_wallet_transactions', 'idempotency_key')) {
                    $table->string('idempotency_key', 191)->nullable()->after('transaction_uid');
                }
                if (!Schema::hasColumn('b2b_wallet_transactions', 'request_hash')) {
                    $table->string('request_hash', 64)->nullable()->after('idempotency_key');
                }
                if (!Schema::hasColumn('b2b_wallet_transactions', 'attempts')) {
                    $table->unsignedInteger('attempts')->default(0)->after('status');
                }
                if (!Schema::hasColumn('b2b_wallet_transactions', 'processed_at')) {
                    $table->timestamp('processed_at')->nullable()->after('attempts');
                }
                if (!Schema::hasColumn('b2b_wallet_transactions', 'locked_until')) {
                    $table->timestamp('locked_until')->nullable()->after('processed_at');
                }
                if (!Schema::hasColumn('b2b_wallet_transactions', 'last_error')) {
                    $table->text('last_error')->nullable()->after('locked_until');
                }
                if (!Schema::hasColumn('b2b_wallet_transactions', 'operator_response_code')) {
                    $table->unsignedSmallInteger('operator_response_code')->nullable()->after('last_error');
                }
                if (!Schema::hasColumn('b2b_wallet_transactions', 'operator_response_body')) {
                    $table->longText('operator_response_body')->nullable()->after('operator_response_code');
                }
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('b2b_wallet_transactions')) {
            Schema::table('b2b_wallet_transactions', function (Blueprint $table) {
                foreach ([
                    'idempotency_key', 'request_hash', 'attempts', 'processed_at', 'locked_until',
                    'last_error', 'operator_response_code', 'operator_response_body'
                ] as $column) {
                    if (Schema::hasColumn('b2b_wallet_transactions', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('b2b_operators')) {
            Schema::table('b2b_operators', function (Blueprint $table) {
                foreach ([
                    'wallet_callback_url', 'wallet_timeout_ms', 'wallet_secret', 'failure_count',
                    'last_failure_at', 'circuit_open_until', 'max_rps'
                ] as $column) {
                    if (Schema::hasColumn('b2b_operators', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
}
