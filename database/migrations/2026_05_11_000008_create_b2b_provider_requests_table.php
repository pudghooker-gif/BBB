<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateB2BProviderRequestsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('b2b_provider_requests')) {
            return;
        }

        Schema::create('b2b_provider_requests', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('operator_id')->nullable()->index();
            $table->string('provider')->index();
            $table->string('game_uid')->nullable()->index();
            $table->string('session_id')->nullable()->index();
            $table->string('request_uid')->unique();
            $table->string('action')->index();
            $table->string('status')->default('pending')->index();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('b2b_provider_requests');
    }
}
