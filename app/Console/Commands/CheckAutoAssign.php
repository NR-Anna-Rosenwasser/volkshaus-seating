<?php

namespace App\Console\Commands;

use App\Services\AdminAlert;
use App\Services\AutoAssign;
use App\Support\SelectionWindow;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

#[Signature('seating:check-auto-assign')]
#[Description('Alert the admins if auto-assign has not been run after the deadline')]
class CheckAutoAssign extends Command
{
    private const HOURS_AFTER_DEADLINE = 3;

    private const REPEAT_SECONDS = 86400;

    private const ALERT_KEY = 'seating.auto-assign-overdue-alert';

    public function handle(AutoAssign $autoAssign, SelectionWindow $window, AdminAlert $alert): int
    {
        $eventDate = config('seating.event_date');
        $eventIsOver = $eventDate && Carbon::parse($eventDate, config('app.timezone'))->isPast();

        if ($window->deadline()?->addHours(self::HOURS_AFTER_DEADLINE)->isFuture() !== false
            || $eventIsOver
            || Cache::has(AutoAssign::LAST_RUN_KEY)) {
            return self::SUCCESS;
        }

        $waiting = $autoAssign->waitingOrders();

        if ($waiting->isNotEmpty() && Cache::add(self::ALERT_KEY, true, self::REPEAT_SECONDS)) {
            $alert->send(
                'Auto-Zuteilung noch nicht ausgeführt',
                'Die Frist für die Platzwahl ist seit über '.self::HOURS_AFTER_DEADLINE." Stunden vorbei, und {$waiting->count()} Bestellungen warten noch auf Plätze. Bitte seating:auto-assign ausführen.",
            );
        }

        return self::SUCCESS;
    }
}
