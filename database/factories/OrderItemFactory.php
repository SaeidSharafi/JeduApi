<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\PublicationStatusEnum;
use App\Models\Enrolment;
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
        $productDeliveryOptionFactory = ProductDeliveryOption::factory();

        return [
            'order_id'                   => Order::factory(),
            'product_delivery_option_id' => $productDeliveryOptionFactory,
            'qty_ordered'                => 1,
            'payment_type'               => $this->faker->randomElement(OrderItemPaymentTypeEnum::getAllValues()),
            'name'                       => fn (array $attributes
            ) => ProductDeliveryOption::find($attributes['product_delivery_option_id'])->name,
            'sku' => fn (array $attributes
            ) => ProductDeliveryOption::find($attributes['product_delivery_option_id'])->sku,
            'product_data_snapshot_json' => fn (array $attributes
            ) => ProductDeliveryOption::find($attributes['product_delivery_option_id'])->product->toArray(),
            'vendor_id'         => Vendor::factory(),
            'price'             => 0,
            'discount_amount'   => 0,
            'tax_amount'        => 0,
            'total'             => 0,
            'prepayment_amount' => 0,
            'total_refunded'    => 0,
            'qty_refunded'      => 0,
            'status'            => $this->faker->randomElement(OrderItemStatusEnum::getAllValues()),
            'created_at'        => Carbon::now(),
            'updated_at'        => Carbon::now(),
        ];
    }

    public function useExistingRelations(): static
    {
        return $this->state(function (array $attributes) {
            $productDeliveryOption = ProductDeliveryOption::query()
                ->where('status', PublicationStatusEnum::PUBLISHED)
                ->inRandomOrder()->first()             ?? ProductDeliveryOption::factory()->create();
            $vendor = Vendor::inRandomOrder()->first() ?? Vendor::factory()->create();

            return [
                'product_delivery_option_id' => $productDeliveryOption->id,
                'vendor_id'                  => $vendor->id,
                'name'                       => $productDeliveryOption->name,
                'sku'                        => $productDeliveryOption->sku,
                'product_data_snapshot_json' => $productDeliveryOption->product->toArray(),
                'price'                      => $productDeliveryOption->price,
                'status'                     => OrderItemStatusEnum::PENDING,
                'payment_type'               => $productDeliveryOption->is_prepayment_available
                    ? OrderItemPaymentTypeEnum::PRE_PAYMENT
                    : OrderItemPaymentTypeEnum::FULL_PAYMENT,
                'total' => $productDeliveryOption->is_prepayment_available
                    ? $productDeliveryOption->prepayment_amount
                    : $productDeliveryOption->price,
            ];
        });
    }

    /**
     * STATE: Create a corresponding Enrolment for this OrderItem.
     * This is the performant way to create a child record.
     */
    public function withEnrolment(): self
    {
        return $this->has(
            Enrolment::factory()->state(function (array $attributes, OrderItem $orderItem) {
                return [
                    'order_id'                   => $orderItem->order_id,
                    'customer_id'                => $orderItem->order->customer_id,
                    'product_delivery_option_id' => $orderItem->product_delivery_option_id,
                ];
            }),
            'enrolment' // The name of the hasOne relationship on the OrderItem model
        );
    }
}
