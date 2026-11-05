<?php

namespace App\Enums;

enum AuditActor: string
{
    case Buyer = 'buyer';
    case Admin = 'admin';
    case System = 'system';
}
