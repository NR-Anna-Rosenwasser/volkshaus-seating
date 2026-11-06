<?php

namespace App\Filament\Widgets;

use App\Enums\BlockReason;
use App\Models\Event;
use App\Services\SeatStats;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SeatOverview extends StatsOverviewWidget
{
    /** The event page passes its record. */
    public ?Event $record = null;

    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '30s';

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $stats = app(SeatStats::class)->snapshot($this->record);
        $blocked = collect($stats['blocked'])
            ->map(fn (int $seats, string $reason): string => BlockReason::from($reason)->label().": {$seats}")
            ->implode(', ');

        return [
            Stat::make('Verkauft', $stats['sold'])->description('Tickets mit Sitzplatz'),
            Stat::make('Zugewiesen', $stats['assigned']),
            Stat::make('Ohne Platz', $stats['unassigned'])
                ->description("{$stats['ordersWithoutSeats']} Bestellungen haben noch nicht gewählt"),
            Stat::make('Gesperrt', array_sum($stats['blocked']))->description($blocked ?: 'Keine'),
            Stat::make('Frei', $stats['free']),
            Stat::make('Verkaufbar', $stats['sellable'])
                ->description($stats['overbooked'] > 0
                    ? "{$stats['overbooked']} Tickets mehr als Plätze! Kapazität in Hi.Events prüfen."
                    : "Kapazität in Hi.Events: {$stats['sellable']}")
                ->color($stats['overbooked'] > 0 ? 'danger' : 'gray'),
        ];
    }
}
