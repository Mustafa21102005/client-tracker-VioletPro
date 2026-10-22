<?php

namespace App\Enum;

enum ChangeRequestStatus: string
{
    case Pending = 'pending';
    case Done = 'done';
    case Cancelled = 'cancelled';
}
