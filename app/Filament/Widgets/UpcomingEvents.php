<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Events\EventResource;
use App\Models\Event;
use App\Services\SeatStats;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * The way into an event from the overview.
 */
class UpcomingEvents extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Events';

    public function table(Table $table): Table
    {
        return $table
            ->query(Event::query()->active())
            ->defaultSort('starts_at')
            ->paginated(false)
            ->emptyStateHeading('Noch keine Events')
            ->recordUrl(fn (Event $record): string => EventResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('name')->label('Name'),
                TextColumn::make('starts_at')->label('Beginn')->dateTime('d.m.Y, H:i')->placeholder('Offen'),
                TextColumn::make('sold')->label('Verkauft')->alignCenter()
                    ->state(fn (Event $record): int => app(SeatStats::class)->snapshot($record)['sold']),
                TextColumn::make('free')->label('Freie Plätze')->alignCenter()
                    ->state(fn (Event $record): int => app(SeatStats::class)->snapshot($record)['free']),
            ]);
    }
}
