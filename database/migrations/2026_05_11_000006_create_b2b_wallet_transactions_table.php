<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateB2BWalletTransactionsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('b2b_wallet_transactions')) {
            return;
        }

        Schema::create('b2b_wallet_transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('operator_id')->index();
            $table->unsignedBigInteger('operator_player_id')->nullable()->index();
            $table->string('session_id')->nullable()->index();
            $table->string('game_uid')->nullable()->index();
            $table->string('provider')->default('goldsvet_internal')->index();
            $table->string('round_id')->nullable()->index();
            $table->string('transaction_uid')->index();
            $table->string('idempotency_key')->index();
            $table->string('type')->index();
            $table->decimal('amount', 20, 8)->default(0);
            $table->string('currency', 3)->index();
            $table->string('status')->default('pending')->index();
            $table->decimal('balance_before', 20, 8)->nullable();
            $table->decimal('balance_after', 20, 8)->nullable();
            $table->json('raw_request')->nullable();
            $table->json('raw_response')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['operator_id', 'idempotency_key'], 'b2b_operator_idempotency_unique');
            $table->foreign('operator_id')->references('id')->on('b2b_operators')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('b2b_wallet_transactions');
    }
}
