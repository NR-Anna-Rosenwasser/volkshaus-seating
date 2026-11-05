<?php

namespace App\Enums;

enum SyncResult: string
{
    case Ignored = 'ignored';
    case Created = 'created';
    case Updated = 'updated';
    case Cancelled = 'cancelled';
    case Unchanged = 'unchanged';
}
