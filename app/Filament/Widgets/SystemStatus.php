<?php

namespace App\Filament\Widgets;

use App\Console\Commands\BackupDatabase;
use App\Models\WebhookEvent;
use App\Support\StatusLog;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

/**
 * What is shared by all events.
 */
class SystemStatus extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '30s';

    protected function getColumns(): int
    {
        return 2;
    }

    protected function getStats(): array
    {
        return [
            Stat::make('Letzter Webhook', self::ago(WebhookEvent::max('received_at'))),
            Stat::make('Letztes Backup', self::ago(StatusLog::get(BackupDatabase::LAST_BACKUP_KEY))),
        ];
    }

    public static function ago(?string $date): string
    {
        return $date ? Carbon::parse($date)->locale('de')->diffForHumans() : 'Noch nie';
    }
}
