<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * For Uptime Kuma. Public, so it only ever says ok or failed.
 */
class HealthController extends Controller
{
    public const SCHEDULER_KEY = 'seating.scheduler-heartbeat';

    public const QUEUE_KEY = 'seating.queue-heartbeat';

    /** The scheduler beats every minute. */
    private const SCHEDULER_MAX_AGE = 180;

    /** Queued mails can hold the heartbeat up for a while without anything being wrong. */
    private const QUEUE_MAX_AGE = 600;

    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->databaseIsUp(),
            'queue' => $this->isRecent(self::QUEUE_KEY, self::QUEUE_MAX_AGE),
            'scheduler' => $this->isRecent(self::SCHEDULER_KEY, self::SCHEDULER_MAX_AGE),
        ];

        $healthy = ! in_array(false, $checks, true);

        return response()->json(
            ['status' => $healthy ? 'ok' : 'failed', 'checks' => array_map(fn (bool $ok): string => $ok ? 'ok' : 'failed', $checks)],
            $healthy ? 200 : 503,
        );
    }

    private function databaseIsUp(): bool
    {
        try {
            DB::select('select 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * The cache lives in the database, so this must survive that being down.
     */
    private function isRecent(string $key, int $maxAge): bool
    {
        try {
            $beat = Cache::get($key);
        } catch (Throwable) {
            return false;
        }

        return $beat !== null && now()->getTimestamp() - $beat <= $maxAge;
    }
}
