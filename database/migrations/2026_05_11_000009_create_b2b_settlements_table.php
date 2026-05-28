<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateB2BSettlementsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('b2b_settlements')) {
            return;
        }

        Schema::create('b2b_settlements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('operator_id')->index();
            $table->timestamp('period_start')->index();
            $table->timestamp('period_end')->index();
            $table->string('currency', 3)->index();
            $table->decimal('bets_amount', 20, 8)->default(0);
            $table->decimal('wins_amount', 20, 8)->default(0);
            $table->decimal('refunds_amount', 20, 8)->default(0);
            $table->decimal('ggr_amount', 20, 8)->default(0);
            $table->decimal('aggregator_fee_amount', 20, 8)->default(0);
            $table->decimal('provider_fee_amount', 20, 8)->default(0);
            $table->decimal('net_amount', 20, 8)->default(0);
            $table->string('status')->default('draft')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('operator_id')->references('id')->on('b2b_operators')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('b2b_settlements');
    }
}
