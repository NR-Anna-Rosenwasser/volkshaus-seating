<?php

namespace App\Console\Commands;

use App\Http\Controllers\HealthController;
use App\Jobs\RecordQueueHeartbeat;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

#[Signature('seating:heartbeat')]
#[Description('Prove to /health that the scheduler and the queue are alive')]
class RecordHeartbeat extends Command
{
    public function handle(): int
    {
        Cache::forever(HealthController::SCHEDULER_KEY, now()->getTimestamp());
        RecordQueueHeartbeat::dispatch();

        return self::SUCCESS;
    }
}
