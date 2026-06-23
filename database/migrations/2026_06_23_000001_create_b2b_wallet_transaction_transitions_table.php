<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateB2BWalletTransactionTransitionsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('b2b_wallet_transaction_transitions')) {
            return;
        }

        Schema::create('b2b_wallet_transaction_transitions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('wallet_transaction_id')->nullable()->index();
            $table->unsignedBigInteger('operator_id')->nullable()->index();
            $table->string('transaction_uid', 191)->nullable()->index();
            $table->string('from_status', 50)->nullable()->index();
            $table->string('to_status', 50)->index();
            $table->string('reason', 100)->nullable()->index();
            $table->string('actor', 100)->default('system');
            $table->json('context')->nullable();
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['operator_id', 'created_at'], 'b2b_wtt_operator_created_idx');
            $table->index(['wallet_transaction_id', 'created_at'], 'b2b_wtt_transaction_created_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('b2b_wallet_transaction_transitions');
    }
}
