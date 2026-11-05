<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class Exporte extends Page
{
    protected string $view = 'filament.pages.exporte';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static ?string $title = 'Exporte';

    protected static ?string $slug = 'exporte';

    protected static ?int $navigationSort = 5;
}
