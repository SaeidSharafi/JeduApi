<?php

declare(strict_types=1);

namespace App\Data\Admin\Discounts;

use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Data;

final class DiscountCouponData extends Data
{
    public function __construct(
        public int $id,
        public int $discount_promotion_id,
        public string $code,
        public bool $is_active,
        public ?int $usage_limit,
        public int $usage_count,
        public ?Verta $created_at,
        public ?Verta $updated_at,
    ) {}
}
