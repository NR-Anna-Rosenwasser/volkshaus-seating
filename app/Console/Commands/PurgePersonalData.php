<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Services\AdminAlert;
use App\Services\PersonalDataPurge;
use App\Support\DataRetention;
use App\Support\SelectionWindow;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

#[Signature('seating:purge {--dry-run : Show what would be removed without changing anything}')]
#[Description('Anonymize orders and attendees of events whose retention period is over')]
class PurgePersonalData extends Command
{
    private const WARNING_DAYS = 7;

    public function handle(PersonalDataPurge $purge, AdminAlert $alert): int
    {
        if (! $this->option('dry-run')) {
            $purge->housekeeping();
        }

        $status = self::SUCCESS;

        // Not Event::running(): the events that are due are the ones it drops.
        foreach (Event::active()->orderBy('id')->get() as $event) {
            $status = max($status, $this->purge($event, $purge, $alert));
        }

        return $status;
    }

    private function purge(Event $event, PersonalDataPurge $purge, AdminAlert $alert): int
    {
        $retention = DataRetention::for($event);
        $date = $retention->purgeDate();
        $counts = $purge->counts($event);
        $summary = "{$counts['orders']} orders, {$counts['attendees']} attendees, {$counts['webhooks']} webhook events";

        if ($this->option('dry-run')) {
            $this->info($event->slug.': '.($date ? 'Due on '.SelectionWindow::format($date).'.' : 'No event date set, so nothing is due.'));
            $this->line("Would remove: {$summary}.");

            return self::SUCCESS;
        }

        if (! $retention->isOver()) {
            // This can't be undone, so a wrong event date has to be noticed in time.
            if ($date?->copy()->subDays(self::WARNING_DAYS)->isPast() && $counts['orders'] > 0 && Cache::add("seating.purge-warning.{$event->id}", true, 8 * 86400)) {
                $alert->send('Personendaten werden bald gelöscht', 'Am '.SelectionWindow::format($date).' werden alle Namen und Adressen anonymisiert. Falls das Datum des Events falsch ist, jetzt korrigieren.', $event);
            }

            $this->info("{$event->slug}: not due yet.");

            return self::SUCCESS;
        }

        try {
            $purge->run($event);
        } catch (Throwable $exception) {
            // Nothing was removed, and tomorrow's run tries again. The promise is on the line, so say so.
            report($exception);
            $alert->send('Anonymisierung fehlgeschlagen', 'seating:purge konnte nicht abgeschlossen werden. Es wurde nichts gelöscht. Der nächste Versuch läuft in der kommenden Nacht.', $event);
            $this->error("{$event->slug}: purge failed, nothing was removed.");

            return self::FAILURE;
        }

        $this->info("{$event->slug}: removed {$summary}.");
        $alert->send('Personendaten anonymisiert', "{$counts['orders']} Bestellungen und {$counts['attendees']} Tickets wurden anonymisiert. Backups mit diesen Daten laufen innert ".config('seating.backup_keep_days').' Tagen ab. Die Daten in Hi.Events bleiben davon unberührt.', $event);

        return self::SUCCESS;
    }
}
