<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateB2BGameSessionsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('b2b_game_sessions')) {
            return;
        }

        Schema::create('b2b_game_sessions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('operator_id')->index();
            $table->unsignedBigInteger('operator_player_id')->index();
            $table->string('session_uid')->unique();
            $table->string('token_hash', 64)->index();
            $table->string('game_uid')->index();
            $table->string('provider')->default('goldsvet_internal')->index();
            $table->string('mode')->default('real');
            $table->string('currency', 3)->index();
            $table->string('language', 8)->default('en');
            $table->string('country', 2)->nullable();
            $table->string('return_url')->nullable();
            $table->string('launch_url')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('operator_id')->references('id')->on('b2b_operators')->onDelete('cascade');
            $table->foreign('operator_player_id')->references('id')->on('b2b_operator_players')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('b2b_game_sessions');
    }
}
