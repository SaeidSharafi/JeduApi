<?php

namespace Database\Factories;

use App\Enums\Order\RefundStatusEnum;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Refund;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @mixin Factory<Refund> */
class RefundFactory extends Factory
{
    protected $model = Refund::class;

    public function definition(): array
    {
        return [
            'order_id'            => Order::factory(),
            'order_item_id'       => OrderItem::factory(),
            'customer_id'         => User::factory(),
            'created_by'          => Staff::factory(),
            'amount'              => $this->faker->randomNumber(),
            'deduction_amount'    => $this->faker->randomNumber(),
            'status'              => $this->faker->randomElement(RefundStatusEnum::getAllValues()),
            'transaction_details' => $this->faker->words(),
            'refunded_at'         => Carbon::now(),
            'admin_notes'         => $this->faker->word(),
            'created_at'          => Carbon::now(),
            'updated_at'          => Carbon::now(),
        ];
    }
}
