<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateB2BGameCatalogTable extends Migration
{
    public function up()
    {
        Schema::create('b2b_game_catalog', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('game_uid')->unique();
            $table->string('provider')->index();
            $table->string('title');
            $table->string('category')->default('slots')->index();
            $table->decimal('rtp', 6, 2)->nullable();
            $table->string('volatility')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->boolean('demo_supported')->default(true);
            $table->boolean('real_supported')->default(true);
            $table->json('supported_currencies')->nullable();
            $table->json('supported_countries')->nullable();
            $table->string('status')->default('active')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('b2b_game_catalog');
    }
}
