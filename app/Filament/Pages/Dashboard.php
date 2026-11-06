<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\SystemStatus;
use App\Filament\Widgets\UpcomingEvents;
use Filament\Pages\Dashboard as BaseDashboard;

/**
 * Across all events. What belongs to one event is on its own page.
 */
class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Übersicht';

    protected static ?string $navigationLabel = 'Übersicht';

    public function getWidgets(): array
    {
        return [SystemStatus::class, UpcomingEvents::class];
    }

    public function getColumns(): int
    {
        return 1;
    }
}
