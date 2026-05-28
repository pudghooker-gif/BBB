<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateB2BOperatorPlayersTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('b2b_operator_players')) {
            return;
        }

        Schema::create('b2b_operator_players', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('operator_id')->index();
            $table->string('external_player_id');
            $table->unsignedBigInteger('shadow_user_id')->nullable()->index();
            $table->string('currency', 3)->index();
            $table->string('country', 2)->nullable()->index();
            $table->string('language', 8)->default('en');
            $table->string('status')->default('active')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['operator_id', 'external_player_id'], 'b2b_operator_player_unique');
            $table->foreign('operator_id')->references('id')->on('b2b_operators')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('b2b_operator_players');
    }
}
