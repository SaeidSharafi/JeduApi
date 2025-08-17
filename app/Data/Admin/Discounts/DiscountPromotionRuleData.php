<?php

declare(strict_types=1);

namespace App\Data\Admin\Discounts;

use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

final class DiscountPromotionRuleData extends Data
{
    public function __construct(
        public int $id,
        public int $discount_promotion_id,
        public string $type,
        public string $handler,
        public array $configuration,
        #[WithTransformer(DateTimeInterfaceTransformer::class, 'Y-m-d H:i:s')]
        public Verta $created_at,
        #[WithTransformer(DateTimeInterfaceTransformer::class, 'Y-m-d H:i:s')]
        public Verta $updated_at,
    ) {}
}
