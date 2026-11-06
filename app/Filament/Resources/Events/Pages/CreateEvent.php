<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\EventResource;
use App\Models\Event;
use App\Services\SeatImporter;
use Filament\Resources\Pages\CreateRecord;

/**
 * @property Event $record
 */
class CreateEvent extends CreateRecord
{
    protected static string $resource = EventResource::class;

    protected static ?string $title = 'Neues Event';

    protected function afterCreate(): void
    {
        app(SeatImporter::class)->import($this->record, config('seating.seat_data_path'));
    }

    protected function getRedirectUrl(): string
    {
        return EventResource::getUrl('view', ['record' => $this->record]);
    }
}
