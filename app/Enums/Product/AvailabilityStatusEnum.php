<?php

namespace App\Enums\Product;

use App\Traits\AdvanceEnum;

enum AvailabilityStatusEnum: string
{
    use AdvanceEnum;

    case PAST = 'past';
    case UPCOMING = 'upcoming';
    case ONGOING = 'ongoing';
}
