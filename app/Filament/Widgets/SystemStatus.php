<?php

namespace App\Filament\Widgets;

use App\Console\Commands\BackupDatabase;
use App\Console\Commands\Reconcile;
use App\Services\AutoAssign;
use App\Support\StatusLog;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class SystemStatus extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected ?string $pollingInterval = '30s';

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $reconcile = StatusLog::get(Reconcile::LAST_RESULT_KEY);
        $failed = $reconcile['failed'] ?? false;

        $skipped = $reconcile['failed_count'] ?? 0;

        return [
            Stat::make('Letzter Abgleich', $this->ago($reconcile['succeeded_at'] ?? null))
                ->description(match (true) {
                    $failed => 'Letzter Versuch fehlgeschlagen',
                    $skipped > 0 => "{$skipped} Bestellungen nicht übernommen",
                    $reconcile !== null => 'Läuft alle 2 Minuten',
                    default => null,
                })
                ->color($failed || $skipped > 0 ? 'danger' : 'gray'),
            Stat::make('Letzte Auto-Zuteilung', $this->ago(StatusLog::get(AutoAssign::LAST_RUN_KEY))),
            Stat::make('Letztes Backup', $this->ago(StatusLog::get(BackupDatabase::LAST_BACKUP_KEY))),
        ];
    }

    private function ago(?string $date): string
    {
        return $date ? Carbon::parse($date)->locale('de')->diffForHumans() : 'Noch nie';
    }
}
