<?php

namespace App\Support;

use App\Models\Event;
use Carbon\CarbonInterface;

/**
 * When an event's personal data has to be gone. The cut-off is a date, not a
 * flag: from then on nothing may bring names back from Hi.Events, even after
 * a restart.
 */
class DataRetention
{
    private function __construct(private readonly Event $event) {}

    public static function for(Event $event): self
    {
        return new self($event);
    }

    public function purgeDate(): ?CarbonInterface
    {
        return $this->event->starts_at?->copy()->addDays($this->event->purge_after_days);
    }

    public function isOver(): bool
    {
        return $this->event->purged_at !== null || ($this->purgeDate()?->isPast() ?? false);
    }
}
