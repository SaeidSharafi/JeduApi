<?php

namespace Database\Factories;

use App\Models\Enrolment;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class EnrolmentFactory extends Factory
{
    protected $model = Enrolment::class;

    public function definition(): array
    {
        $orderItem = OrderItem::factory()->create()->fresh();
        $startData = $this->faker->optional(0.7)->date;
        return [
            'order_id'                   => $orderItem->order_id,
            'order_item_id'              => $orderItem->id,
            'customer_id'                => $orderItem->order->customer_id,
            'product_delivery_option_id' => $orderItem->product_delivery_option_id,
            'enrollment_status'          => $this->faker->randomElement(\App\Enums\EnrolmentStatusEnum::getAllValues()),
            'access_start_date'          => $startData,
            'access_end_date'            => $startData ? Carbon::parse($startData)
                ->addDays($this->faker->numberBetween(1, 365)) : null,
            'external_enrollment_id'     => $this->faker->randomNumber(),
            'provisioning_data'          => [],
            'notes'                      => $this->faker->word(),
            'created_at'                 => Carbon::now(),
            'updated_at'                 => Carbon::now(),
        ];
    }
}
