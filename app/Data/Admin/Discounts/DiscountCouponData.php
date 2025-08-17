<?php

declare(strict_types=1);

namespace App\Data\Admin\Discounts;

use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

final class DiscountCouponData extends Data
{
    public function __construct(
        public int $id,
        public int $discount_promotion_id,
        public string $code,
        public bool $is_active,
        public ?int $usage_limit,
        public int $usage_count,
        #[WithTransformer(DateTimeInterfaceTransformer::class, 'Y-m-d H:i:s')]
        public Verta $created_at,
        #[WithTransformer(DateTimeInterfaceTransformer::class, 'Y-m-d H:i:s')]
        public Verta $updated_at,
    ) {}
}
