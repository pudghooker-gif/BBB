<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateB2BOperatorsTable extends Migration
{
    public function up()
    {
        Schema::create('b2b_operators', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('operator_uid')->unique();
            $table->string('name');
            $table->unsignedBigInteger('shop_id')->nullable()->index();
            $table->string('status')->default('active')->index();
            $table->string('base_url')->nullable();
            $table->string('wallet_callback_url')->nullable();
            $table->text('callback_secret_encrypted')->nullable();
            $table->string('default_currency', 3)->default('USD');
            $table->json('allowed_currencies')->nullable();
            $table->json('allowed_countries')->nullable();
            $table->json('ip_whitelist')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('b2b_operators');
    }
}
