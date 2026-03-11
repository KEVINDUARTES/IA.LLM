<?php

namespace App\Enums;

enum MatchResult: string
{
    case MATCH    = 'match';
    case NO_MATCH = 'no_match';
    case UNKNOWN  = 'unknown';
}
