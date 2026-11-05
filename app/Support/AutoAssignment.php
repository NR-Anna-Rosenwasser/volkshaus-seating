<?php

namespace App\Support;

use App\Models\Order;

/**
 * What auto-assign did for one order. No seats means it could not be seated.
 */
final readonly class AutoAssignment
{
    /**
     * @param  list<string>  $seatIds  The seats assigned in this run.
     */
    public function __construct(
        public Order $order,
        public int $missing,
        public array $seatIds,
        public bool $split,
    ) {}

    public function isSeated(): bool
    {
        return $this->seatIds !== [];
    }
}
