<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRateLimitFieldsToB2BOperatorApiKeysTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('b2b_operator_api_keys')) {
            return;
        }

        Schema::table('b2b_operator_api_keys', function (Blueprint $table) {
            if (!Schema::hasColumn('b2b_operator_api_keys', 'max_rps')) {
                $table->integer('max_rps')->nullable()->after('status');
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('b2b_operator_api_keys')) {
            return;
        }

        Schema::table('b2b_operator_api_keys', function (Blueprint $table) {
            if (Schema::hasColumn('b2b_operator_api_keys', 'max_rps')) {
                $table->dropColumn('max_rps');
            }
        });
    }
}
