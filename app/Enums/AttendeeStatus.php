<?php

namespace App\Enums;

enum AttendeeStatus: string
{
    case Active = 'active';
    case Cancelled = 'cancelled';
}
