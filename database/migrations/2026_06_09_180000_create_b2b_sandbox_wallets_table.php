<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateB2BSandboxWalletsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('b2b_sandbox_wallets')) {
            return;
        }

        Schema::create('b2b_sandbox_wallets', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('operator_id')->index();
            $table->string('external_player_id')->index();
            $table->string('currency', 3)->index();
            $table->decimal('balance', 20, 8)->default(0);
            $table->string('status')->default('active')->index();
            $table->timestamp('last_transaction_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['operator_id', 'external_player_id', 'currency'], 'b2b_sandbox_wallet_unique');
            $table->foreign('operator_id')->references('id')->on('b2b_operators')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('b2b_sandbox_wallets');
    }
}
