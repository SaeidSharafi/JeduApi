<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Enrolment;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

final class EnrolmentFactory extends Factory
{
    protected $model = Enrolment::class;

    public function definition(): array
    {
        $startData = $this->faker->optional(0.7)->date;

        return [
            'order_item_id' => OrderItem::factory(),
            'order_id'      => fn (array $attributes
            ) => OrderItem::find($attributes['order_item_id'])->order->id,
            'customer_id' => fn (array $attributes
            ) => OrderItem::find($attributes['order_item_id'])->order->customer_id,
            'product_delivery_option_id' => fn (array $attributes
            ) => OrderItem::find($attributes['order_item_id'])->product_delivery_option_id,
            'enrollment_status' => $this->faker->randomElement(\App\Enums\EnrolmentStatusEnum::getAllValues()),
            'access_start_date' => $startData,
            'access_end_date'   => $startData ? Carbon::parse($startData)
                ->addDays($this->faker->numberBetween(1, 365)) : null,
            'external_enrollment_id' => $this->faker->randomNumber(),
            'provisioning_data'      => [],
            'notes'                  => $this->faker->word(),
            'created_at'             => Carbon::now(),
            'updated_at'             => Carbon::now(),
        ];
    }
}
