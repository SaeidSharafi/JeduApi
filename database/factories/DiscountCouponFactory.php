<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DiscountCoupon;
use App\Models\DiscountPromotion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @mixin Factory<DiscountCoupon> */
final class DiscountCouponFactory extends Factory
{
    protected $model = DiscountCoupon::class;

    public function definition(): array
    {
        return [
            'discount_promotion_id' => DiscountPromotion::factory(),
            'code'                  => mb_strtoupper($this->faker->unique()->word().$this->faker->randomNumber(4)),
            'is_active'             => true,
            'usage_limit'           => null,
            'usage_count'           => 0,
        ];
    }
}
