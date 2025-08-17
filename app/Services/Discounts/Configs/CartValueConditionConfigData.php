<?php

declare(strict_types=1);

namespace App\Services\Discounts\Configs;

use App\Enums\OperatorEnum;
use Spatie\LaravelData\Data;

final class CartValueConditionConfigData extends Data
{
    public function __construct(
        public OperatorEnum $operator,
        public int $value,      // The value to compare against
        public bool $include_prepayments, // If true, check against the cart's full value
    ) {}
}
