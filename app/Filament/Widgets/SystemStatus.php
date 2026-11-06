<?php

namespace App\Filament\Widgets;

use App\Console\Commands\BackupDatabase;
use App\Console\Commands\Reconcile;
use App\Filament\Concerns\UsesCurrentEvent;
use App\Models\WebhookEvent;
use App\Services\AutoAssign;
use App\Support\StatusLog;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class SystemStatus extends StatsOverviewWidget
{
    use UsesCurrentEvent;

    protected static ?int $sort = 2;

    protected ?string $pollingInterval = '30s';

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $reconcile = StatusLog::get(Reconcile::lastResultKey($this->event()));
        $failed = $reconcile['failed'] ?? false;

        return [
            Stat::make('Letzter Webhook', $this->ago(WebhookEvent::max('received_at'))),
            Stat::make('Letzter Abgleich', $this->ago($reconcile['at'] ?? null))
                ->description($failed ? 'Fehlgeschlagen' : ($reconcile ? 'Erfolgreich' : null))
                ->color($failed ? 'danger' : 'gray'),
            Stat::make('Letzte Auto-Zuteilung', $this->ago(StatusLog::get(AutoAssign::lastRunKey($this->event())))),
            Stat::make('Letztes Backup', $this->ago(StatusLog::get(BackupDatabase::LAST_BACKUP_KEY))),
        ];
    }

    private function ago(?string $date): string
    {
        return $date ? Carbon::parse($date)->locale('de')->diffForHumans() : 'Noch nie';
    }
}
