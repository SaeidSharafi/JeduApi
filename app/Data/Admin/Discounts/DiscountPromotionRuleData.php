<?php

declare(strict_types=1);

namespace App\Data\Admin\Discounts;

use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Data;

final class DiscountPromotionRuleData extends Data
{
    public function __construct(
        public int $id,
        public int $discount_promotion_id,
        public string $type,
        public string $handler,
        public array $configuration,
        public ?Verta $created_at,
        public ?Verta $updated_at,
    ) {}
}
