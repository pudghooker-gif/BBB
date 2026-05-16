<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateB2BWalletCallbackLogsTable extends Migration
{
    public function up()
    {
        Schema::create('b2b_wallet_callback_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('operator_id')->index();
            $table->unsignedBigInteger('wallet_transaction_id')->nullable()->index();
            $table->string('direction')->index();
            $table->string('endpoint')->nullable();
            $table->integer('http_status')->nullable();
            $table->json('request_headers')->nullable();
            $table->json('request_body')->nullable();
            $table->json('response_headers')->nullable();
            $table->json('response_body')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('operator_id')->references('id')->on('b2b_operators')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('b2b_wallet_callback_logs');
    }
}
