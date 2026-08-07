<?php

declare(strict_types=1);

namespace App\Enums\Product;

use App\Traits\AdvanceEnum;

enum AvailabilityStatusEnum: string
{
    /** @use AdvanceEnum<value-of<self>> */
    use AdvanceEnum;

    case PAST     = 'past';
    case UPCOMING = 'upcoming';
    case ONGOING  = 'ongoing';
}
