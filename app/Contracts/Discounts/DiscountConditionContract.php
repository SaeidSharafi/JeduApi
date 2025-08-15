<?php

declare(strict_types=1);

namespace App\Contracts\Discounts;

use App\Data\Admin\Discounts\OrderContextData;
use Spatie\LaravelData\Data;

interface DiscountConditionContract
{
    public function passes(OrderContextData $context, Data $configuration): bool;
}
