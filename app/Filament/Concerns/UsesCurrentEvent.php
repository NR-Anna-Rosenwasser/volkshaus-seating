<?php

namespace App\Filament\Concerns;

use App\Models\Event;
use Filament\Facades\Filament;

/**
 * The event in the URL. Always from there, never the session, so two open
 * tabs can't act on the wrong event.
 */
trait UsesCurrentEvent
{
    protected function event(): Event
    {
        /** @var Event */
        return Filament::getTenant();
    }
}
