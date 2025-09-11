<?php

declare(strict_types=1);

namespace App\Data\Admin\Discounts;

use App\Data\Transformer\TranslatableEnumData;
use App\Enums\Order\DiscountTypeEnum;
use Hekmatinasser\Verta\Verta;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

final class DiscountPromotionData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $description,
        #[WithCast(EnumCast::class), WithTransformer(TranslatableEnumData::class)]
        public DiscountTypeEnum $type,
        public bool $is_active,
        public ?Verta $starts_at,
        public ?Verta $ends_at,
        public int $priority,
        public bool $stop_processing_subsequent_rules,
        public ?int $usage_limit_total,
        public ?int $usage_limit_per_customer,
        public int $total_usage_count,
        public Verta $created_at,
        public Verta $updated_at,
        #[DataCollectionOf(DiscountPromotionRuleData::class)]
        public ?Collection $rules,
        #[DataCollectionOf(DiscountCouponData::class)]
        public ?Collection $coupons,
    ) {}
}
