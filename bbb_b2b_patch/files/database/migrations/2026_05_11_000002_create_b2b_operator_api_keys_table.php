<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateB2BOperatorApiKeysTable extends Migration
{
    public function up()
    {
        Schema::create('b2b_operator_api_keys', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('operator_id')->index();
            $table->string('key_id')->unique();
            $table->text('secret_encrypted');
            $table->string('status')->default('active')->index();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('operator_id')->references('id')->on('b2b_operators')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('b2b_operator_api_keys');
    }
}
