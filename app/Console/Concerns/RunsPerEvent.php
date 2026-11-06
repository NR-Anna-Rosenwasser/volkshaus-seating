<?php

namespace App\Console\Concerns;

use App\Models\Event;
use App\Services\AdminAlert;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Commands that work through events one by one. A failure in one event is
 * reported and must not keep the others from running.
 *
 * @mixin Command
 */
trait RunsPerEvent
{
    /**
     * @param  callable(Event): int  $work
     */
    protected function forEachEvent(callable $work): int
    {
        $status = self::SUCCESS;

        foreach ($this->selectedEvents() as $event) {
            try {
                $status = max($status, $work($event));
            } catch (Throwable $exception) {
                report($exception);
                app(AdminAlert::class)->send("{$this->getName()} fehlgeschlagen", 'Der Befehl konnte nicht abgeschlossen werden.', $event);
                $this->error("{$event->slug}: failed.");
                $status = self::FAILURE;
            }
        }

        return $status;
    }

    /**
     * @return Collection<int, Event>
     */
    protected function selectedEvents(): Collection
    {
        $slug = $this->option('event');

        return $slug ? collect([Event::where('slug', $slug)->firstOrFail()]) : Event::running();
    }
}
