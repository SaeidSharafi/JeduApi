<?php

namespace Database\Factories;

use App\Enums\OrderItemStatusEnum;
use App\Enums\OrderStatusEnum;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductDeliveryOption;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        $product = ProductDeliveryOption::factory()->create()->fresh();

        return [
            'order_id'                   => Order::factory(),
            'product_delivery_option_id' => $product,
            'quantity'                   => $this->faker->randomNumber(),
            'name'                       => $this->faker->name(),
            'sku'                        => $this->faker->word(),
            'vendor_id'                  => Vendor::factory(),
            'product_data_snapshot_json' => $product->toArray(),
            'price'                      => $this->faker->randomNumber(),
            'discount_amount'            => $this->faker->randomNumber(),
            'tax_amount'                 => $this->faker->randomNumber(),
            'total'                      => $this->faker->randomNumber(),
            'status'                     => $this->faker->randomElement(OrderItemStatusEnum::getAllValues()),
            'created_at'                 => Carbon::now(),
            'updated_at'                 => Carbon::now(),
        ];
    }
}
