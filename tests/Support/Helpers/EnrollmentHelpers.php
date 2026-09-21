<?php

declare(strict_types=1);

use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\Product\ProductableEnum;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductDeliveryOption;

if (! function_exists('createEnrollment')) {
    /**
     * Create an enrollment for the given customer.
     *
     * When no delivery option is given, the generated product carries a productable of
     * `$productableType` (a course by default). A caller-supplied delivery option owns its
     * product, so its productable type is whatever that product already has.
     */
    function createEnrollment(
        App\Models\User|Illuminate\Contracts\Auth\Authenticatable $customer,
        DeliveryMethodEnum $deliveryMethod,
        int $count = 1,
        ?ProductDeliveryOption $deliveryOption = null,
        bool $provisioning = false,
        ProductableEnum $productableType = ProductableEnum::COURSE,
    ): App\Models\Enrollment {
        $order = Order::factory()->create(
            [
                'customer_id'            => $customer->id,
                'customer_email'         => $customer->email,
                'customer_phone'         => $customer->phone,
                'customer_first_name'    => $customer->first_name,
                'customer_last_name'     => $customer->last_name,
                'customer_snapshot_json' => $customer->toArray(),
            ]
        );

        $product = $deliveryOption
            ?: ProductDeliveryOption::factory()->create([
                'delivery_method'  => $deliveryMethod->value,
                'fulfillment_type' => $deliveryMethod->getFulfillmentType(),
                'product_id'       => match ($productableType) {
                    ProductableEnum::SEMINAR       => Product::factory()->withSeminar(),
                    ProductableEnum::DIGITAL_ASSET => Product::factory()->withDigitalAsset(),
                    // A Bundle never carries its own enrollment (EnrollmentObserver rejects it),
                    // so only the three sellable productables need a state here.
                    default => Product::factory()->withCourse(),
                },
            ]);

        $order_item = OrderItem::factory()
            ->withEnrollment($provisioning)
            ->count($count)
            ->create([
                'order_id'                   => $order->id,
                'product_delivery_option_id' => $product->id,
                'name'                       => $product->name,
                'sku'                        => $product->sku,
                'product_data_snapshot_json' => $product->product->toArray(),
            ])->fresh();

        return $order_item->first()->enrollment;
    }
}
