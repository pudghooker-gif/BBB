<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddResilienceFieldsToB2BWalletTransactionsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('b2b_wallet_transactions')) {
            return;
        }

        Schema::table('b2b_wallet_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('b2b_wallet_transactions', 'attempts')) {
                $table->unsignedSmallInteger('attempts')->default(0)->after('error_message');
            }
            if (!Schema::hasColumn('b2b_wallet_transactions', 'last_attempt_at')) {
                $table->timestamp('last_attempt_at')->nullable()->after('attempts');
            }
            if (!Schema::hasColumn('b2b_wallet_transactions', 'locked_until')) {
                $table->timestamp('locked_until')->nullable()->index()->after('last_attempt_at');
            }
            if (!Schema::hasColumn('b2b_wallet_transactions', 'processed_at')) {
                $table->timestamp('processed_at')->nullable()->index()->after('locked_until');
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('b2b_wallet_transactions')) {
            return;
        }

        Schema::table('b2b_wallet_transactions', function (Blueprint $table) {
            foreach (['attempts', 'last_attempt_at', 'locked_until', 'processed_at'] as $column) {
                if (Schema::hasColumn('b2b_wallet_transactions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}
