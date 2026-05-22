<?php

declare(strict_types=1);

use App\Enums\Product\DeliveryMethodEnum;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductDeliveryOption;

if (! function_exists('createEnrollment')) {
    function createEnrollment(
        App\Models\User|Illuminate\Contracts\Auth\Authenticatable $customer,
        DeliveryMethodEnum $deliveryMethod,
        int $count = 1,
        ?ProductDeliveryOption $deliveryOption = null,
        bool $provisioning = false,
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
