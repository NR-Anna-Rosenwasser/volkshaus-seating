<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Services\AutoAssign;
use App\Support\AutoAssignment;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

#[Signature('seating:auto-assign {event : Event slug} {--dry-run : Show the result without changing anything}')]
#[Description('Assign seats to everyone who has not chosen by the deadline')]
class AutoAssignSeats extends Command
{
    public function handle(AutoAssign $autoAssign): int
    {
        $event = Event::where('slug', $this->argument('event'))->firstOrFail();
        $dryRun = (bool) $this->option('dry-run');

        // Buyers were promised they can choose until the deadline.
        if (! $dryRun && ! $event->window()->hasEnded()) {
            $this->error('The selection deadline has not passed yet. Use --dry-run for a preview.');

            return self::FAILURE;
        }

        $results = $dryRun ? $autoAssign->preview($event) : $autoAssign->run($event);

        if ($dryRun) {
            $this->warn('Dry run: nothing was changed, and no mails were sent.');
            $this->table(['Order', 'Tickets', 'Seats', 'Split'], $results->map(fn (AutoAssignment $result): array => [
                $result->order->short_id,
                $result->missing,
                $result->isSeated() ? implode(', ', $result->seatIds) : 'NO SEATS',
                $result->split ? 'yes' : '',
            ]));
        }

        $this->summary($event, $results);

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, AutoAssignment>  $results
     */
    private function summary(Event $event, Collection $results): void
    {
        [$seated, $unseated] = $results->partition(fn (AutoAssignment $result): bool => $result->isSeated());
        $split = $seated->filter(fn (AutoAssignment $result): bool => $result->split);

        $this->info("Assigned: {$seated->count()} orders, {$seated->sum('missing')} tickets.");
        $this->line("Split: {$split->count()} orders".$this->ids($split));
        $this->line("Unassignable: {$unseated->count()} orders, {$unseated->sum('missing')} tickets".$this->ids($unseated));

        // A preview has rolled back, so this count excludes what it would assign.
        $free = $event->seats()->sellable()->whereNull('attendee_id')->count() - ($this->option('dry-run') ? $seated->sum('missing') : 0);
        $this->line("Free seats left: {$free}.");
    }

    /**
     * @param  Collection<int, AutoAssignment>  $results
     */
    private function ids(Collection $results): string
    {
        return $results->isEmpty() ? '.' : ': '.$results->map(fn (AutoAssignment $result): string => $result->order->short_id)->implode(', ').'.';
    }
}
