<?php

namespace App\Filament\Widgets;

use App\Console\Commands\BackupDatabase;
use App\Console\Commands\Reconcile;
use App\Models\WebhookEvent;
use App\Services\AutoAssign;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class SystemStatus extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected ?string $pollingInterval = '30s';

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $reconcile = Cache::get(Reconcile::LAST_RESULT_KEY);
        $failed = $reconcile['failed'] ?? false;

        return [
            Stat::make('Letzter Webhook', $this->ago(WebhookEvent::max('received_at'))),
            Stat::make('Letzter Abgleich', $this->ago($reconcile['at'] ?? null))
                ->description($failed ? 'Fehlgeschlagen' : ($reconcile ? 'Erfolgreich' : null))
                ->color($failed ? 'danger' : 'gray'),
            Stat::make('Letzte Auto-Zuteilung', $this->ago(Cache::get(AutoAssign::LAST_RUN_KEY))),
            Stat::make('Letztes Backup', $this->ago(Cache::get(BackupDatabase::LAST_BACKUP_KEY))),
        ];
    }

    private function ago(?string $date): string
    {
        return $date ? Carbon::parse($date)->locale('de')->diffForHumans() : 'Noch nie';
    }
}
