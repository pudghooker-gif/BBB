<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateB2BWalletManualActionsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('b2b_wallet_manual_actions')) {
            return;
        }

        Schema::create('b2b_wallet_manual_actions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('wallet_transaction_id')->nullable()->index();
            $table->unsignedBigInteger('operator_id')->nullable()->index();
            $table->string('transaction_uid', 191)->nullable()->index();
            $table->string('action', 80)->index();
            $table->string('from_status', 50)->nullable()->index();
            $table->string('to_status', 50)->index();
            $table->string('actor', 191)->index();
            $table->text('reason');
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['operator_id', 'created_at'], 'b2b_wma_operator_created_idx');
            $table->index(['wallet_transaction_id', 'created_at'], 'b2b_wma_transaction_created_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('b2b_wallet_manual_actions');
    }
}
