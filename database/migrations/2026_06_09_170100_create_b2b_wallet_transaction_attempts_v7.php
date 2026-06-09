<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateB2bWalletTransactionAttemptsV7 extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('b2b_wallet_transaction_attempts')) {
            Schema::create('b2b_wallet_transaction_attempts', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('wallet_transaction_id')->nullable();
                $table->unsignedBigInteger('operator_id')->nullable();
                $table->string('transaction_uid', 191)->nullable();
                $table->string('type', 50)->nullable();
                $table->unsignedInteger('attempt_no')->default(1);
                $table->string('url', 500)->nullable();
                $table->unsignedInteger('timeout_ms')->default(5000);
                $table->unsignedSmallInteger('http_status')->nullable();
                $table->string('result', 50)->default('pending');
                $table->unsignedInteger('duration_ms')->nullable();
                $table->longText('request_body')->nullable();
                $table->longText('response_body')->nullable();
                $table->text('error')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();

                $table->index(['operator_id', 'created_at'], 'b2b_wta_operator_created_idx');
                $table->index(['transaction_uid'], 'b2b_wta_tx_uid_idx');
                $table->index(['result', 'created_at'], 'b2b_wta_result_created_idx');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('b2b_wallet_transaction_attempts');
    }
}
