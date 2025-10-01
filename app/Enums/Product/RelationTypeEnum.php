<?php

declare(strict_types=1);

namespace App\Enums\Product;

use App\Traits\AdvanceEnum;

enum RelationTypeEnum: string
{
    use AdvanceEnum;

    case RELATED    = 'related';
    case CROSS_SELL = 'cross_sell';
    case UPSELL     = 'upsell';
}
