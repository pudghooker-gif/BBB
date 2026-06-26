<?php

use Illuminate\Support\Facades\Artisan;
use VanguardLTE\B2B\Jobs\CloseStaleB2BSessionsJob;
use VanguardLTE\B2B\Jobs\RecoverWalletRollbacksJob;
use VanguardLTE\B2B\Jobs\ReconcileWalletTransactionsJob;
use VanguardLTE\B2B\Jobs\RetryWalletTransactionsJob;
use VanguardLTE\B2B\Services\B2BPrivilegedActionGuard;
use VanguardLTE\B2B\Services\B2BStaleSessionCloser;
use VanguardLTE\B2B\Services\WalletManualActionService;
use VanguardLTE\B2B\Services\WalletReconciliationService;
use VanguardLTE\B2B\Services\WalletRollbackRecoveryService;
use VanguardLTE\B2B\Services\WalletTransactionService;

Artisan::command('b2b:retry-wallet {--limit=50} {--dispatch : Dispatch the retry job to the configured B2B queue}', function (WalletTransactionService $service) {
    $limit = (int) $this->option('limit');

    if ((bool) $this->option('dispatch')) {
        dispatch(new RetryWalletTransactionsJob($limit));
        $this->info('B2B wallet retry job dispatched to '.config('b2b_queues.queues.wallet_retry').'.');
        return 0;
    }

    $result = $service->retryPending($limit);
    $this->info('B2B wallet retry processed: '.(isset($result['processed']) ? $result['processed'] : 0));

    return 0;
})->describe('Retry failed or timed out B2B wallet callbacks.');

Artisan::command('b2b:recover-rollbacks {--limit=50} {--dispatch : Dispatch the rollback recovery job to the configured B2B queue}', function (WalletRollbackRecoveryService $service) {
    $limit = (int) $this->option('limit');

    if ((bool) $this->option('dispatch')) {
        dispatch(new RecoverWalletRollbacksJob($limit));
        $this->info('B2B wallet rollback recovery job dispatched to '.config('b2b_queues.queues.wallet_retry').'.');
        return 0;
    }

    $result = $service->recover($limit);

    if (isset($result['message'])) {
        $this->error($result['message']);
        return 1;
    }

    $this->info('B2B wallet rollback recovery processed: '.$result['processed']);
    $this->info('Reversed: '.$result['reversed'].'; failed: '.$result['failed'].'; manual_review: '.$result['manual_review'].'; skipped: '.$result['skipped']);

    return 0;
})->describe('Recover B2B rollback_required wallet transactions by calling operator rollback.');

Artisan::command('b2b:reconcile-wallet {--limit=100} {--pending-minutes=5} {--dispatch : Dispatch the reconciliation job to the configured B2B queue}', function (WalletReconciliationService $service) {
    $limit = (int) $this->option('limit');
    $pendingMinutes = (int) $this->option('pending-minutes');

    if ((bool) $this->option('dispatch')) {
        dispatch(new ReconcileWalletTransactionsJob($limit, $pendingMinutes));
        $this->info('B2B wallet reconciliation job dispatched to '.config('b2b_queues.queues.reconciliation').'.');
        return 0;
    }

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

Artisan::command('b2b:close-stale-sessions {--minutes=30} {--dispatch : Dispatch the stale-session cleanup job to the configured B2B queue}', function (B2BStaleSessionCloser $closer) {
    $minutes = (int) $this->option('minutes');

    if ((bool) $this->option('dispatch')) {
        dispatch(new CloseStaleB2BSessionsJob($minutes));
        $this->info('B2B stale session cleanup job dispatched to '.config('b2b_queues.queues.maintenance').'.');
        return 0;
    }

    $result = $closer->close($minutes);
    if (isset($result['message'])) {
        $this->error($result['message']);
        return 1;
    }

    $this->info('B2B stale sessions closed: '.$result['closed']);
    return 0;
})->describe('Mark stale B2B sessions as stale so one hanging player does not block flows.');
