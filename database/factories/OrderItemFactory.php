<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrderItemPaymentTypeEnum;
use App\Enums\OrderItemStatusEnum;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductDeliveryOption;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

final class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        $product = ProductDeliveryOption::factory()->create()->fresh();

        return [
            'order_id'                   => Order::factory(),
            'product_delivery_option_id' => $product,
            'qty_ordered'                => $this->faker->numberBetween(1, 10),
            'payment_type'               => $this->faker->randomElement(OrderItemPaymentTypeEnum::getAllValues()),
            'name'                       => $this->faker->name(),
            'sku'                        => $this->faker->word(),
            'vendor_id'                  => Vendor::factory(),
            'product_data_snapshot_json' => $product->toArray(),
            'price'                      => $this->faker->randomNumber(),
            'discount_amount'            => $this->faker->randomNumber(),
            'tax_amount'                 => $this->faker->randomNumber(),
            'total'                      => $this->faker->randomNumber(),
            'prepayment_amount'          => $this->faker->randomNumber(),
            'total_refunded'             => $this->faker->randomNumber(),
            'qty_refunded'               => $this->faker->numberBetween(0, 5),
            'status'                     => $this->faker->randomElement(OrderItemStatusEnum::getAllValues()),
            'created_at'                 => Carbon::now(),
            'updated_at'                 => Carbon::now(),
        ];
    }
}
