<?php

namespace App\Filament\Resources\Events\Pages\Concerns;

use App\Filament\Resources\Events\EventResource;
use App\Models\Event;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;

/**
 * A custom page below an event. The event comes from the URL.
 */
trait ShowsOneEvent
{
    use InteractsWithRecord;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    protected function event(): Event
    {
        /** @var Event */
        return $this->getRecord();
    }

    public static function getResource(): string
    {
        return EventResource::class;
    }
}
