<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class B2BMigrationTest extends TestCase
{
    public function testB2BMigrationsApplyOnCleanSqliteDatabase()
    {
        $this->assertSame(0, Artisan::call('migrate:fresh', [
            '--database' => 'sqlite_memory',
            '--force' => true,
            '--no-interaction' => true,
        ]));

        foreach ([
            'b2b_operators',
            'b2b_operator_api_keys',
            'b2b_operator_audit_events',
            'b2b_operator_players',
            'b2b_game_catalog',
            'b2b_operator_game_assignments',
            'b2b_game_sessions',
            'b2b_wallet_transactions',
            'b2b_wallet_callback_logs',
            'b2b_provider_requests',
            'b2b_settlements',
            'b2b_operator_health_events',
            'b2b_wallet_transaction_attempts',
            'b2b_sandbox_wallets',
            'b2b_sandbox_wallet_entries',
            'b2b_wallet_transaction_transitions',
            'b2b_wallet_reconciliation_items',
            'b2b_wallet_manual_actions',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), $table . ' table is missing after migrate:fresh');
        }

        foreach ([
            'b2b_game_sessions' => ['shadow_user_id', 'legacy_launch_token', 'launched_at', 'heartbeat_at', 'stale_at', 'close_reason'],
            'b2b_operator_game_assignments' => ['operator_id', 'game_uid', 'provider', 'status', 'allowed_currencies', 'allowed_countries'],
            'b2b_wallet_transactions' => ['idempotency_key', 'request_hash', 'raw_response', 'operator_response_body'],
            'b2b_operators' => ['max_rps', 'wallet_timeout_ms', 'connect_timeout_ms', 'circuit_open_until'],
            'b2b_operator_api_keys' => ['max_rps'],
            'b2b_wallet_transaction_attempts' => ['operator_id', 'attempt_no', 'duration_ms', 'result'],
            'b2b_wallet_reconciliation_items' => ['reason', 'priority', 'state', 'detected_at'],
            'b2b_wallet_manual_actions' => ['action', 'actor', 'reason', 'context'],
            'b2b_operator_audit_events' => ['operator_id', 'event_type', 'actor', 'subject_type', 'subject_id', 'reason', 'metadata'],
            'b2b_settlements' => ['settlement_uid', 'export_hash', 'exported_at', 'submitted_at', 'submitted_by', 'approved_at', 'approved_by', 'rejected_at', 'rejected_by'],
        ] as $table => $columns) {
            foreach ($columns as $column) {
                $this->assertTrue(
                    Schema::hasColumn($table, $column),
                    $table . '.' . $column . ' column is missing after migrate:fresh'
                );
            }
        }

        $this->assertSame(0, Artisan::call('migrate', [
            '--database' => 'sqlite_memory',
            '--force' => true,
            '--no-interaction' => true,
        ]));
        $this->assertStringContainsString('Nothing to migrate', Artisan::output());
    }
}
