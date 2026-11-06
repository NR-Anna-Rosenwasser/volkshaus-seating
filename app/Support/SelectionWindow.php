<?php

namespace App\Support;

use App\Models\Event;
use Carbon\CarbonInterface;

/**
 * When buyers may choose seats. Without dates selection stays closed, so a
 * forgotten setting can't leave it open forever.
 */
class SelectionWindow
{
    private function __construct(private readonly Event $event) {}

    public static function for(Event $event): self
    {
        return new self($event);
    }

    public function opensAt(): ?CarbonInterface
    {
        return $this->event->selection_opens_at;
    }

    public function deadline(): ?CarbonInterface
    {
        return $this->event->selection_deadline?->copy();
    }

    public function hasStarted(): bool
    {
        return $this->opensAt()?->isPast() ?? false;
    }

    public function hasEnded(): bool
    {
        return $this->deadline()?->isPast() ?? false;
    }

    public function isOpen(): bool
    {
        return $this->hasStarted() && $this->deadline() !== null && ! $this->hasEnded();
    }

    public static function format(?CarbonInterface $date): string
    {
        return $date?->locale('de')->isoFormat('dddd, D. MMMM YYYY, HH:mm').' Uhr';
    }
}
