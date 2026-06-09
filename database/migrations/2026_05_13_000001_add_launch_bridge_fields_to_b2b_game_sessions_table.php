<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLaunchBridgeFieldsToB2BGameSessionsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('b2b_game_sessions')) {
            return;
        }

        Schema::table('b2b_game_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('b2b_game_sessions', 'shadow_user_id')) {
                $table->unsignedBigInteger('shadow_user_id')->nullable()->index()->after('operator_player_id');
            }
            if (!Schema::hasColumn('b2b_game_sessions', 'legacy_launch_token')) {
                $table->string('legacy_launch_token', 191)->nullable()->index()->after('launch_url');
            }
            if (!Schema::hasColumn('b2b_game_sessions', 'legacy_launch_url')) {
                $table->string('legacy_launch_url', 2048)->nullable()->after('legacy_launch_token');
            }
            if (!Schema::hasColumn('b2b_game_sessions', 'launched_at')) {
                $table->timestamp('launched_at')->nullable()->index()->after('last_seen_at');
            }
            if (!Schema::hasColumn('b2b_game_sessions', 'launch_attempts')) {
                $table->unsignedInteger('launch_attempts')->default(0)->after('heartbeat_timeout_seconds');
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('b2b_game_sessions')) {
            return;
        }
    }
}
