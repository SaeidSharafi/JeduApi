<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Staff;
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
            'total_item_count'       => 0,
            'total_qty_ordered'      => 0,
            'subtotal'               => 0,
            'discount_amount'        => 0,
            'tax_amount'             => 0,
            'grand_total'            => 0,
            'currency_code'          => $this->faker->currencyCode(),
            'applied_coupon_code'    => $this->faker->word(),
            'admin_notes'            => $this->faker->word(),
            'created_at'             => Carbon::now(),
            'updated_at'             => Carbon::now(),
            'created_by'             => Staff::factory(),
        ];
    }

    public function withCalculatedTotals(array $items): self
    {
        // The 'has' method can accept a factory instance with a sequence.
        // This creates items based on the array of data we provide.
        return $this->has(
            OrderItem::factory()
                ->count(count($items))
                ->sequence(...$items), // The spread operator provides the sequence
            'items'
        )->afterCreating(function (Order $order) {
            // After the order and its items are created, we recalculate all totals
            // to ensure they are perfectly in sync, just like our action does.
            $order->subtotal        = $order->items->sum(fn ($item) => $item->price * $item->qty_ordered);
            $order->discount_amount = $order->items->sum(fn ($item) => $item->discount_amount * $item->qty_ordered);
            $order->tax_amount      = $order->items->sum(fn ($item) => $item->tax_amount * $item->qty_ordered);

            // Calculate totals based on our established business logic
            $grandTotal          = 0;
            $fullValueGrandTotal = 0;

            foreach ($order->items as $item) {
                $lineItemFullValue = ($item->price - $item->discount_amount + $item->tax_amount) * $item->qty_ordered;
                $fullValueGrandTotal += $lineItemFullValue;

                if ($item->payment_type === OrderItemPaymentTypeEnum::FULL_PAYMENT) {
                    $grandTotal += $lineItemFullValue;
                } else { // PRE_PAYMENT
                    $grandTotal += $item->total * $item->qty_ordered;
                }
            }

            $order->grand_total            = $grandTotal;
            $order->full_value_grand_total = $fullValueGrandTotal;
            $order->total_item_count       = $order->items->count();
            $order->total_qty_ordered      = $order->items->sum('qty_ordered');
            $order->save();
        });
    }

    public function withCalculatedTotalsAutomated(): self
    {
        return $this->afterCreating(function (Order $order) {
            // Refresh the 'items' relationship to ensure all data is loaded.
            $order->load('items');

            // After the order and its items are created, we recalculate all totals.
            $order->subtotal        = $order->items->sum(fn (OrderItem $item) => $item->price * $item->qty_ordered);
            $order->discount_amount = $order->items->sum(fn (OrderItem $item) => $item->discount_amount
                * $item->qty_ordered);
            $order->tax_amount = $order->items->sum(fn (OrderItem $item) => $item->tax_amount * $item->qty_ordered);

            // Calculate totals based on our established business logic
            $grandTotal          = 0;
            $fullValueGrandTotal = 0;

            foreach ($order->items as $item) {
                $lineItemFullValue = ($item->price - $item->discount_amount + $item->tax_amount) * $item->qty_ordered;
                $fullValueGrandTotal += $lineItemFullValue;

                if ($item->payment_type === OrderItemPaymentTypeEnum::FULL_PAYMENT->value) {
                    $grandTotal += $lineItemFullValue;
                } else { // PRE_PAYMENT
                    // Note: The logic in your original OrderItemFactory seems to set 'total' incorrectly.
                    // Assuming 'total' should be the pre-payment amount for a pre-payment item.
                    // If an item is set to pre-payment, its 'total' should reflect the amount to be paid now.
                    $grandTotal += $item->total;
                }
            }
            $order->grand_total = $grandTotal;
            $order->full_value_grand_total
                                      = $fullValueGrandTotal; // You might want to define this field in your model/migration
            $order->total_item_count  = $order->items->count();
            $order->total_qty_ordered = $order->items->sum('qty_ordered');
            $order->save();
        });
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
