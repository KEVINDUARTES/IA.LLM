<?php

namespace App\Enums;

enum CriterionType: string
{
    case BOOLEAN   = 'boolean';
    case YEARS     = 'years';
    case ENUM      = 'enum';
    case SCORE_1_5 = 'score_1_5';
}
