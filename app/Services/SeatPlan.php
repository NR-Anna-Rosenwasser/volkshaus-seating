<?php

namespace App\Services;

use App\Enums\SeatState;
use App\Models\Seat;
use Illuminate\Support\Facades\Cache;

/**
 * The public view of the hall: seat states and how many seats are left.
 * Everything in here is safe to show to anyone.
 */
class SeatPlan
{
    public const CACHE_SECONDS = 30;

    private const CACHE_KEY = 'seating.public-plan';

    /**
     * @return array{
     *     seats: array<string, string>,
     *     sections: array<string, array{free: int, sellable: int}>,
     *     total: array{free: int, sellable: int}
     * }
     */
    public function snapshot(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, $this->build(...));
    }

    /**
     * @return array{
     *     seats: array<string, string>,
     *     sections: array<string, array{free: int, sellable: int}>,
     *     total: array{free: int, sellable: int}
     * }
     */
    private function build(): array
    {
        $plan = ['seats' => [], 'sections' => [], 'total' => ['free' => 0, 'sellable' => 0]];

        foreach (Seat::query()->get(['id', 'section', 'block_reason', 'attendee_id']) as $seat) {
            $state = $seat->state();
            $plan['seats'][$seat->id] = $state->value;
            $plan['sections'][$seat->section] ??= ['free' => 0, 'sellable' => 0];

            if ($state === SeatState::Blocked) {
                continue;
            }

            $plan['sections'][$seat->section]['sellable']++;
            $plan['total']['sellable']++;

            if ($state === SeatState::Free) {
                $plan['sections'][$seat->section]['free']++;
                $plan['total']['free']++;
            }
        }

        return $plan;
    }
}
