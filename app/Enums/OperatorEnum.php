<?php

declare(strict_types=1);

namespace App\Enums;

enum OperatorEnum: string
{
    case EQUAL                 = '==';
    case LESS_THAN             = '<';
    case GREATER_THAN          = '>';
    case LESS_THAN_OR_EQUAL    = '<=';
    case GREATER_THAN_OR_EQUAL = '>=';
    case NOT_EQUAL             = '<>';
}
