<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * When buyers may choose seats. Without configured dates selection stays
 * closed, so a forgotten setting can't leave it open forever.
 */
class SelectionWindow
{
    public function opensAt(): ?CarbonInterface
    {
        return $this->date('seating.selection_opens_at');
    }

    public function deadline(): ?CarbonInterface
    {
        return $this->date('seating.selection_deadline');
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

    private function date(string $key): ?CarbonInterface
    {
        $value = config($key);

        return $value ? Carbon::parse($value, config('app.timezone')) : null;
    }
}
