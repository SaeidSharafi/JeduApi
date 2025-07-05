<?php

namespace Database\Factories;

use App\Enums\OrderPaymentStatusEnum;
use App\Enums\OrderStatusEnum;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $customer = User::factory()->create();
        return [
            'increment_id'           => $this->faker->word(),
            'status'                 => $this->faker->randomElement(OrderStatusEnum::getAllValues()),
            'customer_id'            => $customer->id,
            'customer_email'         => $customer->email,
            'customer_phone'         => $customer->phone,
            'customer_first_name'    => $customer->first_name,
            'customer_last_name'     => $customer->last_name,
            'customer_snapshot_json' => $customer->toArray(),
            'total_item_count'       => $this->faker->numberBetween(1, 10),
            'total_qty_ordered'      => $this->faker->numberBetween(1, 100),
            'subtotal'               => $this->faker->randomNumber(),
            'discount_amount'        => $this->faker->randomNumber(),
            'tax_amount'             => $this->faker->randomNumber(),
            'grand_total'            => $this->faker->randomNumber(),
            'currency_code'          => $this->faker->currencyCode(),
            'applied_coupon_code'    => $this->faker->word(),
            'admin_notes'            => $this->faker->word(),
            'created_at'             => Carbon::now(),
            'updated_at'             => Carbon::now(),
        ];
    }
}
