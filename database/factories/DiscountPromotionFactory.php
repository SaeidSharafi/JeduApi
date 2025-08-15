<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Order\DiscountTypeEnum;
use App\Models\DiscountPromotion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @mixin Factory<DiscountPromotion> */
final class DiscountPromotionFactory extends Factory
{
    protected $model = DiscountPromotion::class;

    public function definition(): array
    {
        return [
            'name'                             => $this->faker->unique()->sentence(3),
            'description'                      => $this->faker->optional()->paragraph,
            'type'                             => DiscountTypeEnum::PRODUCT_SPECIFIC,
            'is_active'                        => true,
            'starts_at'                        => null,
            'ends_at'                          => null,
            'priority'                         => 0,
            'stop_processing_subsequent_rules' => false,
            'usage_limit_total'                => null,
            'usage_limit_per_customer'         => null,
            'total_usage_count'                => 0,
        ];
    }
}
