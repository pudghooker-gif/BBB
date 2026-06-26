<?php

namespace VanguardLTE\B2B\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use VanguardLTE\B2B\Services\WalletReconciliationService;

class ReconcileWalletTransactionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;
    public $timeout = 120;

    private $limit;
    private $pendingMinutes;

    public function __construct($limit = 100, $pendingMinutes = null)
    {
        $this->limit = max(1, (int) $limit);
        $this->pendingMinutes = $pendingMinutes === null
            ? (int) config('b2b.wallet_reconciliation_pending_minutes', 5)
            : max(1, (int) $pendingMinutes);
        $this->onConnection(config('b2b_queues.connection'));
        $this->onQueue(config('b2b_queues.queues.reconciliation'));
    }

    public function handle(WalletReconciliationService $service)
    {
        return $service->scan($this->limit, $this->pendingMinutes);
    }

    public function limit()
    {
        return $this->limit;
    }

    public function pendingMinutes()
    {
        return $this->pendingMinutes;
    }
}
