<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCatalogRuntimeFieldsToB2BGameCatalogTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('b2b_game_catalog')) {
            return;
        }

        Schema::table('b2b_game_catalog', function (Blueprint $table) {
            if (!Schema::hasColumn('b2b_game_catalog', 'provider_game_id')) {
                $table->string('provider_game_id')->nullable()->index();
            }

            if (!Schema::hasColumn('b2b_game_catalog', 'canonical_game_id')) {
                $table->string('canonical_game_id')->nullable()->index();
            }

            if (!Schema::hasColumn('b2b_game_catalog', 'slug')) {
                $table->string('slug')->nullable()->index();
            }

            if (!Schema::hasColumn('b2b_game_catalog', 'platform')) {
                $table->string('platform', 30)->nullable()->index();
            }

            if (!Schema::hasColumn('b2b_game_catalog', 'launch_config')) {
                $table->json('launch_config')->nullable();
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('b2b_game_catalog')) {
            return;
        }

        Schema::table('b2b_game_catalog', function (Blueprint $table) {
            foreach (['provider_game_id', 'canonical_game_id', 'slug', 'platform', 'launch_config'] as $column) {
                if (Schema::hasColumn('b2b_game_catalog', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}
