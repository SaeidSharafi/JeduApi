<?php

namespace App\Enums\Product;

use App\Traits\AdvanceEnum;

enum ProductDeliveryStatusEnum: string
{
    use AdvanceEnum;

    case ONLINE = 'online';
    case IN_PERSON = 'in_person';
    case COMBINED = 'combined';
}
