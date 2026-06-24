<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateB2BOperatorGameAssignmentsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('b2b_operator_game_assignments')) {
            return;
        }

        Schema::create('b2b_operator_game_assignments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('operator_id')->index();
            $table->string('game_uid')->index();
            $table->string('provider')->default('goldsvet_internal')->index();
            $table->string('status', 30)->default('allowed')->index();
            $table->boolean('demo_enabled')->default(true);
            $table->boolean('real_enabled')->default(true);
            $table->json('allowed_currencies')->nullable();
            $table->json('allowed_countries')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['operator_id', 'provider', 'game_uid'], 'b2b_operator_game_unique');
            $table->foreign('operator_id')->references('id')->on('b2b_operators')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('b2b_operator_game_assignments');
    }
}
