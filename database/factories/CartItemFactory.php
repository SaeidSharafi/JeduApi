<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Models\Cart;
use App\Models\ProductDeliveryOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CartItem>
 */
final class CartItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cart_id'                    => Cart::factory(),
            'product_delivery_option_id' => ProductDeliveryOption::factory(),
            'payment_type'               => OrderItemPaymentTypeEnum::FULL_PAYMENT,
            'quantity'                   => $this->faker->numberBetween(1, 5),
        ];
    }
}
