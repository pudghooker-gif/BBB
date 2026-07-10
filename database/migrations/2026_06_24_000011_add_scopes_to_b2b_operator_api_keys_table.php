<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddScopesToB2BOperatorApiKeysTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('b2b_operator_api_keys') || Schema::hasColumn('b2b_operator_api_keys', 'scopes')) {
            return;
        }

        Schema::table('b2b_operator_api_keys', function (Blueprint $table) {
            if (Schema::hasColumn('b2b_operator_api_keys', 'max_rps')) {
                $table->json('scopes')->nullable()->after('max_rps');
                return;
            }

            $table->json('scopes')->nullable()->after('status');
        });
    }

    public function down()
    {
        if (!Schema::hasTable('b2b_operator_api_keys') || !Schema::hasColumn('b2b_operator_api_keys', 'scopes')) {
            return;
        }

        Schema::table('b2b_operator_api_keys', function (Blueprint $table) {
            $table->dropColumn('scopes');
        });
    }
}
