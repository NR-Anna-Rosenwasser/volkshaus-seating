<?php

namespace App\Console\Commands;

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
#[Description('Anonymize orders and attendees once the retention period is over')]
class PurgePersonalData extends Command
{
    private const WARNING_DAYS = 7;

    private const WARNING_KEY = 'seating.purge-warning';

    public function handle(PersonalDataPurge $purge, DataRetention $retention, AdminAlert $alert): int
    {
        $date = $retention->purgeDate();
        $counts = $purge->counts();
        $summary = "{$counts['orders']} orders, {$counts['attendees']} attendees, {$counts['webhooks']} webhook events";

        if ($this->option('dry-run')) {
            $this->info($date ? 'Due on '.SelectionWindow::format($date).'.' : 'EVENT_DATE is not set, so nothing is due.');
            $this->line("Would remove: {$summary}.");

            return self::SUCCESS;
        }

        if (! $retention->isOver()) {
            // This can't be undone, so a wrong EVENT_DATE has to be noticed in time.
            if ($date?->copy()->subDays(self::WARNING_DAYS)->isPast() && $counts['orders'] > 0 && Cache::add(self::WARNING_KEY, true, 8 * 86400)) {
                $alert->send('Personendaten werden bald gelöscht', 'Am '.SelectionWindow::format($date).' werden alle Namen und Adressen anonymisiert. Falls EVENT_DATE falsch ist, jetzt korrigieren.');
            }

            $this->info('Not due yet.');

            return self::SUCCESS;
        }

        if ($counts['orders'] + $counts['attendees'] + $counts['webhooks'] === 0) {
            $this->info('Nothing to remove.');

            return self::SUCCESS;
        }

        try {
            $purge->run();
        } catch (Throwable $exception) {
            // Nothing was removed, and tomorrow's run tries again. The promise is on the line, so say so.
            report($exception);
            $alert->send('Anonymisierung fehlgeschlagen', 'seating:purge konnte nicht abgeschlossen werden. Es wurde nichts gelöscht. Der nächste Versuch läuft in der kommenden Nacht.');
            $this->error('Purge failed, nothing was removed.');

            return self::FAILURE;
        }

        $this->info("Removed: {$summary}.");
        $alert->send('Personendaten anonymisiert', "{$counts['orders']} Bestellungen und {$counts['attendees']} Tickets wurden anonymisiert. Backups mit diesen Daten laufen innert ".config('seating.backup_keep_days').' Tagen ab. Die Daten in Hi.Events bleiben davon unberührt.');

        return self::SUCCESS;
    }
}
