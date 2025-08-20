<?php

declare(strict_types=1);

namespace App\Enums\Operators;

use App\Traits\AdvanceEnum;

enum MathOperatorEnum: string
{
    use AdvanceEnum;
    case EQUAL                 = '==';
    case LESS_THAN             = '<';
    case GREATER_THAN          = '>';
    case LESS_THAN_OR_EQUAL    = '<=';
    case GREATER_THAN_OR_EQUAL = '>=';
    case NOT_EQUAL             = '<>';
}
