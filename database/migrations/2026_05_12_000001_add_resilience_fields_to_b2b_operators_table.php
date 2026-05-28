<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddResilienceFieldsToB2BOperatorsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('b2b_operators')) {
            return;
        }

        Schema::table('b2b_operators', function (Blueprint $table) {
            if (!Schema::hasColumn('b2b_operators', 'failure_count')) {
                $table->unsignedInteger('failure_count')->default(0)->after('status');
            }
            if (!Schema::hasColumn('b2b_operators', 'last_failure_at')) {
                $table->timestamp('last_failure_at')->nullable()->after('failure_count');
            }
            if (!Schema::hasColumn('b2b_operators', 'last_success_at')) {
                $table->timestamp('last_success_at')->nullable()->after('last_failure_at');
            }
            if (!Schema::hasColumn('b2b_operators', 'circuit_open_until')) {
                $table->timestamp('circuit_open_until')->nullable()->index()->after('last_success_at');
            }
            if (!Schema::hasColumn('b2b_operators', 'circuit_breaker_threshold')) {
                $table->unsignedInteger('circuit_breaker_threshold')->default(5)->after('circuit_open_until');
            }
            if (!Schema::hasColumn('b2b_operators', 'circuit_breaker_cooldown_seconds')) {
                $table->unsignedInteger('circuit_breaker_cooldown_seconds')->default(30)->after('circuit_breaker_threshold');
            }
            if (!Schema::hasColumn('b2b_operators', 'max_rps')) {
                $table->unsignedInteger('max_rps')->default(50)->after('circuit_breaker_cooldown_seconds');
            }
            if (!Schema::hasColumn('b2b_operators', 'wallet_timeout_ms')) {
                $table->unsignedInteger('wallet_timeout_ms')->default(5000)->after('max_rps');
            }
            if (!Schema::hasColumn('b2b_operators', 'connect_timeout_ms')) {
                $table->unsignedInteger('connect_timeout_ms')->default(1500)->after('wallet_timeout_ms');
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('b2b_operators')) {
            return;
        }

        Schema::table('b2b_operators', function (Blueprint $table) {
            foreach ([
                'failure_count',
                'last_failure_at',
                'last_success_at',
                'circuit_open_until',
                'circuit_breaker_threshold',
                'circuit_breaker_cooldown_seconds',
                'max_rps',
                'wallet_timeout_ms',
                'connect_timeout_ms',
            ] as $column) {
                if (Schema::hasColumn('b2b_operators', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}
