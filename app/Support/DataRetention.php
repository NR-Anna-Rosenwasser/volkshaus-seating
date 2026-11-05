<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * When personal data has to be gone. The cut-off is a date, not a flag: from
 * then on nothing may bring names back from Hi.Events, even after a restart.
 */
class DataRetention
{
    public function purgeDate(): ?CarbonInterface
    {
        $event = config('seating.event_date');

        return $event
            ? Carbon::parse($event, config('app.timezone'))->addDays((int) config('seating.purge_after_days'))
            : null;
    }

    public function isOver(): bool
    {
        return $this->purgeDate()?->isPast() ?? false;
    }
}
