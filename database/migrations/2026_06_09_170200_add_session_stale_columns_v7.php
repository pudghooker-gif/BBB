<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSessionStaleColumnsV7 extends Migration
{
    public function up()
    {
        if (Schema::hasTable('b2b_game_sessions')) {
            Schema::table('b2b_game_sessions', function (Blueprint $table) {
                if (!Schema::hasColumn('b2b_game_sessions', 'heartbeat_at')) {
                    $table->timestamp('heartbeat_at')->nullable()->after('last_seen_at');
                }
                if (!Schema::hasColumn('b2b_game_sessions', 'stale_at')) {
                    $table->timestamp('stale_at')->nullable()->after('heartbeat_at');
                }
                if (!Schema::hasColumn('b2b_game_sessions', 'close_reason')) {
                    $table->string('close_reason', 100)->nullable()->after('closed_at');
                }
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('b2b_game_sessions')) {
            Schema::table('b2b_game_sessions', function (Blueprint $table) {
                foreach (['heartbeat_at', 'stale_at', 'close_reason'] as $column) {
                    if (Schema::hasColumn('b2b_game_sessions', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
}
