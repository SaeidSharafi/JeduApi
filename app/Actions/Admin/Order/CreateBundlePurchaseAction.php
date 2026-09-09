<?php

declare(strict_types=1);

namespace App\Actions\Admin\Order;

use App\Data\Admin\ProductDeliveryOption\ProductDeliveryOptionShowData;
use App\Enums\EnrollmentStatusEnum;
use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Models\BundlePurchase;
use App\Models\Order;
use App\Models\ProductDeliveryOption;

final readonly class CreateBundlePurchaseAction
{
    public function handle(Order $order, ProductDeliveryOption $parent): BundlePurchase
    {
        $purchase = $order->bundlePurchases()->create([
            'product_delivery_option_id' => $parent->id,
            'bundle_name'                => $parent->product->productable->full_name,
            'product_name'               => $parent->product->name,
            'name'                       => $parent->name,
            'sku'                        => $parent->sku,
            'base_value'                 => $parent->bundleComponents->sum('price'),
            'selling_price'              => $parent->price,
            'composition_version'        => $parent->composition_version,
            'checkout_status'            => OrderStatusEnum::PENDING,
            'product_data_snapshot_json' => ProductDeliveryOptionShowData::from($parent)->toArray(),
        ]);

        foreach ($parent->bundleComponents->sortBy('id') as $component) {
            $allocation = (int) $component->getRelation('pivot')->allocation;
            $discount   = $component->price - $allocation;
            $item       = $purchase->components()->create([
                'order_id'                   => $order->id,
                'product_delivery_option_id' => $component->id,
                'vendor_id'                  => $component->product->vendor_id,
                'name'                       => $component->product->name,
                'sku'                        => $component->sku,
                'product_data_snapshot_json' => ProductDeliveryOptionShowData::from($component)->toArray(),
                'qty_ordered'                => 1,
                'payment_type'               => OrderItemPaymentTypeEnum::FULL_PAYMENT,
                'status'                     => OrderItemStatusEnum::PENDING,
                'price'                      => $component->price,
                'total'                      => $allocation,
                'discount_amount'            => 0,
                'tax_amount'                 => 0,
                'prepayment_amount'          => null,
                'pricing_metadata'           => [
                    'original_price'          => $component->price,
                    'base_price_amount'       => $component->price,
                    'paid_amount'             => $allocation,
                    'product_discount_amount' => $discount,
                    'cart_discount_amount'    => 0,
                    'total_discount_amount'   => $discount,
                    'bundle_discount_amount'  => $discount,
                    'discount_type'           => 'bundle',
                    'discount_amount'         => $discount,
                    'discount_percentage'     => null,
                ],
            ]);
            $item->enrollment()->create([
                'order_id'                   => $order->id,
                'customer_id'                => $order->customer_id,
                'product_delivery_option_id' => $component->id,
                'enrollment_status'          => EnrollmentStatusEnum::AWAITING_PAYMENT,
            ]);
        }

        return $purchase;
    }
}
