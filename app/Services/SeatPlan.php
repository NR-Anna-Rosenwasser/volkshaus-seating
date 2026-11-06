<?php

namespace App\Services;

use App\Enums\SeatState;
use App\Models\Event;
use Illuminate\Support\Facades\Cache;

/**
 * The public view of the hall: seat states and how many seats are left.
 * Everything in here is safe to show to anyone.
 */
class SeatPlan
{
    public const CACHE_SECONDS = 30;

    /**
     * @return array{
     *     seats: array<string, string>,
     *     sections: array<string, array{free: int, sellable: int}>,
     *     total: array{free: int, sellable: int}
     * }
     */
    public function snapshot(Event $event): array
    {
        return Cache::remember(self::cacheKey($event), self::CACHE_SECONDS, fn (): array => $this->fresh($event));
    }

    public static function cacheKey(Event $event): string
    {
        return "seating.public-plan.{$event->id}";
    }

    /**
     * @return array{
     *     seats: array<string, string>,
     *     sections: array<string, array{free: int, sellable: int}>,
     *     total: array{free: int, sellable: int}
     * }
     */
    public function fresh(Event $event): array
    {
        $plan = ['seats' => [], 'sections' => [], 'total' => ['free' => 0, 'sellable' => 0]];

        foreach ($event->seats()->get(['code', 'section', 'block_reason', 'attendee_id']) as $seat) {
            $state = $seat->state();
            $plan['seats'][$seat->code] = $state->value;
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
