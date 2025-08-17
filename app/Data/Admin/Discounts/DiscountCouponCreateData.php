<?php

declare(strict_types=1);

namespace App\Data\Admin\Discounts;

use Spatie\LaravelData\Data;

final class DiscountCouponCreateData extends Data
{
    public function __construct(
        public string $code,
        public ?int $usage_limit = null,
        public bool $is_active = true,
    ) {}
}
