<?php

namespace App\Exceptions;

use RuntimeException;

class SeatConflict extends RuntimeException
{
    /**
     * @param  list<string>  $seatIds
     */
    public function __construct(public readonly array $seatIds)
    {
        parent::__construct('Seats are no longer available.');
    }
}
