<?php

namespace App\Services;

use App\Mail\AdminAlertMail;
use App\Models\Event;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Emails the people running the events. An alert must never break the work it
 * reports on, and must never contain personal data. Alerts about one event
 * name it, because the same list hears about all of them.
 */
class AdminAlert
{
    private const MAIL_FAILURES_KEY = 'seating.mail-failures';

    public function send(string $title, string $message, ?Event $event = null): void
    {
        $title = $event ? "{$event->name}: {$title}" : $title;

        Log::warning("{$title}: {$message}");

        $recipients = config('seating.admin_emails');

        if ($recipients === []) {
            return;
        }

        try {
            // Sent directly: the queue may be the very thing that is broken.
            Mail::to($recipients)->send(new AdminAlertMail($title, $message));
        } catch (Throwable $exception) {
            Log::error("Admin alert failed: {$exception->getMessage()}");
        }
    }

    /**
     * Warns once per hour when mail keeps failing.
     */
    public function mailFailed(): void
    {
        Cache::add(self::MAIL_FAILURES_KEY, 0, 3600);

        if (Cache::increment(self::MAIL_FAILURES_KEY) === config('seating.mail_failure_threshold')) {
            $this->send(
                'E-Mails schlagen fehl',
                config('seating.mail_failure_threshold').' E-Mails konnten in der letzten Stunde nicht verschickt werden.',
            );
        }
    }
}
