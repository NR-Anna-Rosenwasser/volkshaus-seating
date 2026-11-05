<?php

namespace App\Enums;

enum MailType: string
{
    case Link = 'link';
    case Confirmation = 'confirmation';
    case Reminder = 'reminder';
    case AutoAssigned = 'auto_assigned';
    case Final = 'final';
}
