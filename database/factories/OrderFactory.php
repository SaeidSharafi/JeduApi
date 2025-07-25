<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Order\OrderStatusEnum;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

final class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $customerFactory = User::factory();

        return [
            'increment_id'           => $this->faker->unique()->randomNumber(6),
            'status'                 => $this->faker->randomElement(OrderStatusEnum::getAllValues()),
            'customer_id'            => $customerFactory,
            'customer_email'         => fn (array $attributes) => User::find($attributes['customer_id'])->email,
            'customer_phone'         => fn (array $attributes) => User::find($attributes['customer_id'])->phone,
            'customer_first_name'    => fn (array $attributes) => User::find($attributes['customer_id'])->first_name,
            'customer_last_name'     => fn (array $attributes) => User::find($attributes['customer_id'])->last_name,
            'customer_snapshot_json' => fn (array $attributes) => User::find($attributes['customer_id'])->toArray(),
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

    /**
     * STATE: Use an existing User instead of creating a new one.
     */
    public function useExistingCustomer(): self
    {
        return $this->state(function (array $attributes) {
            // Find a random existing user, or create one if the DB is empty.
            $customer = User::inRandomOrder()->first() ?? User::factory()->create();

            // Return the state array. This will now prevent the default User::factory() from running.
            return [
                'customer_id'            => $customer->id,
                'customer_email'         => $customer->email,
                'customer_phone'         => $customer->phone,
                'customer_first_name'    => $customer->first_name,
                'customer_last_name'     => $customer->last_name,
                'customer_snapshot_json' => $customer->toArray(),
            ];
        });
    }

    /**
     * HELPER STATE: Attach a given number of OrderItems to the Order.
     */
    public function withItems(int $count = 1): static
    {
        // This is a shortcut for the has() relationship method.
        // It will create $count new OrderItems and associate them with this Order.
        return $this->has(OrderItem::factory()->count($count), 'items');
    }
}
