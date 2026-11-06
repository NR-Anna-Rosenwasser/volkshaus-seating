<?php

namespace App\Console\Commands;

use App\Console\Concerns\RunsPerEvent;
use App\Models\Event;
use App\Services\AdminAlert;
use App\Services\AutoAssign;
use App\Support\StatusLog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

#[Signature('seating:check-auto-assign {--event= : Only this event (slug)}')]
#[Description('Alert the admins if auto-assign has not been run after the deadline')]
class CheckAutoAssign extends Command
{
    use RunsPerEvent;

    private const HOURS_AFTER_DEADLINE = 3;

    private const REPEAT_SECONDS = 86400;

    public function handle(AutoAssign $autoAssign, AdminAlert $alert): int
    {
        return $this->forEachEvent(function (Event $event) use ($autoAssign, $alert): int {
            if ($event->window()->deadline()?->addHours(self::HOURS_AFTER_DEADLINE)->isFuture() !== false
                || $event->starts_at?->isPast()
                || StatusLog::get(AutoAssign::lastRunKey($event)) !== null) {
                return self::SUCCESS;
            }

            $waiting = $autoAssign->waitingOrders($event);

            if ($waiting->isNotEmpty() && Cache::add("seating.auto-assign-overdue-alert.{$event->id}", true, self::REPEAT_SECONDS)) {
                $alert->send(
                    'Auto-Zuteilung noch nicht ausgeführt',
                    'Die Frist für die Platzwahl ist seit über '.self::HOURS_AFTER_DEADLINE." Stunden vorbei, und {$waiting->count()} Bestellungen warten noch auf Plätze. Bitte die Auto-Zuteilung in der Übersicht ausführen.",
                    $event,
                );
            }

            return self::SUCCESS;
        });
    }
}
