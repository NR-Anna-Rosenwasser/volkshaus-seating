<?php

namespace App\Filament\Pages\Tenancy;

use App\Models\Event;
use App\Services\SeatImporter;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\RegisterTenant;
use Filament\Schemas\Schema;

class RegisterEvent extends RegisterTenant
{
    public static function getLabel(): string
    {
        return 'Neues Event';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Name')->required()->maxLength(255),
            TextInput::make('hievents_event_id')->label('Event-ID in Hi.Events')->required()->unique(Event::class, 'hievents_event_id'),
            TextInput::make('venue_name')->label('Ort')->maxLength(255),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRegistration(array $data): Event
    {
        $event = Event::create($data);

        app(SeatImporter::class)->import($event, config('seating.seat_data_path'));

        return $event;
    }
}
