<?php

namespace App\Enums;

enum MatchStatus: string
{
    case LIVE      = 'LIVE';
    case SCHEDULED = 'SCHEDULED';
    case FINISHED  = 'FINISHED';
    case POSTPONED = 'POSTPONED';
    case CANCELLED = 'CANCELLED';
}
