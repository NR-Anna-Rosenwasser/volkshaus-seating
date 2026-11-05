<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Operator alerts. An alert must never break the work it reports on, and
 * must never contain personal data.
 */
class Ntfy
{
    public function alert(string $title, string $message): void
    {
        $url = config('seating.ntfy.url');
        $topic = config('seating.ntfy.topic');

        if (! $url || ! $topic) {
            Log::warning("ntfy is not configured. {$title}: {$message}");

            return;
        }

        try {
            Http::timeout(5)
                ->withHeaders(['Title' => $title])
                ->withBody($message, 'text/plain')
                ->post(rtrim($url, '/').'/'.$topic)
                ->throw();
        } catch (Throwable $exception) {
            Log::error("ntfy alert failed: {$exception->getMessage()}");
        }
    }
}
