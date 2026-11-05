<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Active = 'active';
    case Cancelled = 'cancelled';
}
