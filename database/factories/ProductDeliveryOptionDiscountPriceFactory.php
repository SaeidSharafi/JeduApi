<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DiscountPromotion;
use App\Models\ProductDeliveryOption;
use App\Models\ProductDeliveryOptionDiscountPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

final class ProductDeliveryOptionDiscountPriceFactory extends Factory
{
    protected $model = ProductDeliveryOptionDiscountPrice::class;

    public function definition(): array
    {
        return [
            'product_delivery_option_id' => ProductDeliveryOption::factory(),
            'discount_promotion_id' => DiscountPromotion::factory(),
            'discounted_price' => $this->faker->numberBetween(1000, 50000), // Price in cents
        ];
    }

    /**
     * Create a discount for a specific product delivery option.
     */
    public function forProductDeliveryOption(ProductDeliveryOption|int $productDeliveryOption): static
    {
        $productDeliveryOptionId = $productDeliveryOption instanceof ProductDeliveryOption
            ? $productDeliveryOption->id
            : $productDeliveryOption;

        return $this->state([
            'product_delivery_option_id' => $productDeliveryOptionId,
        ]);
    }

    /**
     * Create a discount with a specific price.
     */
    public function withPrice(int $price): static
    {
        return $this->state([
            'discounted_price' => $price,
        ]);
    }
}
