<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddProductionIndexesToB2BTables extends Migration
{
    public function up()
    {
        if (Schema::hasTable('b2b_wallet_transactions') && !Schema::hasColumn('b2b_wallet_transactions', 'transaction_id')) {
            Schema::table('b2b_wallet_transactions', function (Blueprint $table) {
                $table->string('transaction_id', 191)->nullable()->after('transaction_uid');
            });
        }

        if (Schema::hasTable('b2b_wallet_transactions')) {
            Schema::table('b2b_wallet_transactions', function (Blueprint $table) {
                $this->safeIndex('b2b_wallet_transactions', $table, ['operator_id', 'transaction_uid'], 'b2b_wt_operator_tx_uid_idx');
                $this->safeIndex('b2b_wallet_transactions', $table, ['operator_id', 'transaction_id'], 'b2b_wt_operator_tx_id_idx');
                $this->safeIndex('b2b_wallet_transactions', $table, ['operator_id', 'session_id', 'created_at'], 'b2b_wt_operator_session_created_idx');
                $this->safeIndex('b2b_wallet_transactions', $table, ['operator_id', 'status', 'created_at'], 'b2b_wt_operator_status_created_idx');
                $this->safeIndex('b2b_wallet_transactions', $table, ['status', 'created_at'], 'b2b_wt_status_created_idx');
                $this->safeIndex('b2b_wallet_transactions', $table, ['operator_id', 'currency', 'created_at'], 'b2b_wt_operator_currency_created_idx');
            });
        }

        if (Schema::hasTable('b2b_game_sessions')) {
            Schema::table('b2b_game_sessions', function (Blueprint $table) {
                $this->safeIndex('b2b_game_sessions', $table, ['operator_id', 'session_uid'], 'b2b_gs_operator_session_uid_idx');
                $this->safeIndex('b2b_game_sessions', $table, ['operator_id', 'token_hash'], 'b2b_gs_operator_token_hash_idx');
                $this->safeIndex('b2b_game_sessions', $table, ['operator_id', 'status', 'created_at'], 'b2b_gs_operator_status_created_idx');
                $this->safeIndex('b2b_game_sessions', $table, ['status', 'expires_at'], 'b2b_gs_status_expires_idx');
                $this->safeIndex('b2b_game_sessions', $table, ['status', 'last_seen_at'], 'b2b_gs_status_last_seen_idx');
            });
        }

        if (Schema::hasTable('b2b_wallet_callback_logs')) {
            Schema::table('b2b_wallet_callback_logs', function (Blueprint $table) {
                $this->safeIndex('b2b_wallet_callback_logs', $table, ['operator_id', 'http_status', 'created_at'], 'b2b_wcl_operator_http_created_idx');
                $this->safeIndex('b2b_wallet_callback_logs', $table, ['operator_id', 'direction', 'created_at'], 'b2b_wcl_operator_direction_created_idx');
                $this->safeIndex('b2b_wallet_callback_logs', $table, ['wallet_transaction_id', 'created_at'], 'b2b_wcl_transaction_created_idx');
            });
        }

        if (Schema::hasTable('b2b_settlements')) {
            Schema::table('b2b_settlements', function (Blueprint $table) {
                $this->safeIndex('b2b_settlements', $table, ['operator_id', 'period_start', 'period_end'], 'b2b_set_operator_period_idx');
                $this->safeIndex('b2b_settlements', $table, ['operator_id', 'status', 'created_at'], 'b2b_set_operator_status_created_idx');
                $this->safeIndex('b2b_settlements', $table, ['operator_id', 'currency', 'created_at'], 'b2b_set_operator_currency_created_idx');
            });
        }

        if (Schema::hasTable('b2b_operator_api_keys')) {
            Schema::table('b2b_operator_api_keys', function (Blueprint $table) {
                $this->safeIndex('b2b_operator_api_keys', $table, ['operator_id', 'status'], 'b2b_oak_operator_status_idx');
                $this->safeIndex('b2b_operator_api_keys', $table, ['operator_id', 'created_at'], 'b2b_oak_operator_created_idx');
            });
        }

        if (Schema::hasTable('b2b_operator_audit_events')) {
            Schema::table('b2b_operator_audit_events', function (Blueprint $table) {
                $this->safeIndex('b2b_operator_audit_events', $table, ['operator_id', 'created_at'], 'b2b_oae_operator_created_idx');
                $this->safeIndex('b2b_operator_audit_events', $table, ['operator_id', 'event_type', 'created_at'], 'b2b_oae_operator_event_created_idx');
            });
        }

        if (Schema::hasTable('b2b_provider_requests')) {
            Schema::table('b2b_provider_requests', function (Blueprint $table) {
                $this->safeIndex('b2b_provider_requests', $table, ['operator_id', 'status', 'created_at'], 'b2b_pr_operator_status_created_idx');
                $this->safeIndex('b2b_provider_requests', $table, ['provider', 'action', 'status', 'created_at'], 'b2b_pr_provider_action_status_created_idx');
                $this->safeIndex('b2b_provider_requests', $table, ['session_id', 'created_at'], 'b2b_pr_session_created_idx');
            });
        }
    }

    public function down()
    {
        foreach ([
            'b2b_wallet_transactions' => [
                'b2b_wt_operator_tx_uid_idx',
                'b2b_wt_operator_tx_id_idx',
                'b2b_wt_operator_session_created_idx',
                'b2b_wt_operator_status_created_idx',
                'b2b_wt_status_created_idx',
                'b2b_wt_operator_currency_created_idx',
            ],
            'b2b_game_sessions' => [
                'b2b_gs_operator_session_uid_idx',
                'b2b_gs_operator_token_hash_idx',
                'b2b_gs_operator_status_created_idx',
                'b2b_gs_status_expires_idx',
                'b2b_gs_status_last_seen_idx',
            ],
            'b2b_wallet_callback_logs' => [
                'b2b_wcl_operator_http_created_idx',
                'b2b_wcl_operator_direction_created_idx',
                'b2b_wcl_transaction_created_idx',
            ],
            'b2b_settlements' => [
                'b2b_set_operator_period_idx',
                'b2b_set_operator_status_created_idx',
                'b2b_set_operator_currency_created_idx',
            ],
            'b2b_operator_api_keys' => [
                'b2b_oak_operator_status_idx',
                'b2b_oak_operator_created_idx',
            ],
            'b2b_operator_audit_events' => [
                'b2b_oae_operator_created_idx',
                'b2b_oae_operator_event_created_idx',
            ],
            'b2b_provider_requests' => [
                'b2b_pr_operator_status_created_idx',
                'b2b_pr_provider_action_status_created_idx',
                'b2b_pr_session_created_idx',
            ],
        ] as $tableName => $indexes) {
            foreach ($indexes as $index) {
                $this->safeDropIndex($tableName, $index);
            }
        }
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
            // Existing deployments may already have equivalent hand-created indexes.
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
            // Keep rollback safe for partially upgraded installs.
        }
    }
}
