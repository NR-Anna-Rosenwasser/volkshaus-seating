<?php

namespace App\Jobs;

use App\Http\Controllers\HealthController;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class RecordQueueHeartbeat implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Cache::forever(HealthController::QUEUE_KEY, now()->getTimestamp());
    }
}
