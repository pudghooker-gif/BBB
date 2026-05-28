<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSessionResilienceFieldsToB2BGameSessionsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('b2b_game_sessions')) {
            return;
        }

        Schema::table('b2b_game_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('b2b_game_sessions', 'last_seen_at')) {
                $table->timestamp('last_seen_at')->nullable()->index()->after('expires_at');
            }
            if (!Schema::hasColumn('b2b_game_sessions', 'closed_at')) {
                $table->timestamp('closed_at')->nullable()->index()->after('last_seen_at');
            }
            if (!Schema::hasColumn('b2b_game_sessions', 'heartbeat_timeout_seconds')) {
                $table->unsignedInteger('heartbeat_timeout_seconds')->default(120)->after('closed_at');
            }
            if (!Schema::hasColumn('b2b_game_sessions', 'failure_code')) {
                $table->string('failure_code')->nullable()->after('heartbeat_timeout_seconds');
            }
            if (!Schema::hasColumn('b2b_game_sessions', 'failure_message')) {
                $table->text('failure_message')->nullable()->after('failure_code');
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('b2b_game_sessions')) {
            return;
        }

        Schema::table('b2b_game_sessions', function (Blueprint $table) {
            foreach (['last_seen_at', 'closed_at', 'heartbeat_timeout_seconds', 'failure_code', 'failure_message'] as $column) {
                if (Schema::hasColumn('b2b_game_sessions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}
