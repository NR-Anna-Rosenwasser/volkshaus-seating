<?php

namespace App\Filament\Widgets;

use App\Console\Commands\Reconcile;
use App\Models\Event;
use App\Services\AutoAssign;
use App\Support\StatusLog;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class EventStatus extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '30s';

    /** The event page passes its record. */
    public ?Event $record = null;

    protected function getColumns(): int
    {
        return 2;
    }

    protected function getStats(): array
    {
        $reconcile = StatusLog::get(Reconcile::lastResultKey($this->record));
        $failed = $reconcile['failed'] ?? false;

        return [
            Stat::make('Letzter Abgleich', SystemStatus::ago($reconcile['at'] ?? null))
                ->description($failed ? 'Fehlgeschlagen' : ($reconcile ? 'Erfolgreich' : null))
                ->color($failed ? 'danger' : 'gray'),
            Stat::make('Letzte Auto-Zuteilung', SystemStatus::ago(StatusLog::get(AutoAssign::lastRunKey($this->record)))),
        ];
    }
}
