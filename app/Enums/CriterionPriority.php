<?php

namespace App\Enums;

enum CriterionPriority: string
{
    case HIGH   = 'high';
    case MEDIUM = 'medium';
    case LOW    = 'low';

    /**
     * Default weight derived from priority when none is explicitly set.
     */
    public function defaultWeight(): int
    {
        return match($this) {
            self::HIGH   => 30,
            self::MEDIUM => 20,
            self::LOW    => 10,
        };
    }
}
