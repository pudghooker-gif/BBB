<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateB2BWalletReconciliationItemsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('b2b_wallet_reconciliation_items')) {
            return;
        }

        Schema::create('b2b_wallet_reconciliation_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('wallet_transaction_id')->nullable()->index();
            $table->unsignedBigInteger('operator_id')->nullable()->index();
            $table->string('transaction_uid', 191)->nullable()->index();
            $table->string('status', 50)->index();
            $table->string('reason', 100)->index();
            $table->string('priority', 20)->default('normal')->index();
            $table->string('state', 30)->default('open')->index();
            $table->json('context')->nullable();
            $table->timestamp('detected_at')->nullable()->index();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['operator_id', 'state', 'detected_at'], 'b2b_wri_operator_state_detected_idx');
            $table->index(['wallet_transaction_id', 'state'], 'b2b_wri_transaction_state_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('b2b_wallet_reconciliation_items');
    }
}
