<?php

namespace App\Enums\Product;

use App\Traits\AdvanceEnum;

enum ProductRegistrationStatusEnum: string
{
    use AdvanceEnum;

    case IN_PROGRESS = 'in_progress';
    case FINISHED = 'finished';
}
