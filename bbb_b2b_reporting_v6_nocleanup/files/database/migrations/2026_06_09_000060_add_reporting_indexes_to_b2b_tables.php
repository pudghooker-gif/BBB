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
                $this->safeIndex($table, ['operator_id', 'created_at'], 'b2b_wt_operator_created_idx');
                $this->safeIndex($table, ['operator_id', 'type', 'status'], 'b2b_wt_operator_type_status_idx');
                $this->safeIndex($table, ['operator_id', 'game_id'], 'b2b_wt_operator_game_idx');
                $this->safeIndex($table, ['operator_id', 'round_id'], 'b2b_wt_operator_round_idx');
            });
        }

        if (Schema::hasTable('b2b_game_sessions')) {
            Schema::table('b2b_game_sessions', function (Blueprint $table) {
                $this->safeIndex($table, ['operator_id', 'created_at'], 'b2b_gs_operator_created_idx');
                $this->safeIndex($table, ['operator_id', 'status'], 'b2b_gs_operator_status_idx');
                $this->safeIndex($table, ['operator_id', 'game_id'], 'b2b_gs_operator_game_idx');
            });
        }

        if (Schema::hasTable('b2b_wallet_callback_logs')) {
            Schema::table('b2b_wallet_callback_logs', function (Blueprint $table) {
                $this->safeIndex($table, ['operator_id', 'created_at'], 'b2b_wcl_operator_created_idx');
                $this->safeIndex($table, ['wallet_transaction_id'], 'b2b_wcl_transaction_idx');
            });
        }
    }

    public function down()
    {
        // Intentionally no-op for old MySQL compatibility. These indexes are safe to leave.
    }

    private function safeIndex(Blueprint $table, array $columns, $name)
    {
        try {
            $table->index($columns, $name);
        } catch (\Exception $e) {
            // Ignore duplicate index errors on repeated patch application.
        }
    }
}
