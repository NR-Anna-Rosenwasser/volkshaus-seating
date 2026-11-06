<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\Pages\Concerns\ShowsOneEvent;
use BackedEnum;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;

class EventExports extends Page
{
    use ShowsOneEvent;

    protected string $view = 'filament.pages.exporte';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static ?string $title = 'Exporte';

    protected static ?string $navigationLabel = 'Exporte';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return ['event' => $this->event()];
    }
}
