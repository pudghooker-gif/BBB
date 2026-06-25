<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use VanguardLTE\B2B\Services\B2BPrivilegedActionGuard;
use VanguardLTE\B2B\Services\WalletManualActionService;
use VanguardLTE\B2B\Services\WalletReconciliationService;
use VanguardLTE\B2B\Services\WalletRollbackRecoveryService;
use VanguardLTE\B2B\Services\WalletTransactionService;

Artisan::command('b2b:retry-wallet {--limit=50}', function (WalletTransactionService $service) {
    $limit = (int) $this->option('limit');
    $result = $service->retryPending($limit);
    $this->info('B2B wallet retry processed: '.(isset($result['processed']) ? $result['processed'] : 0));
})->describe('Retry failed or timed out B2B wallet callbacks.');

Artisan::command('b2b:recover-rollbacks {--limit=50}', function (WalletRollbackRecoveryService $service) {
    $limit = (int) $this->option('limit');
    $result = $service->recover($limit);

    if (isset($result['message'])) {
        $this->error($result['message']);
        return 1;
    }

    $this->info('B2B wallet rollback recovery processed: '.$result['processed']);
    $this->info('Reversed: '.$result['reversed'].'; failed: '.$result['failed'].'; manual_review: '.$result['manual_review'].'; skipped: '.$result['skipped']);

    return 0;
})->describe('Recover B2B rollback_required wallet transactions by calling operator rollback.');

Artisan::command('b2b:reconcile-wallet {--limit=100} {--pending-minutes=5}', function (WalletReconciliationService $service) {
    $limit = (int) $this->option('limit');
    $pendingMinutes = (int) $this->option('pending-minutes');
    $result = $service->scan($limit, $pendingMinutes);

    if (isset($result['message'])) {
        $this->error($result['message']);
        return 1;
    }

    $this->info('B2B wallet reconciliation processed: '.$result['processed']);
    $this->info('Opened: '.$result['opened'].'; updated: '.$result['updated'].'; transitioned_unknown: '.$result['transitioned_unknown']);

    return 0;
})->describe('Scan B2B wallet transactions that need reconciliation or manual review.');

Artisan::command('b2b:wallet-manual-action {transaction_uid} {action} {--operator-id=} {--actor=} {--reason=} {--permission=} {--confirm=}', function (WalletManualActionService $service, B2BPrivilegedActionGuard $guard) {
    $actor = (string) $this->option('actor');
    $reason = (string) $this->option('reason');

    $privilege = $guard->authorize($this->option('operator-id'), 'wallet.manual_action', $actor, $reason, $this->option('permission'), $this->option('confirm'));
    if (!$privilege['ok']) {
        $this->error($privilege['message']);
        return 1;
    }

    try {
        $result = $service->apply(
            $this->argument('transaction_uid'),
            $this->argument('action'),
            $reason,
            $actor,
            $this->option('operator-id')
        );
    } catch (\Exception $e) {
        $this->error($e->getMessage());
        return 1;
    }

    $this->info('B2B wallet manual action applied.');
    $this->line('transaction_uid: '.$result['transaction_uid']);
    $this->line('from_status: '.$result['from_status']);
    $this->line('to_status: '.$result['to_status']);
    $this->line('manual_action_id: '.($result['manual_action_id'] ?: 'not_recorded'));

    return 0;
})->describe('Apply an audited manual state transition to one B2B wallet transaction.');

Artisan::command('b2b:close-stale-sessions {--minutes=30}', function () {
    if (!Schema::hasTable('b2b_game_sessions')) {
        $this->error('b2b_game_sessions table missing.');
        return 1;
    }

    $minutes = (int) $this->option('minutes');
    if ($minutes < 1) {
        $minutes = 30;
    }

    $cutoff = Carbon::now()->subMinutes($minutes);
    $updates = [
        'status' => 'stale',
        'stale_at' => Carbon::now(),
        'close_reason' => 'heartbeat_timeout',
        'updated_at' => Carbon::now(),
    ];

    foreach (['stale_at', 'close_reason'] as $column) {
        if (!Schema::hasColumn('b2b_game_sessions', $column)) {
            unset($updates[$column]);
        }
    }

    $count = DB::table('b2b_game_sessions')
        ->whereIn('status', ['active', 'launched'])
        ->where(function ($query) use ($cutoff) {
            $query->where(function ($q) use ($cutoff) {
                if (Schema::hasColumn('b2b_game_sessions', 'last_seen_at')) {
                    $q->whereNotNull('last_seen_at')->where('last_seen_at', '<', $cutoff);
                }
            });
            if (Schema::hasColumn('b2b_game_sessions', 'heartbeat_at')) {
                $query->orWhere(function ($q) use ($cutoff) {
                    $q->whereNotNull('heartbeat_at')->where('heartbeat_at', '<', $cutoff);
                });
            }
            $query->orWhere(function ($q) use ($cutoff) {
                $q->whereNull('last_seen_at')->where('created_at', '<', $cutoff);
            });
        })
        ->update($updates);

    $this->info('B2B stale sessions closed: '.$count);
})->describe('Mark stale B2B sessions as stale so one hanging player does not block flows.');
