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

    public function label(): string
    {
        return match ($this) {
            self::Vip => 'VIP',
            self::Security => 'Sicherheit',
            self::Press => 'Presse',
            self::Tech => 'Technik',
            self::Wheelchair => 'Rollstuhl',
            self::Staff => 'Team',
            self::Other => 'Anderes',
        };
    }
}
