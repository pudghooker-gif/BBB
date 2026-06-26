<?php

namespace VanguardLTE\B2B\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use VanguardLTE\B2B\Services\B2BStaleSessionCloser;

class CloseStaleB2BSessionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;
    public $timeout = 180;

    private $minutes;

    public function __construct($minutes = 30)
    {
        $this->minutes = max(1, (int) $minutes);
        $this->onConnection(config('b2b_queues.connection'));
        $this->onQueue(config('b2b_queues.queues.maintenance'));
    }

    public function handle(B2BStaleSessionCloser $closer)
    {
        return $closer->close($this->minutes);
    }

    public function minutes()
    {
        return $this->minutes;
    }
}
