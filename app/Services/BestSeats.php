<?php

namespace App\Services;

use App\Models\Seat;
use App\Support\VenueLayout;
use Illuminate\Support\Collection;

/**
 * Finds the best free seats for a group. Deterministic: the same free seats
 * and group size always give the same result. A lower rank is a better seat.
 */
class BestSeats
{
    /** How many starting points to try when a group has to be split. */
    private const SPLIT_ANCHORS = 40;

    private const MAX_GROUPS = 4;

    /** Per row between two parts of a group, beyond the row right behind. */
    private const ROW_PENALTY = 200;

    private const SECTION_PENALTY = 5000;

    public function __construct(private readonly VenueLayout $layout) {}

    /**
     * @param  list<string>  $ownSeatIds  Seats that count as free for this group.
     * @return list<string>
     */
    public function find(int $count, array $ownSeatIds = []): array
    {
        $seats = $this->freeSeats($ownSeatIds);

        if ($count < 1 || $seats->count() < $count) {
            return [];
        }

        $runs = $this->runs($seats);
        $whole = $this->windows($runs, $count)->first();

        return $this->ids($whole !== null ? [$whole] : $this->split($count, $runs, $seats));
    }

    /**
     * @param  list<string>  $ownSeatIds
     * @return Collection<int, array<string, mixed>>
     */
    private function freeSeats(array $ownSeatIds): Collection
    {
        $center = $this->layout->centerX();
        $order = array_flip(config('seating.section_preference'));
        $sideSections = $this->layout->verticalSections();

        return Seat::query()
            ->sellable()
            ->where(fn ($query) => $query->whereNull('attendee_id')->orWhereIn('id', $ownSeatIds))
            ->get()
            ->map(function (Seat $seat) use ($center, $order, $sideSections): array {
                $side = in_array($seat->section, $sideSections, true);

                return [
                    'id' => $seat->id,
                    'section' => $seat->section,
                    'row' => $seat->row,
                    'number' => $seat->number,
                    'segment' => $seat->segment,
                    // Obstructed seats come last everywhere. Side galleries run away from the stage.
                    'sort' => [
                        $seat->hasFlag('obstructed') ? 1 : 0,
                        $order[$seat->section] ?? PHP_INT_MAX,
                        $side ? $seat->number : $seat->row,
                        $side ? $seat->row : abs($seat->x - $center),
                        $seat->id,
                    ],
                ];
            })
            ->sortBy(fn (array $seat): array => $seat['sort'])
            ->values()
            ->map(fn (array $seat, int $rank): array => [...$seat, 'rank' => $rank]);
    }

    /**
     * Unbroken stretches of free seats. Seats are neighbours only within one
     * row and segment: pillars and gaps start a new segment.
     *
     * @param  Collection<int, array<string, mixed>>  $seats
     * @return list<list<array<string, mixed>>>
     */
    private function runs(Collection $seats): array
    {
        $runs = [];

        $segments = $seats->groupBy(fn (array $seat): string => "{$seat['section']}|{$seat['row']}|{$seat['segment']}");

        foreach ($segments as $segment) {
            $run = [];

            foreach ($segment->sortBy('number') as $seat) {
                if ($run !== [] && $seat['number'] !== end($run)['number'] + 1) {
                    $runs[] = $run;
                    $run = [];
                }

                $run[] = $seat;
            }

            $runs[] = $run;
        }

        return $runs;
    }

    /**
     * Every block of the given size, best first.
     *
     * @param  list<list<array<string, mixed>>>  $runs
     * @return Collection<int, list<array<string, mixed>>>
     */
    private function windows(array $runs, int $size): Collection
    {
        $windows = [];

        foreach ($runs as $run) {
            for ($start = 0; $start + $size <= count($run); $start++) {
                $windows[] = array_slice($run, $start, $size);
            }
        }

        return collect($windows)
            ->sortBy(fn (array $window): array => [$this->cost($window), $window[0]['id']])
            ->values();
    }

    /**
     * Uses as few parts as possible, and avoids a part of one while another
     * split of the same size works.
     *
     * @param  list<list<array<string, mixed>>>  $runs
     * @param  Collection<int, array<string, mixed>>  $seats
     * @return list<list<array<string, mixed>>>
     */
    private function split(int $count, array $runs, Collection $seats): array
    {
        for ($groups = 2; $groups <= min($count, self::MAX_GROUPS); $groups++) {
            foreach ([false, true] as $allowSingles) {
                $plans = collect($this->partitions($count, $groups))
                    ->filter(fn (array $parts): bool => $allowSingles || ! in_array(1, $parts, true))
                    ->map(fn (array $parts): ?array => $this->place($parts, $runs))
                    ->filter();

                if ($plans->isNotEmpty()) {
                    return $plans->sortBy('cost')->first()['windows'];
                }
            }
        }

        return $seats->take($count)->map(fn (array $seat): array => [$seat])->values()->all();
    }

    /**
     * @param  list<int>  $parts
     * @param  list<list<array<string, mixed>>>  $runs
     * @return array{windows: list<list<array<string, mixed>>>, cost: int}|null
     */
    private function place(array $parts, array $runs): ?array
    {
        $bySize = [];

        foreach (array_unique($parts) as $size) {
            $bySize[$size] = $this->windows($runs, $size);
        }

        $best = null;

        foreach ($bySize[$parts[0]]->take(self::SPLIT_ANCHORS) as $anchor) {
            $plan = [$anchor];
            $cost = $this->cost($anchor);
            $taken = array_column($anchor, 'id');

            foreach (array_slice($parts, 1) as $size) {
                $next = $bySize[$size]
                    ->reject(fn (array $window): bool => array_intersect(array_column($window, 'id'), $taken) !== [])
                    ->sortBy(fn (array $window): array => [$this->cost($window) + $this->distance($anchor[0], $window[0]), $window[0]['id']])
                    ->first();

                if ($next === null) {
                    continue 2;
                }

                $plan[] = $next;
                $cost += $this->cost($next) + $this->distance($anchor[0], $next[0]);
                $taken = [...$taken, ...array_column($next, 'id')];
            }

            if ($best === null || $cost < $best['cost']) {
                $best = ['windows' => $plan, 'cost' => $cost];
            }
        }

        return $best;
    }

    /**
     * Ways to write $count as $groups parts, largest part first.
     *
     * @return list<list<int>>
     */
    private function partitions(int $count, int $groups, ?int $max = null): array
    {
        $max ??= $count;

        if ($groups === 1) {
            return $count <= $max ? [[$count]] : [];
        }

        $partitions = [];

        for ($first = min($max, $count - $groups + 1); $first >= 1; $first--) {
            foreach ($this->partitions($count - $first, $groups - 1, $first) as $rest) {
                $partitions[] = [$first, ...$rest];
            }
        }

        return $partitions;
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    private function distance(array $a, array $b): int
    {
        if ($a['section'] !== $b['section']) {
            return self::SECTION_PENALTY;
        }

        return max(abs($a['row'] - $b['row']) - 1, 0) * self::ROW_PENALTY;
    }

    /**
     * @param  list<array<string, mixed>>  $window
     */
    private function cost(array $window): int
    {
        return array_sum(array_column($window, 'rank'));
    }

    /**
     * @param  list<list<array<string, mixed>>>  $windows
     * @return list<string>
     */
    private function ids(array $windows): array
    {
        return collect($windows)->flatten(1)->pluck('id')->all();
    }
}
