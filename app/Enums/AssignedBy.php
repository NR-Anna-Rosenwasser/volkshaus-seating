<?php

namespace App\Enums;

enum AssignedBy: string
{
    case Buyer = 'buyer';
    case Auto = 'auto';
    case Admin = 'admin';
}
