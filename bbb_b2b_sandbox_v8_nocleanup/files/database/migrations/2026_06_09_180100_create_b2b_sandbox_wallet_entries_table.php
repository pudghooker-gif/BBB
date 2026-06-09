<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateB2BSandboxWalletEntriesTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('b2b_sandbox_wallet_entries')) {
            return;
        }

        Schema::create('b2b_sandbox_wallet_entries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('wallet_id')->index();
            $table->unsignedBigInteger('operator_id')->index();
            $table->string('action')->index();
            $table->string('transaction_id')->nullable()->index();
            $table->string('idempotency_key')->index();
            $table->decimal('amount', 20, 8)->default(0);
            $table->string('currency', 3)->index();
            $table->decimal('balance_before', 20, 8)->nullable();
            $table->decimal('balance_after', 20, 8)->nullable();
            $table->string('status')->default('success')->index();
            $table->json('raw_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamps();

            $table->unique(['operator_id', 'idempotency_key'], 'b2b_sandbox_entry_idempotency_unique');
            $table->foreign('wallet_id')->references('id')->on('b2b_sandbox_wallets')->onDelete('cascade');
            $table->foreign('operator_id')->references('id')->on('b2b_operators')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('b2b_sandbox_wallet_entries');
    }
}
