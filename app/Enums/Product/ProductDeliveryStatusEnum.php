<?php

declare(strict_types=1);

namespace App\Enums\Product;

use App\Traits\AdvanceEnum;

enum ProductDeliveryStatusEnum: string
{
    /** @use AdvanceEnum<value-of<self>> */
    use AdvanceEnum;

    case ONLINE    = 'online';
    case IN_PERSON = 'in_person';
    case COMBINED  = 'combined';
}
