<?php

namespace App\Enums;

/**
 * What the public may know about a seat. Deliberately hides why a seat is
 * blocked and who sits on it.
 */
enum SeatState: string
{
    case Free = 'free';
    case Taken = 'taken';
    case Blocked = 'blocked';
}
