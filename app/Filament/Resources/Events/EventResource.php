<?php

namespace App\Filament\Resources\Events;

use App\Filament\Resources\Events\Pages\CreateEvent;
use App\Filament\Resources\Events\Pages\EditEvent;
use App\Filament\Resources\Events\Pages\EventExports;
use App\Filament\Resources\Events\Pages\EventInfodesk;
use App\Filament\Resources\Events\Pages\EventSaalplan;
use App\Filament\Resources\Events\Pages\ListEvents;
use App\Filament\Resources\Events\Pages\ManageEventOrders;
use App\Filament\Resources\Events\Pages\ViewEvent;
use App\Models\Event;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * An event is an ordinary record. It is in the URL of everything below it, so
 * two open tabs can't act on the wrong one, but it isn't a Filament tenant:
 * that place is kept for a future organizer that events belong to.
 */
class EventResource extends Resource
{
    protected static ?string $model = Event::class;

    protected static ?string $modelLabel = 'Event';

    protected static ?string $pluralModelLabel = 'Events';

    protected static ?string $slug = 'events';

    protected static ?string $recordRouteKeyName = 'slug';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = 1;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    /**
     * The form for a new event. Everything else is in the settings.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Name')->required()->maxLength(255),
            TextInput::make('hievents_event_id')->label('Event-ID in Hi.Events')->required()->unique(Event::class, 'hievents_event_id'),
            TextInput::make('venue_name')->label('Ort')->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('orders'))
            ->defaultSort('starts_at', 'desc')
            ->columns([
                TextColumn::make('name')->label('Name')->searchable()->sortable(),
                TextColumn::make('starts_at')->label('Beginn')->dateTime('d.m.Y, H:i')->sortable()->placeholder('Offen'),
                TextColumn::make('venue_name')->label('Ort')->placeholder('Offen'),
                TextColumn::make('orders_count')->label('Bestellungen')->alignCenter(),
            ]);
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            ViewEvent::class,
            EventSaalplan::class,
            ManageEventOrders::class,
            EventInfodesk::class,
            EventExports::class,
            EditEvent::class,
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEvents::route('/'),
            'create' => CreateEvent::route('/neu'),
            'view' => ViewEvent::route('/{record}'),
            'saalplan' => EventSaalplan::route('/{record}/saalplan'),
            'orders' => ManageEventOrders::route('/{record}/bestellungen'),
            'infodesk' => EventInfodesk::route('/{record}/infodesk'),
            'exporte' => EventExports::route('/{record}/exporte'),
            'edit' => EditEvent::route('/{record}/einstellungen'),
        ];
    }
}
