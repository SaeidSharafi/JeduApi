<?php

declare(strict_types=1);

namespace App\Data\Admin\Discounts;

use Spatie\LaravelData\Data;

final class DiscountPromotionRuleCreateData extends Data
{
    public function __construct(
        public string $type,           // 'condition' or 'action'
        public string $handler,        // e.g., 'cart_value_over', 'apply_percentage_off'
        public array $configuration,   // Handler-specific configuration
    ) {}
}
