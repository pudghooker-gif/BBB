<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReportingIndexesToB2BTables extends Migration
{
    public function up()
    {
        if (Schema::hasTable('b2b_wallet_transactions')) {
            Schema::table('b2b_wallet_transactions', function (Blueprint $table) {
                $this->safeIndex('b2b_wallet_transactions', $table, ['operator_id', 'created_at'], 'b2b_wt_operator_created_idx');
                $this->safeIndex('b2b_wallet_transactions', $table, ['operator_id', 'type', 'status'], 'b2b_wt_operator_type_status_idx');
                $this->safeIndex('b2b_wallet_transactions', $table, ['operator_id', 'game_uid'], 'b2b_wt_operator_game_idx');
                $this->safeIndex('b2b_wallet_transactions', $table, ['operator_id', 'round_id'], 'b2b_wt_operator_round_idx');
            });
        }

        if (Schema::hasTable('b2b_game_sessions')) {
            Schema::table('b2b_game_sessions', function (Blueprint $table) {
                $this->safeIndex('b2b_game_sessions', $table, ['operator_id', 'created_at'], 'b2b_gs_operator_created_idx');
                $this->safeIndex('b2b_game_sessions', $table, ['operator_id', 'status'], 'b2b_gs_operator_status_idx');
                $this->safeIndex('b2b_game_sessions', $table, ['operator_id', 'game_uid'], 'b2b_gs_operator_game_idx');
            });
        }

        if (Schema::hasTable('b2b_wallet_callback_logs')) {
            Schema::table('b2b_wallet_callback_logs', function (Blueprint $table) {
                $this->safeIndex('b2b_wallet_callback_logs', $table, ['operator_id', 'created_at'], 'b2b_wcl_operator_created_idx');
                $this->safeIndex('b2b_wallet_callback_logs', $table, ['wallet_transaction_id'], 'b2b_wcl_transaction_idx');
            });
        }
    }

    public function down()
    {
        $this->safeDropIndex('b2b_wallet_transactions', 'b2b_wt_operator_created_idx');
        $this->safeDropIndex('b2b_wallet_transactions', 'b2b_wt_operator_type_status_idx');
        $this->safeDropIndex('b2b_wallet_transactions', 'b2b_wt_operator_game_idx');
        $this->safeDropIndex('b2b_wallet_transactions', 'b2b_wt_operator_round_idx');
        $this->safeDropIndex('b2b_game_sessions', 'b2b_gs_operator_created_idx');
        $this->safeDropIndex('b2b_game_sessions', 'b2b_gs_operator_status_idx');
        $this->safeDropIndex('b2b_game_sessions', 'b2b_gs_operator_game_idx');
        $this->safeDropIndex('b2b_wallet_callback_logs', 'b2b_wcl_operator_created_idx');
        $this->safeDropIndex('b2b_wallet_callback_logs', 'b2b_wcl_transaction_idx');
    }

    private function safeIndex($tableName, Blueprint $table, array $columns, $name)
    {
        foreach ($columns as $column) {
            if (!Schema::hasColumn($tableName, $column)) {
                return;
            }
        }

        try {
            $table->index($columns, $name);
        } catch (\Exception $e) {
            // Ignore duplicate index errors on repeated patch application.
        }
    }

    private function safeDropIndex($tableName, $name)
    {
        if (!Schema::hasTable($tableName)) {
            return;
        }

        try {
            Schema::table($tableName, function (Blueprint $table) use ($name) {
                $table->dropIndex($name);
            });
        } catch (\Exception $e) {
            // Old installs may not have all indexes; rollback should remain safe.
        }
    }
}
