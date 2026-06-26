<?php

namespace VanguardLTE\B2B\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use VanguardLTE\B2B\Services\WalletTransactionService;

class RetryWalletTransactionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $timeout = 30;

    private $limit;

    public function __construct($limit = 50)
    {
        $this->limit = max(1, (int) $limit);
        $this->onConnection(config('b2b_queues.connection'));
        $this->onQueue(config('b2b_queues.queues.wallet_retry'));
    }

    public function handle(WalletTransactionService $service)
    {
        return $service->retryPending($this->limit);
    }

    public function limit()
    {
        return $this->limit;
    }
}
