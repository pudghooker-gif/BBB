<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSettlementLifecycleFieldsToB2BSettlementsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('b2b_settlements')) {
            return;
        }

        Schema::table('b2b_settlements', function (Blueprint $table) {
            if (!Schema::hasColumn('b2b_settlements', 'settlement_uid')) {
                $table->string('settlement_uid', 80)->nullable()->unique()->after('id');
            }
            if (!Schema::hasColumn('b2b_settlements', 'export_format')) {
                $table->string('export_format', 20)->nullable()->after('status');
            }
            if (!Schema::hasColumn('b2b_settlements', 'export_hash')) {
                $table->string('export_hash', 64)->nullable()->index()->after('export_format');
            }
            if (!Schema::hasColumn('b2b_settlements', 'exported_at')) {
                $table->timestamp('exported_at')->nullable()->index()->after('export_hash');
            }
            if (!Schema::hasColumn('b2b_settlements', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->index()->after('exported_at');
            }
            if (!Schema::hasColumn('b2b_settlements', 'submitted_by')) {
                $table->string('submitted_by', 100)->nullable()->after('submitted_at');
            }
            if (!Schema::hasColumn('b2b_settlements', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->index()->after('submitted_by');
            }
            if (!Schema::hasColumn('b2b_settlements', 'approved_by')) {
                $table->string('approved_by', 100)->nullable()->after('approved_at');
            }
            if (!Schema::hasColumn('b2b_settlements', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->index()->after('approved_by');
            }
            if (!Schema::hasColumn('b2b_settlements', 'rejected_by')) {
                $table->string('rejected_by', 100)->nullable()->after('rejected_at');
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('b2b_settlements')) {
            return;
        }

        Schema::table('b2b_settlements', function (Blueprint $table) {
            foreach ([
                'settlement_uid',
                'export_format',
                'export_hash',
                'exported_at',
                'submitted_at',
                'submitted_by',
                'approved_at',
                'approved_by',
                'rejected_at',
                'rejected_by',
            ] as $column) {
                if (Schema::hasColumn('b2b_settlements', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}
