<?php

namespace App\Enums;

enum BlockReason: string
{
    case Vip = 'vip';
    case Security = 'security';
    case Press = 'press';
    case Tech = 'tech';
    case Wheelchair = 'wheelchair';
    case Staff = 'staff';
    case Other = 'other';
}
